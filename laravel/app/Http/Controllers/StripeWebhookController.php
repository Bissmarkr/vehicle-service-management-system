<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            return response()->json(['error' => 'Missing Stripe signature.'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, config('services.stripe.webhook_secret'));
        } catch (\Throwable $exception) {
            return response()->json(['error' => 'Invalid Stripe signature.'], 400);
        }

        $eventType = $event->type ?? null;
        $eventId = $event->id ?? null;
        $object = $event->data->object ?? null;

        if (! $object) {
            return response()->json(['received' => true]);
        }

        if ($eventId && Payment::where('stripe_event_id', $eventId)->exists()) {
            return response()->json(['received' => true]);
        }

        if ($eventType === 'checkout.session.completed') {
            if (($object->payment_status ?? null) !== 'paid') {
                return response()->json(['received' => true]);
            }
            return $this->handleCheckoutSessionCompleted($object, $eventId);
        }

        if (in_array($eventType, ['payment_intent.succeeded', 'payment_intent.payment_failed'], true)) {
            return $this->handlePaymentIntentEvent($eventType, $object, $eventId);
        }

        return response()->json(['received' => true]);
    }

    protected function handleCheckoutSessionCompleted($session, ?string $eventId)
    {
        $invoiceId = $session->metadata->invoice_id ?? null;
        $paymentIntentId = $session->payment_intent ?? null;
        $customerId = $session->metadata->customer_id ?? null;
        $paymentType = $session->metadata->payment_type ?? 'advance';
        if (! in_array($paymentType, ['advance', 'remaining'], true)) {
            return response()->json(['received' => true]);
        }

        $existing = Payment::where('stripe_checkout_session_id', $session->id)
            ->orWhere('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if ($existing && in_array($existing->payment_status, ['PAID', 'COMPLETED'], true) && $existing->admin_status !== 'REJECTED') {
            return response()->json(['received' => true]);
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($invoice, $session, $paymentIntentId, $customerId, $eventId, $paymentType) {
            $payment = Payment::firstOrNew(['invoice_id' => $invoice->invoice_id, 'payment_type' => $paymentType]);
            $payment->booking_id = $invoice->booking_id;
            $payment->customer_id = $customerId ?? $invoice->booking?->customer_id;
            $payment->payment_amount = $payment->payment_amount ?: ($paymentType === 'remaining' ? max(0, (float) $invoice->total_amount - (float) $invoice->advance_amount) : $invoice->advance_amount);
            $payment->payment_method = 'CARD';
            $payment->payment_date = now();
            $payment->payment_status = $paymentType === 'remaining' ? 'PAID' : 'COMPLETED';
            $payment->admin_status = $paymentType === 'advance' ? ($payment->admin_status === 'REJECTED' ? 'PENDING_APPROVAL' : ($payment->admin_status ?: 'PENDING_APPROVAL')) : 'PENDING_REVIEW';
            $payment->rejection_reason = null;
            $payment->status = $paymentType === 'remaining' ? 'PAID' : 'COMPLETED';
            $payment->currency = strtoupper((string) config('services.stripe.currency', 'lkr'));
            $payment->stripe_checkout_session_id = $session->id;
            $payment->stripe_payment_intent_id = $paymentIntentId;
            $payment->stripe_event_id = $eventId;
            $payment->paid_at = now();
            $payment->save();

            $this->syncInvoicePaymentState($invoice, $paymentType);
        });

        return response()->json(['received' => true]);
    }

    protected function handlePaymentIntentEvent(string $eventType, $paymentIntent, ?string $eventId)
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $payment) {
            return response()->json(['received' => true]);
        }

        if (in_array($payment->payment_status, ['PAID', 'COMPLETED'], true) && $eventType === 'payment_intent.succeeded' && $payment->admin_status !== 'REJECTED') {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($payment, $eventType, $paymentIntent, $eventId) {
            $invoice = $payment->invoice()->first();

            if ($eventType === 'payment_intent.succeeded') {
                $payment->update([
                    'payment_status' => $payment->payment_type === 'remaining' ? 'PAID' : 'COMPLETED',
                    'admin_status' => $payment->payment_type === 'advance' ? ($payment->admin_status === 'REJECTED' ? 'PENDING_APPROVAL' : ($payment->admin_status ?: 'PENDING_APPROVAL')) : 'PENDING_REVIEW',
                    'rejection_reason' => null,
                    'status' => $payment->payment_type === 'remaining' ? 'PAID' : 'COMPLETED',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_event_id' => $eventId,
                    'paid_at' => now(),
                ]);

                if ($invoice) {
                    $this->syncInvoicePaymentState($invoice, $payment->payment_type);
                }
            }

            if ($eventType === 'payment_intent.payment_failed') {
                $payment->update([
                    'payment_status' => 'FAILED',
                    'status' => 'FAILED',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_event_id' => $eventId,
                ]);
            }
        });

        return response()->json(['received' => true]);
    }

    protected function syncInvoicePaymentState(Invoice $invoice, string $paymentType): void
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

        if ($invoice->booking) {
            $invoice->booking()->update([
                'payment_status' => $fullyPaid ? 'PAID' : ($successfulTotal > 0 ? 'PARTIALLY_PAID' : 'PENDING'),
                'booking_status' => $successfulTotal > 0 ? 'CONFIRMED' : $invoice->booking->booking_status,
            ]);
        }
    }
}
