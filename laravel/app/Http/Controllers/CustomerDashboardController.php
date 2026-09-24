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
        $bookingsQuery = Booking::with(['vehicle', 'service', 'mechanic', 'invoice'])
            ->where('customer_id', $customer->customer_id)
            ->latest('preferred_date');
        if ($search !== '') $bookingsQuery->where(fn ($query) => $query->whereHas('vehicle', fn ($vehicle) => $vehicle->where('registration_number', 'like', "%{$search}%"))->orWhereHas('service', fn ($service) => $service->where('service_name', 'like', "%{$search}%")));
        $bookings = $bookingsQuery->get();
        $invoicesQuery = Invoice::with(['booking.vehicle', 'booking.service'])
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $customer->customer_id))
            ->latest('invoice_date');
        $invoices = $invoicesQuery->get();
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
