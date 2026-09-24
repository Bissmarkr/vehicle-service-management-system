<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
	public function index()
	{
		$query = Invoice::with('booking.customer')->latest('invoice_date');
		if (strtolower((string) request()->user()->role) === 'customer') $query->whereHas('booking', fn ($builder) => $builder->where('customer_id', Customer::where('user_id', request()->user()->user_id)->value('customer_id')));
		return response()->json(['success' => true, 'data' => $query->get()]);
	}

	public function store(Request $request)
	{
		abort_unless(strtolower((string) $request->user()->role) === 'admin', 403, 'Only administrators can generate invoices.');
		$data = $request->validate(['booking_id' => ['required', 'integer', 'exists:service_bookings,booking_id', 'unique:invoices,booking_id']]);
		$booking = Booking::with(['service', 'parts'])->findOrFail($data['booking_id']);
		$serviceCharge = (float) $booking->service->price;
		$partsCharge = (float) $booking->parts->sum(fn ($part) => $part->quantity * $part->unit_price);
		$invoice = Invoice::create(['booking_id' => $booking->booking_id, 'service_charge' => $serviceCharge, 'parts_charge' => $partsCharge, 'total_amount' => $serviceCharge + $partsCharge, 'invoice_status' => 'UNPAID', 'invoice_date' => now()->toDateString()]);

		return response()->json(['success' => true, 'data' => $invoice->load('booking.customer')], 201);
	}

	public function show(Invoice $invoice)
	{
		return response()->json(['success' => true, 'data' => $invoice->load(['booking.customer', 'payments'])]);
	}
}
