<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private StripePaymentService $stripePaymentService)
    {
    }

    public function index()
    {
        return response()->json(['success' => true, 'data' => Payment::with('invoice')->latest('payment_date')->get()]);
    }

    public function store(Request $request)
    {
        $payment = Payment::create($request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,invoice_id'],
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:CASH,CARD,BANK_TRANSFER,OTHER'],
            'payment_date' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'in:PENDING,PROCESSING,PAID,FAILED,CANCELLED,REFUNDED'],
        ]));

        return response()->json(['success' => true, 'data' => $payment->load('invoice')], 201);
    }

    public function show(Payment $payment)
    {
        return response()->json(['success' => true, 'data' => $payment->load('invoice')]);
    }

    public function createCheckoutSession(Request $request)
    {
        $payload = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,invoice_id'],
        ]);

        $user = $request->user();
        $customer = Customer::where('user_id', $user->user_id)->first();

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Customer account not found.'], 403);
        }

        $invoice = Invoice::with('booking')->find($payload['invoice_id']);
        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        if ((int) $invoice->booking?->customer_id !== (int) $customer->customer_id) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to pay this invoice.'], 403);
        }

        if ((float) $invoice->advance_amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invoice is not available for payment.'], 422);
        }

        if ((string) $invoice->advance_payment_status === 'PAID') {
            return response()->json(['success' => false, 'message' => 'Advance payment has already been completed.'], 422);
        }

        $existing = Payment::where('invoice_id', $invoice->invoice_id)
            ->where('payment_type', 'advance')
            ->whereIn('payment_status', ['PAID', 'PROCESSING'])
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Advance payment has already been completed.'], 422);
        }

        $stripeSecret = (string) config('services.stripe.secret');
        if ($stripeSecret === '' || ! str_starts_with($stripeSecret, 'sk_test_')) {
            Log::error('Stripe Checkout is not configured.', [
                'invoice_id' => $invoice->invoice_id,
                'customer_id' => $customer->customer_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe TEST mode is not configured. Set a valid STRIPE_SECRET in Laravel .env.',
                'error_code' => 'STRIPE_CONFIGURATION_ERROR',
            ], 503);
        }

        try {
            $session = $this->stripePaymentService->createCheckoutSession($invoice, $customer);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            Log::error('Stripe Checkout API request failed.', [
                'invoice_id' => $invoice->invoice_id,
                'customer_id' => $customer->customer_id,
                'exception_class' => get_class($exception),
                'http_status' => $exception->getHttpStatus(),
                'stripe_error_type' => $exception->getError()?->type,
                'stripe_error_code' => $exception->getStripeCode(),
                'stripe_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create Stripe Checkout Session.',
                'error_code' => 'STRIPE_CHECKOUT_ERROR',
            ], 502);
        } catch (\Throwable $exception) {
            Log::error('Stripe Checkout session creation failed.', [
                'invoice_id' => $invoice->invoice_id,
                'customer_id' => $customer->customer_id,
                'exception_class' => get_class($exception),
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create Stripe checkout session.',
                'error_code' => 'STRIPE_CHECKOUT_ERROR',
            ], 502);
        }

        DB::transaction(function () use ($invoice, $customer, $session) {
            $payment = Payment::firstOrNew(['invoice_id' => $invoice->invoice_id, 'payment_type' => 'advance']);
            $payment->booking_id = $invoice->booking_id;
            $payment->customer_id = $customer->customer_id;
            $payment->payment_amount = $invoice->advance_amount;
            $payment->payment_method = 'CARD';
            $payment->payment_date = now();
            $payment->payment_status = 'PENDING';
            $payment->status = 'PENDING';
            $payment->currency = strtoupper((string) config('services.stripe.currency', 'LKR'));
            $payment->stripe_checkout_session_id = $session->id;
            $payment->stripe_payment_intent_id = $session->payment_intent;
            $payment->paid_at = null;
            $payment->save();

            if ($invoice->booking) {
                $invoice->booking()->update(['payment_status' => 'PENDING']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Checkout session created successfully',
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ]);
    }

    public function paymentStatus(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (empty($sessionId)) {
            return response()->json(['success' => false, 'message' => 'session_id is required.'], 422);
        }

        $payment = Payment::where('stripe_checkout_session_id', $sessionId)->first();
        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment session not found.'], 404);
        }

        $invoice = $payment->invoice()->with(['booking.vehicle', 'booking.service'])->first();
        $customer = Customer::where('user_id', $request->user()->user_id)->first();
        if (! $customer || (int) $payment->customer_id !== (int) $customer->customer_id) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to view this payment.'], 403);
        }

        return response()->json([
            'success' => true,
            'paid' => (string) $payment->payment_status === 'PAID',
            'status' => $payment->payment_status,
            'invoice_status' => $invoice?->invoice_status,
            'advance_payment_status' => $invoice?->advance_payment_status,
            'remaining_amount' => $invoice?->remaining_amount,
            'invoice_id' => $invoice?->invoice_id,
            'payment' => $payment,
            'booking' => $invoice?->booking,
            'invoice' => $invoice,
        ]);
    }
}
