<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceHistory;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $customer = Customer::where('user_id', $request->user()->user_id)->firstOrFail();
        $search = $request->string('search')->toString();
        $vehiclesQuery = $customer->vehicles()->orderBy('registration_number');
        if ($search !== '') $vehiclesQuery->where(fn ($query) => $query->where('registration_number', 'like', "%{$search}%")->orWhere('make', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%"));
        $vehicles = $vehiclesQuery->get();
        $bookingsQuery = Booking::with(['vehicle', 'service', 'mechanic', 'invoice.payments'])
            ->where('customer_id', $customer->customer_id)
            ->latest('preferred_date');
        if ($search !== '') $bookingsQuery->where(fn ($query) => $query->whereHas('vehicle', fn ($vehicle) => $vehicle->where('registration_number', 'like', "%{$search}%"))->orWhereHas('service', fn ($service) => $service->where('service_name', 'like', "%{$search}%")));
        $bookings = $bookingsQuery->get();
        $invoicesQuery = Invoice::with(['booking.vehicle', 'booking.service', 'payments'])
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $customer->customer_id))
            ->latest('invoice_date');
        $invoices = $invoicesQuery->get();
        $bookings->each(function ($booking) {
            $invoice = $booking->invoice;
            $payments = $invoice?->payments ?? collect();
            $advance = $payments->where('payment_type', 'advance')->sortByDesc('payment_id')->first();
            $remaining = $payments->where('payment_type', 'remaining')->sortByDesc('payment_id')->first();
            $successfulTotal = $payments->whereIn('payment_status', ['PAID', 'COMPLETED'])->sum('payment_amount');
            $advancePaid = $advance && in_array((string) $advance->payment_status, ['PAID', 'COMPLETED'], true);
            $remainingPaid = $remaining && in_array((string) $remaining->payment_status, ['PAID', 'COMPLETED'], true);
            $fullyPaid = $invoice && $successfulTotal >= (float) $invoice->total_amount;
            $paymentStatus = $fullyPaid ? 'PAID' : ($successfulTotal > 0 ? 'PARTIALLY_PAID' : strtoupper((string) ($advance?->payment_status ?: $booking->payment_status ?: 'PENDING')));
            $remainingAmount = $invoice ? max(0, round((float) $invoice->total_amount - (float) $successfulTotal, 2)) : 0;

            $booking->setAttribute('payment_status', $paymentStatus);
            $booking->setAttribute('admin_status', $advance?->admin_status);
            $booking->setAttribute('rejection_reason', $advance?->rejection_reason);
            $booking->setAttribute('advance_payment', $advance);
            $booking->setAttribute('remaining_payment', $remaining);
            $booking->setAttribute('remaining_amount', $remainingAmount);
            $booking->setAttribute('remaining_payment_status', $remainingPaid ? 'PAID' : strtoupper((string) ($invoice?->remaining_payment_status ?: ($remaining?->payment_status ?: 'PENDING'))));
            if ($invoice) {
                $invoice->setAttribute('payment_status', $paymentStatus);
                $invoice->setAttribute('admin_status', $advance?->admin_status);
                $invoice->setAttribute('rejection_reason', $advance?->rejection_reason);
                $invoice->setAttribute('advance_payment', $advance);
                $invoice->setAttribute('remaining_payment', $remaining);
                $invoice->setAttribute('remaining_amount', $remainingAmount);
                $invoice->setAttribute('remaining_payment_status', $remainingPaid ? 'PAID' : strtoupper((string) ($invoice->remaining_payment_status ?: ($remaining?->payment_status ?: 'PENDING'))));
            }
        });
        $invoices->each(function ($invoice) {
            $payments = $invoice->payments;
            $advance = $payments->where('payment_type', 'advance')->sortByDesc('payment_id')->first();
            $remaining = $payments->where('payment_type', 'remaining')->sortByDesc('payment_id')->first();
            $successfulTotal = $payments->whereIn('payment_status', ['PAID', 'COMPLETED'])->sum('payment_amount');
            $remainingAmount = max(0, round((float) $invoice->total_amount - (float) $successfulTotal, 2));
            $remainingPaid = $remaining && in_array((string) $remaining->payment_status, ['PAID', 'COMPLETED'], true);
            $invoice->setAttribute('payment_status', $successfulTotal >= (float) $invoice->total_amount ? 'PAID' : ($successfulTotal > 0 ? 'PARTIALLY_PAID' : 'PENDING'));
            $invoice->setAttribute('admin_status', $advance?->admin_status);
            $invoice->setAttribute('rejection_reason', $advance?->rejection_reason);
            $invoice->setAttribute('advance_payment', $advance);
            $invoice->setAttribute('remaining_payment', $remaining);
            $invoice->setAttribute('remaining_amount', $remainingAmount);
            $invoice->setAttribute('remaining_payment_status', $remainingPaid ? 'PAID' : strtoupper((string) ($invoice->remaining_payment_status ?: ($remaining?->payment_status ?: 'PENDING'))));
        });
        $history = ServiceHistory::with(['vehicle', 'booking.service', 'booking.mechanic'])
            ->whereHas('vehicle', fn ($query) => $query->where('customer_id', $customer->customer_id))
            ->latest('service_date')
            ->get();
        $activity = Booking::where('customer_id', $customer->customer_id)
            ->selectRaw("DATE_FORMAT(preferred_date, '%Y-%m') as month, COUNT(*) as bookings, SUM(booking_status = 'COMPLETED') as completed")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->customer_id,
                    'name' => $customer->full_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                ],
                'statistics' => [
                    'vehicles' => $vehicles->count(),
                    'active_bookings' => $bookings->whereIn('booking_status', ['PENDING', 'CONFIRMED', 'ASSIGNED', 'IN_PROGRESS'])->count(),
                    'completed_services' => $bookings->where('booking_status', 'COMPLETED')->count(),
                    'pending_payments' => $invoices->whereIn('invoice_status', ['UNPAID', 'PARTIALLY_PAID'])->sum('total_amount'),
                ],
                'vehicles' => $vehicles,
                'recent_bookings' => $bookings->take(6)->values(),
                'service_history' => $history->take(6)->values(),
                'invoices' => $invoices->take(6)->values(),
                'activity' => $activity,
                'services' => ServiceType::orderBy('service_name')->get(),
                'notifications' => $bookings->filter(fn ($booking) => in_array($booking->booking_status, ['CONFIRMED', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED'], true))->take(5)->values(),
            ],
        ]);
    }
}
