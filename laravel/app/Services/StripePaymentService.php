<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use InvalidArgumentException;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripePaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCheckoutSession(Invoice $invoice, Customer $customer): Session
    {
        return $this->createPaymentSession($invoice, $customer, (float) $invoice->advance_amount, 'advance');
    }

    public function createRemainingCheckoutSession(Invoice $invoice, Customer $customer, float $remainingAmount): Session
    {
        return $this->createPaymentSession($invoice, $customer, $remainingAmount, 'remaining');
    }

    private function createPaymentSession(Invoice $invoice, Customer $customer, float $amount, string $paymentType): Session
    {
        $booking = $invoice->booking()->with('service')->first();
        $serviceName = $booking?->service?->service_name ?? 'Vehicle service';
        $currency = strtolower((string) config('services.stripe.currency', 'lkr'));
        $amountInMinorUnits = (int) round($amount * 100);
        $successUrl = rtrim((string) config('app.frontend_url'), '/') . '/payment/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = rtrim((string) config('app.frontend_url'), '/') . '/payment/cancel?booking_id=' . (int) ($booking?->booking_id ?? 0) . '&payment_type=' . $paymentType;

        if ($amountInMinorUnits < 1) {
            throw new InvalidArgumentException('The calculated payment amount must be greater than zero.');
        }

        if (! filter_var(str_replace('{CHECKOUT_SESSION_ID}', 'checkout-session', $successUrl), FILTER_VALIDATE_URL) || ! filter_var($cancelUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Stripe success and cancel URLs must be valid absolute URLs.');
        }

        return Session::create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'VSMS - ' . $serviceName,
                    ],
                    'unit_amount' => $amountInMinorUnits,
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'invoice_id' => (string) $invoice->invoice_id,
                'booking_id' => (string) ($booking?->booking_id ?? 0),
                'customer_id' => (string) $customer->customer_id,
                'payment_type' => $paymentType,
            ],
            'customer_email' => $customer->email,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function getCheckoutSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }

    public function paymentIntentStatus(?string $paymentIntentId): ?string
    {
        if (empty($paymentIntentId)) {
            return null;
        }

        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            return $paymentIntent->status ?? null;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
