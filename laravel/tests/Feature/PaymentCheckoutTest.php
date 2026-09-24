<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_checkout_session_for_their_invoice(): void
    {
        $user = User::create([
            'username' => 'customer@example.com',
            'email' => 'customer@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'CUSTOMER',
        ]);

        $customer = Customer::create([
            'user_id' => $user->user_id,
            'full_name' => 'Jane Customer',
            'email' => 'customer@example.com',
            'phone' => '+1234567890',
        ]);

        $vehicle = Vehicle::create([
            'customer_id' => $customer->customer_id,
            'registration_number' => 'ABC-123',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'manufacture_year' => 2022,
        ]);

        $service = ServiceType::create([
            'service_name' => 'Oil Change',
            'description' => 'Engine oil and filter replacement',
            'price' => 2500,
            'estimated_duration_minutes' => 60,
        ]);

        $booking = Booking::create([
            'customer_id' => $customer->customer_id,
            'vehicle_id' => $vehicle->vehicle_id,
            'service_type_id' => $service->service_type_id,
            'preferred_date' => now()->addDays(2)->toDateString(),
            'booking_status' => 'PENDING',
        ]);

        $invoice = Invoice::create([
            'booking_id' => $booking->booking_id,
            'service_charge' => 2500,
            'parts_charge' => 0,
            'total_amount' => 2500,
            'invoice_status' => 'UNPAID',
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/create-checkout-session', ['invoice_id' => $invoice->invoice_id]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Checkout session created successfully');
    }
}
