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

        $existing = Payment::where('stripe_checkout_session_id', $session->id)
            ->orWhere('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if ($existing && $existing->payment_status === 'PAID') {
            return response()->json(['received' => true]);
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($invoice, $session, $paymentIntentId, $customerId, $eventId) {
            $payment = Payment::firstOrNew(['invoice_id' => $invoice->invoice_id, 'payment_type' => 'advance']);
            $payment->booking_id = $invoice->booking_id;
            $payment->customer_id = $customerId ?? $invoice->booking?->customer_id;
            $payment->payment_amount = $invoice->advance_amount;
            $payment->payment_method = 'CARD';
            $payment->payment_date = now();
            $payment->payment_status = 'PAID';
            $payment->status = 'PAID';
            $payment->currency = strtoupper((string) config('services.stripe.currency', 'lkr'));
            $payment->stripe_checkout_session_id = $session->id;
            $payment->stripe_payment_intent_id = $paymentIntentId;
            $payment->stripe_event_id = $eventId;
            $payment->paid_at = now();
            $payment->save();

            $invoice->update(['advance_payment_status' => 'PAID']);

            if ($invoice->booking) {
                $invoice->booking()->update([
                    'payment_status' => 'PAID',
                    'booking_status' => 'CONFIRMED',
                ]);
            }
        });

        return response()->json(['received' => true]);
    }

    protected function handlePaymentIntentEvent(string $eventType, $paymentIntent, ?string $eventId)
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $payment) {
            return response()->json(['received' => true]);
        }

        if ($payment->payment_status === 'PAID' && $eventType === 'payment_intent.succeeded') {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($payment, $eventType, $paymentIntent, $eventId) {
            $invoice = $payment->invoice()->first();

            if ($eventType === 'payment_intent.succeeded') {
                $payment->update([
                    'payment_status' => 'PAID',
                    'status' => 'PAID',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_event_id' => $eventId,
                    'paid_at' => now(),
                ]);

                if ($invoice) {
                    $invoice->update(['advance_payment_status' => 'PAID']);
                    if ($invoice->booking) {
                        $invoice->booking()->update([
                            'payment_status' => 'PAID',
                            'booking_status' => 'CONFIRMED',
                        ]);
                    }
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
}
