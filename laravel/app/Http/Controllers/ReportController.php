<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mechanic;
use App\Models\Payment;
use App\Models\Vehicle;

class ReportController extends Controller
{
    public function dashboard()
    {
        return response()->json(['success' => true, 'data' => [
            'customers' => Customer::count(),
            'vehicles' => Vehicle::count(),
            'mechanics' => Mechanic::count(),
            'bookings' => Booking::count(),
            'completed_services' => Booking::where('booking_status', 'COMPLETED')->count(),
            'invoices' => Invoice::count(),
            'revenue' => (float) Payment::where('payment_status', 'COMPLETED')->sum('payment_amount'),
        ]]);
    }

    public function revenue()
    {
        return response()->json(['success' => true, 'data' => [
            'total' => (float) Payment::where('payment_status', 'COMPLETED')->sum('payment_amount'),
            'paid_invoices' => Invoice::where('invoice_status', 'PAID')->count(),
            'unpaid_invoices' => Invoice::whereIn('invoice_status', ['UNPAID', 'PARTIALLY_PAID'])->count(),
        ]]);
    }

    public function services()
    {
        return response()->json(['success' => true, 'data' => Booking::selectRaw('booking_status, COUNT(*) as total')->groupBy('booking_status')->pluck('total', 'booking_status')]);
    }
}
