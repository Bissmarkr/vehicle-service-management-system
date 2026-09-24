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

    public function myPayments(Request $request)
    {
        $customer = Customer::where('user_id', $request->user()->user_id)->firstOrFail();
        $payments = Payment::with(['invoice', 'booking.vehicle', 'booking.service'])
            ->where('customer_id', $customer->customer_id)
            ->where('payment_type', 'advance')
            ->latest('payment_date')
            ->get();

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function adminPaymentRequests()
    {
        $payments = Payment::with(['customer', 'booking.vehicle', 'booking.service', 'invoice'])
            ->where(function ($query) {
                $query->where(function ($advanceQuery) {
                    $advanceQuery->where('payment_type', 'advance')
                        ->where('payment_status', 'COMPLETED')
                        ->whereIn('admin_status', ['PENDING_APPROVAL', 'APPROVED', 'REJECTED']);
                })->orWhere(function ($remainingQuery) {
                    $remainingQuery->where('payment_type', 'remaining')
                        ->whereIn('payment_status', ['PAID', 'COMPLETED']);
                });
            })
            ->latest('payment_date')
            ->get()
            ->map(fn (Payment $payment) => $this->paymentRequestPayload($payment));

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function adminPaymentRequest(Payment $payment)
    {
        return response()->json([
            'success' => true,
            'data' => $this->paymentRequestPayload($payment->load(['customer', 'booking.vehicle', 'booking.service', 'invoice'])),
        ]);
    }

    public function markPaymentRequestRead(Payment $payment)
    {
        if ($payment->payment_type !== 'remaining' || ! in_array((string) $payment->payment_status, ['PAID', 'COMPLETED'], true)) {
            return response()->json(['success' => false, 'message' => 'This payment request cannot be marked as read.'], 409);
        }

        $payment->update(['admin_status' => 'READ']);

        return response()->json([
            'success' => true,
            'data' => $this->paymentRequestPayload($payment->fresh()->load(['customer', 'booking.vehicle', 'booking.service', 'invoice.payments'])),
        ]);
    }

    public function approvePaymentRequest(Request $request, Payment $payment)
    {
        if ($payment->payment_type !== 'advance' || $payment->payment_status !== 'COMPLETED' || $payment->admin_status !== 'PENDING_APPROVAL') {
            return response()->json(['success' => false, 'message' => 'This payment is not waiting for approval.'], 409);
        }

        DB::transaction(function () use ($request, $payment) {
            $payment->update([
                'admin_status' => 'APPROVED',
                'approved_by' => $request->user()->user_id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);
            $payment->invoice()->update([
                'advance_payment_status' => 'COMPLETED',
                'invoice_status' => 'PARTIALLY_PAID',
            ]);
            $payment->booking()->update([
                'payment_status' => 'PARTIALLY_PAID',
                'booking_status' => 'CONFIRMED',
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Payment approved successfully.', 'data' => $this->paymentRequestPayload($payment->fresh()->load(['customer', 'booking.vehicle', 'booking.service', 'invoice']))]);
    }

    public function rejectPaymentRequest(Request $request, Payment $payment)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        if ($payment->payment_type !== 'advance' || $payment->payment_status !== 'COMPLETED' || $payment->admin_status !== 'PENDING_APPROVAL') {
            return response()->json(['success' => false, 'message' => 'This payment is not waiting for approval.'], 409);
        }

        DB::transaction(function () use ($data, $payment) {
            $payment->update([
                'admin_status' => 'REJECTED',
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => $data['rejection_reason'],
            ]);
            $payment->invoice()->update(['advance_payment_status' => 'REJECTED']);
            $payment->booking()->update(['payment_status' => 'REJECTED']);
        });

        return response()->json(['success' => true, 'message' => 'Payment rejected.', 'data' => $this->paymentRequestPayload($payment->fresh()->load(['customer', 'booking.vehicle', 'booking.service', 'invoice']))]);
    }

    public function store(Request $request)
    {
        $payment = Payment::create($request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,invoice_id'],
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:CASH,CARD,BANK_TRANSFER,OTHER'],
            'payment_date' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'in:PENDING,PROCESSING,PAID,COMPLETED,FAILED,CANCELLED,REFUNDED'],
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

        if (in_array((string) $invoice->advance_payment_status, ['PAID', 'APPROVED'], true)) {
            return response()->json(['success' => false, 'message' => 'Advance payment already completed.'], 409);
        }

        $existing = Payment::where('invoice_id', $invoice->invoice_id)
            ->where('payment_type', 'advance')
            ->latest('payment_id')
            ->first();

        if ($existing && $existing->admin_status !== 'REJECTED' && in_array((string) $existing->payment_status, ['PAID', 'COMPLETED', 'PROCESSING'], true)) {
            $message = in_array((string) $existing->payment_status, ['PAID', 'COMPLETED'], true)
                ? 'Advance payment already completed.'
                : 'An advance payment is already in progress.';
            return response()->json(['success' => false, 'message' => $message], 409);
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
            $payment->admin_status = null;
            $payment->approved_by = null;
            $payment->approved_at = null;
            $payment->rejection_reason = null;
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

    public function createRemainingCheckoutSession(Request $request)
    {
        $payload = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:service_bookings,booking_id'],
        ]);

        $customer = Customer::where('user_id', $request->user()->user_id)->first();
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Customer account not found.'], 403);
        }

        $invoice = Invoice::with('booking')->where('booking_id', $payload['booking_id'])->first();
        if (! $invoice || (int) $invoice->booking?->customer_id !== (int) $customer->customer_id) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to pay this booking.'], 403);
        }

        $successfulTotal = (float) Payment::where('invoice_id', $invoice->invoice_id)
            ->whereIn('payment_status', ['PAID', 'COMPLETED'])
            ->sum('payment_amount');
        $advancePaid = Payment::where('invoice_id', $invoice->invoice_id)
            ->where('payment_type', 'advance')
            ->whereIn('payment_status', ['PAID', 'COMPLETED'])
            ->exists();
        if (! $advancePaid) {
            return response()->json(['success' => false, 'message' => 'Advance payment must be completed before paying the remaining balance.'], 409);
        }
        $remainingAmount = round((float) $invoice->total_amount - $successfulTotal, 2);

        if ($remainingAmount <= 0) {
            return response()->json(['success' => false, 'message' => 'This invoice is already fully paid.'], 409);
        }

        $existing = Payment::where('invoice_id', $invoice->invoice_id)
            ->where('payment_type', 'remaining')
            ->latest('payment_id')
            ->first();
        if ($existing && in_array((string) $existing->payment_status, ['PAID', 'COMPLETED'], true)) {
            return response()->json(['success' => false, 'message' => 'Remaining payment is already completed.'], 409);
        }
        if ($existing && $existing->stripe_checkout_session_id && in_array((string) $existing->payment_status, ['PENDING', 'PROCESSING'], true)) {
            return response()->json(['success' => false, 'message' => 'A remaining payment is already in progress.'], 409);
        }

        $stripeSecret = (string) config('services.stripe.secret');
        if ($stripeSecret === '' || ! str_starts_with($stripeSecret, 'sk_test_')) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe TEST mode is not configured. Set a valid STRIPE_SECRET in Laravel .env.',
                'error_code' => 'STRIPE_CONFIGURATION_ERROR',
            ], 503);
        }

        try {
            $session = $this->stripePaymentService->createRemainingCheckoutSession($invoice, $customer, $remainingAmount);
        } catch (\Throwable $exception) {
            Log::error('Remaining Stripe Checkout session creation failed.', [
                'invoice_id' => $invoice->invoice_id,
                'booking_id' => $invoice->booking_id,
                'customer_id' => $customer->customer_id,
                'exception_class' => get_class($exception),
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create remaining payment checkout session.',
                'error_code' => 'STRIPE_CHECKOUT_ERROR',
            ], 502);
        }

        DB::transaction(function () use ($invoice, $customer, $session, $remainingAmount) {
            $payment = Payment::firstOrNew(['invoice_id' => $invoice->invoice_id, 'payment_type' => 'remaining']);
            $payment->booking_id = $invoice->booking_id;
            $payment->customer_id = $customer->customer_id;
            $payment->payment_amount = $remainingAmount;
            $payment->payment_method = 'CARD';
            $payment->payment_date = now();
            $payment->payment_status = 'PENDING';
            $payment->admin_status = null;
            $payment->approved_by = null;
            $payment->approved_at = null;
            $payment->rejection_reason = null;
            $payment->status = 'PENDING';
            $payment->currency = strtoupper((string) config('services.stripe.currency', 'LKR'));
            $payment->stripe_checkout_session_id = $session->id;
            $payment->stripe_payment_intent_id = $session->payment_intent;
            $payment->stripe_event_id = null;
            $payment->paid_at = null;
            $payment->save();

            $invoice->update(['remaining_payment_status' => 'PENDING']);
            $invoice->booking()->update(['payment_status' => 'PARTIALLY_PAID']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Remaining payment checkout session created successfully.',
            'checkout_url' => $session->url,
            'session_id' => $session->id,
            'remaining_amount' => $remainingAmount,
        ]);
    }

    public function paymentStatus(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (empty($sessionId)) {
            return response()->json(['success' => false, 'message' => 'session_id is required.'], 422);
        }

        $payment = Payment::where('stripe_checkout_session_id', $sessionId)->first();
        $customer = Customer::where('user_id', $request->user()->user_id)->first();
        $session = null;
        try {
            $session = $this->stripePaymentService->getCheckoutSession($sessionId);
        } catch (\Throwable $exception) {
            Log::warning('Unable to verify Stripe Checkout Session.', ['session_id' => $sessionId, 'exception' => $exception->getMessage()]);
        }

        $sessionCustomerId = $session?->metadata->customer_id ?? $payment?->customer_id;
        if (! $customer || ($sessionCustomerId && (int) $sessionCustomerId !== (int) $customer->customer_id)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to view this payment.'], 403);
        }

        if ($session && $session->payment_status === 'paid') {
            $payment = $this->markSessionAsCompleted($session, $payment);
        }

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment session not found.'], 404);
        }

        $invoice = $payment->invoice()->with(['booking.vehicle', 'booking.service'])->first();
        if ((int) $payment->customer_id !== (int) $customer->customer_id) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to view this payment.'], 403);
        }

        $successfulTotal = $invoice?->payments
            ?->whereIn('payment_status', ['PAID', 'COMPLETED'])
            ?->sum('payment_amount') ?? 0;

        return response()->json([
            'success' => true,
            'paid' => in_array((string) $payment->payment_status, ['PAID', 'COMPLETED'], true),
            'status' => $payment->payment_status,
            'payment_status' => $payment->payment_status,
            'payment_type' => $payment->payment_type,
            'admin_status' => $payment->admin_status,
            'rejection_reason' => $payment->rejection_reason,
            'booking_id' => $invoice?->booking_id,
            'advance_amount' => $invoice?->advance_amount,
            'invoice_status' => $invoice?->invoice_status,
            'advance_payment_status' => $invoice?->advance_payment_status,
            'remaining_amount' => $invoice?->remaining_amount,
            'total_paid' => $successfulTotal,
            'invoice_id' => $invoice?->invoice_id,
            'payment' => $payment,
            'booking' => $invoice?->booking,
            'invoice' => $invoice,
        ]);
    }

    private function markSessionAsCompleted($session, ?Payment $payment): ?Payment
    {
        $invoiceId = $session->metadata->invoice_id ?? $payment?->invoice_id;
        $paymentType = $session->metadata->payment_type ?? $payment?->payment_type ?? 'advance';
        $invoice = Invoice::with('booking')->find($invoiceId);
        if (! $invoice) {
            return $payment;
        }

        return DB::transaction(function () use ($session, $payment, $invoice) {
            $payment ??= Payment::firstOrNew([
                'invoice_id' => $invoice->invoice_id,
                'payment_type' => $paymentType,
            ]);
            $payment->booking_id = $invoice->booking_id;
            $payment->customer_id = $invoice->customer_id;
            $payment->payment_type = $paymentType;
            $payment->payment_amount = $payment->payment_amount ?: ($paymentType === 'remaining' ? max(0, (float) $invoice->total_amount - (float) $invoice->advance_amount) : $invoice->advance_amount);
            $payment->payment_method = 'CARD';
            $payment->payment_date = now();
            $wasCompleted = in_array((string) $payment->payment_status, ['COMPLETED', 'PAID'], true);
            $payment->payment_status = $paymentType === 'remaining' ? 'PAID' : 'COMPLETED';
            $payment->admin_status = $paymentType === 'advance' ? ($wasCompleted ? $payment->admin_status : 'PENDING_APPROVAL') : 'PENDING_REVIEW';
            $payment->status = $paymentType === 'remaining' ? 'PAID' : 'COMPLETED';
            $payment->currency = strtoupper((string) config('services.stripe.currency', 'LKR'));
            $payment->stripe_checkout_session_id = $session->id;
            $payment->stripe_payment_intent_id = $session->payment_intent;
            $payment->paid_at = now();
            $payment->save();

            $this->syncInvoicePaymentState($invoice, $paymentType);

            return $payment->fresh();
        });
    }

    private function syncInvoicePaymentState(Invoice $invoice, string $paymentType): void
    {
        $successfulTotal = (float) Payment::where('invoice_id', $invoice->invoice_id)
            ->whereIn('payment_status', ['PAID', 'COMPLETED'])
            ->sum('payment_amount');
        $advancePaid = Payment::where('invoice_id', $invoice->invoice_id)
            ->where('payment_type', 'advance')
            ->whereIn('payment_status', ['PAID', 'COMPLETED'])
            ->exists();
        $remainingPaid = Payment::where('invoice_id', $invoice->invoice_id)
            ->where('payment_type', 'remaining')
            ->whereIn('payment_status', ['PAID', 'COMPLETED'])
            ->exists();
        $fullyPaid = $successfulTotal >= (float) $invoice->total_amount;

        $invoice->update([
            'advance_payment_status' => $advancePaid ? 'COMPLETED' : $invoice->advance_payment_status,
            'remaining_payment_status' => $remainingPaid ? 'PAID' : $invoice->remaining_payment_status,
            'remaining_amount' => max(0, round((float) $invoice->total_amount - $successfulTotal, 2)),
            'invoice_status' => $fullyPaid ? 'PAID' : ($successfulTotal > 0 ? 'PARTIALLY_PAID' : 'UNPAID'),
        ]);
        $invoice->booking()->update([
            'payment_status' => $fullyPaid ? 'PAID' : ($successfulTotal > 0 ? 'PARTIALLY_PAID' : 'PENDING'),
            'booking_status' => $successfulTotal > 0 ? 'CONFIRMED' : $invoice->booking?->booking_status,
        ]);
    }

    private function paymentRequestPayload(Payment $payment): array
    {
        return [
            'payment_id' => $payment->payment_id,
            'invoice_id' => $payment->invoice_id,
            'booking_id' => $payment->booking_id,
            'customer' => $payment->customer,
            'vehicle' => $payment->booking?->vehicle,
            'service' => $payment->booking?->service,
            'payment_amount' => $payment->payment_amount,
            'payment_type' => $payment->payment_type,
            'total_invoice_amount' => $payment->invoice?->total_amount,
            'advance_amount' => $payment->invoice?->advance_amount,
            'remaining_amount' => $payment->invoice?->remaining_amount,
            'total_paid' => $payment->invoice?->payments
                ?->whereIn('payment_status', ['PAID', 'COMPLETED'])
                ?->sum('payment_amount'),
            'invoice_status' => $payment->invoice?->invoice_status,
            'payment_method' => $payment->payment_method,
            'payment_date' => $payment->payment_date,
            'payment_status' => $payment->payment_status,
            'admin_status' => $payment->admin_status,
            'stripe_payment_id' => $payment->stripe_payment_intent_id,
            'stripe_session_id' => $payment->stripe_checkout_session_id,
            'approved_by' => $payment->approved_by,
            'approved_at' => $payment->approved_at,
            'rejection_reason' => $payment->rejection_reason,
        ];
    }
}
