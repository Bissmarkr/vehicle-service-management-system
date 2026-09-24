<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ServiceHistory;
use Illuminate\Http\Request;

class ServiceHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceHistory::with(['vehicle', 'booking']);
        if (strtolower((string) $request->user()->role) === 'customer') {
            $customerId = Customer::where('user_id', $request->user()->user_id)->value('customer_id');
            $query->whereHas('vehicle', fn ($builder) => $builder->where('customer_id', $customerId));
        }
        return response()->json(['success' => true, 'data' => $query->latest('service_date')->get()]);
    }

    public function forVehicle(string $vehicle)
    {
        return response()->json(['success' => true, 'data' => ServiceHistory::where('vehicle_id', $vehicle)->latest('service_date')->get()]);
    }
}
