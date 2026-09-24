<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mechanic;
use App\Models\Payment;
use App\Models\Vehicle;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'customers' => Customer::count(),
            'vehicles' => Vehicle::count(),
            'mechanics' => Mechanic::count(),
            'pending_bookings' => Booking::where('booking_status', 'PENDING')->count(),
            'confirmed_bookings' => Booking::where('booking_status', 'CONFIRMED')->count(),
            'in_progress' => Booking::where('booking_status', 'IN_PROGRESS')->count(),
            'completed_services' => Booking::where('booking_status', 'COMPLETED')->count(),
            'invoices' => Invoice::count(),
            'payments' => Payment::sum('payment_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
