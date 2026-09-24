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
            'pending_payment_requests' => Payment::where(function ($query) {
                $query->where(function ($advanceQuery) {
                    $advanceQuery->where('payment_type', 'advance')
                        ->where('payment_status', 'COMPLETED')
                        ->where('admin_status', 'PENDING_APPROVAL');
                })->orWhere(function ($remainingQuery) {
                    $remainingQuery->where('payment_type', 'remaining')
                        ->whereIn('payment_status', ['PAID', 'COMPLETED'])
                        ->where('admin_status', 'PENDING_REVIEW');
                });
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
