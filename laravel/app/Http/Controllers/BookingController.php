<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Mechanic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['customer', 'vehicle', 'service', 'mechanic', 'parts.part'])->latest('preferred_date');
        if ($request->filled('search')) $query->whereHas('customer', fn ($q) => $q->where('full_name', 'like', '%' . $request->string('search') . '%'))->orWhereHas('vehicle', fn ($q) => $q->where('registration_number', 'like', '%' . $request->string('search') . '%'));
        $role = strtolower((string) $request->user()->role);
        if ($role === 'customer') $query->where('customer_id', Customer::where('user_id', $request->user()->user_id)->value('customer_id'));
        if ($role === 'mechanic') $query->where('mechanic_id', Mechanic::where('user_id', $request->user()->user_id)->value('mechanic_id'));
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,customer_id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,vehicle_id'],
            'service_type_id' => ['required', 'integer', 'exists:service_types,service_type_id'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'customer_notes' => ['nullable', 'string'],
        ]);

        if (strtolower((string) $request->user()->role) === 'customer') {
            $data['customer_id'] = Customer::where('user_id', $request->user()->user_id)->value('customer_id');
        }

        if (empty($data['customer_id'])) abort(422, 'An owning customer is required.');

        $vehicleCustomer = \App\Models\Vehicle::where('vehicle_id', $data['vehicle_id'])->value('customer_id');
        if ((int) $vehicleCustomer !== (int) $data['customer_id']) abort(422, 'The vehicle does not belong to the selected customer.');

        $service = \App\Models\ServiceType::findOrFail($data['service_type_id']);
        [$booking, $invoice] = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $service) {
            $booking = Booking::create([
                'customer_id' => $data['customer_id'],
                'vehicle_id' => $data['vehicle_id'],
                'service_type_id' => $data['service_type_id'],
                'preferred_date' => $data['preferred_date'],
                'preferred_time' => $data['preferred_time'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'booking_status' => 'PENDING',
                'payment_status' => 'PENDING',
            ]);

            $totalAmount = (float) $service->price;
            $advancePercentage = (float) config('services.stripe.advance_percentage', 30);
            $advanceAmount = round($totalAmount * $advancePercentage / 100, 2);
            $invoice = \App\Models\Invoice::firstOrCreate(
                ['booking_id' => $booking->booking_id],
                [
                    'customer_id' => $data['customer_id'],
                    'service_charge' => (float) $service->price,
                    'parts_charge' => 0,
                    'total_amount' => $totalAmount,
                    'advance_percentage' => $advancePercentage,
                    'advance_amount' => $advanceAmount,
                    'remaining_amount' => round($totalAmount - $advanceAmount, 2),
                    'advance_payment_status' => 'PENDING',
                    'remaining_payment_status' => 'PENDING',
                    'invoice_status' => 'UNPAID',
                    'invoice_date' => now()->toDateString(),
                ]
            );

            $invoice->update([
                'customer_id' => $data['customer_id'],
                'service_charge' => (float) $service->price,
                'parts_charge' => 0,
                'total_amount' => $totalAmount,
                'advance_percentage' => $advancePercentage,
                'advance_amount' => $advanceAmount,
                'remaining_amount' => round($totalAmount - $advanceAmount, 2),
                'advance_payment_status' => 'PENDING',
                'remaining_payment_status' => 'PENDING',
                'invoice_status' => 'UNPAID',
            ]);

            $customer = Customer::findOrFail($data['customer_id']);
            \App\Models\Payment::firstOrCreate(
                ['invoice_id' => $invoice->invoice_id, 'payment_type' => 'advance'],
                [
                    'booking_id' => $booking->booking_id,
                    'customer_id' => $customer->customer_id,
                    'payment_amount' => $invoice->advance_amount,
                    'payment_method' => 'CARD',
                    'payment_date' => now(),
                    'payment_status' => 'PENDING',
                    'status' => 'PENDING',
                    'currency' => strtoupper((string) config('services.stripe.currency', 'LKR')),
                    'stripe_checkout_session_id' => null,
                    'stripe_payment_intent_id' => null,
                    'stripe_event_id' => null,
                    'paid_at' => null,
                ]
            );

            $invoice->booking()->update(['payment_status' => 'PENDING']);

            $booking->forceFill(['payment_status' => 'PENDING'])->save();

            return [$booking->load(['customer', 'vehicle', 'service']), $invoice->fresh()];
        });

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully. Review the booking and pay the advance when ready.',
            'booking' => $booking,
            'invoice' => $invoice,
        ], 201);
    }

    public function show(Booking $booking)
    {
        $customer = Customer::where('user_id', request()->user()->user_id)->first();
        if (strtolower((string) request()->user()->role) === 'customer' && (int) $booking->customer_id !== (int) $customer?->customer_id) abort(403, 'You are not authorized to view this booking.');
        return response()->json(['success' => true, 'data' => $booking->load(['customer', 'vehicle', 'service', 'invoice'])]);
    }

    public function update(Request $request, Booking $booking)
    {
        $booking->update($request->validate([
            'preferred_date' => ['sometimes', 'date'],
            'customer_notes' => ['nullable', 'string'],
            'booking_status' => ['sometimes', 'in:PENDING,CONFIRMED,ASSIGNED,IN_PROGRESS,COMPLETED,CANCELLED'],
        ]));

        return response()->json(['success' => true, 'data' => $booking->fresh()->load(['customer', 'vehicle', 'service'])]);
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();
        return response()->json(['success' => true, 'message' => 'Booking deleted successfully']);
    }

    public function assignMechanic(Request $request, Booking $booking)
    {
        abort_unless(strtolower((string) $request->user()->role) === 'admin', 403, 'Only administrators can assign mechanics.');
        $data = $request->validate(['mechanic_id' => ['required', 'integer', 'exists:mechanics,mechanic_id']]);
        $booking->update($data + ['booking_status' => 'ASSIGNED']);
        return response()->json(['success' => true, 'message' => 'Mechanic assigned successfully', 'data' => $booking->fresh()->load(['customer', 'vehicle', 'service', 'mechanic'])]);
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $booking->update($request->validate(['booking_status' => ['required', 'in:PENDING,CONFIRMED,ASSIGNED,IN_PROGRESS,COMPLETED,CANCELLED']]));
        return response()->json(['success' => true, 'data' => $booking->fresh()]);
    }

    public function progress(Request $request, Booking $booking)
    {
        $mechanic = Mechanic::where('user_id', $request->user()->user_id)->firstOrFail();
        abort_unless((int) $booking->mechanic_id === (int) $mechanic->mechanic_id, 403, 'Only the assigned mechanic can update this job.');
        $data = $request->validate([
            'booking_status' => ['required', 'in:ASSIGNED,IN_PROGRESS,COMPLETED'],
            'customer_notes' => ['nullable', 'string'],
            'parts' => ['array'],
            'parts.*.part_id' => ['required', 'exists:spare_parts,part_id'],
            'parts.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($booking, $data) {
            foreach ($data['parts'] ?? [] as $used) {
                $part = \App\Models\SparePart::lockForUpdate()->findOrFail($used['part_id']);
                abort_if($part->stock_quantity < $used['quantity'], 422, 'Insufficient stock.');
                $part->decrement('stock_quantity', $used['quantity']);
                $existing = \App\Models\BookingPart::where(['booking_id' => $booking->booking_id, 'part_id' => $part->part_id])->first();
                if ($existing) $existing->increment('quantity', $used['quantity']);
                else \App\Models\BookingPart::create(['booking_id' => $booking->booking_id, 'part_id' => $part->part_id, 'quantity' => $used['quantity'], 'unit_price' => $part->unit_price]);
            }
            $booking->update(['booking_status' => $data['booking_status'], 'customer_notes' => $data['customer_notes'] ?? $booking->customer_notes]);
        });
        return response()->json(['success' => true, 'data' => $booking->fresh()->load(['customer', 'vehicle', 'service', 'mechanic', 'parts.part'])]);
    }
}
