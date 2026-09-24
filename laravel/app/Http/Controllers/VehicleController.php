<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
	public function index(Request $request)
	{
		$query = Vehicle::with('customer')->orderBy('registration_number');
		if ($request->filled('search')) {
			$search = $request->string('search');
			$query->where(function ($builder) use ($search) {
				$builder->where('registration_number', 'like', "%{$search}%")
					->orWhere('make', 'like', "%{$search}%")
					->orWhere('model', 'like', "%{$search}%");
			});
		}
		if (strtolower((string) $request->user()->role) === 'customer') {
			$customer = $this->customerFor($request);
			if (! $customer) return $this->missingCustomerResponse();
			$query->where('customer_id', $customer->customer_id);
		}
		return response()->json(['success' => true, 'data' => $query->get()]);
	}

	public function store(Request $request)
	{
		$data = $request->validate([
			'customer_id' => ['sometimes', 'integer', 'exists:customers,customer_id'],
			'registration_number' => ['required', 'string', 'max:50', 'unique:vehicles,registration_number'],
			'make' => ['required', 'string', 'max:100'],
			'model' => ['required', 'string', 'max:100'],
			'manufacture_year' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
		]);
		if (strtolower((string) $request->user()->role) === 'customer') {
			$customer = $this->customerFor($request);
			if (! $customer) return $this->missingCustomerResponse();
			$data['customer_id'] = $customer->customer_id;
		}
		if (empty($data['customer_id'])) return response()->json(['success' => false, 'message' => 'An owning customer is required.'], 422);
		$vehicle = Vehicle::create($data);

		return response()->json(['success' => true, 'data' => $vehicle->load('customer')], 201);
	}

	public function show(Vehicle $vehicle)
	{
		$this->authorizeCustomerVehicle(request(), $vehicle);
		return response()->json(['success' => true, 'data' => $vehicle->load('customer')]);
	}

	public function update(Request $request, Vehicle $vehicle)
	{
		$this->authorizeCustomerVehicle($request, $vehicle);
		$vehicle->update($request->validate([
			'registration_number' => ['sometimes', 'string', 'max:50', 'unique:vehicles,registration_number,' . $vehicle->vehicle_id . ',vehicle_id'],
			'make' => ['sometimes', 'string', 'max:100'],
			'model' => ['sometimes', 'string', 'max:100'],
			'manufacture_year' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
		]));

		return response()->json(['success' => true, 'data' => $vehicle->fresh()->load('customer')]);
	}

	public function destroy(Vehicle $vehicle)
	{
		$this->authorizeCustomerVehicle(request(), $vehicle);
		$vehicle->delete();
		return response()->json(['success' => true, 'message' => 'Vehicle deleted successfully']);
	}

	private function authorizeCustomerVehicle(Request $request, Vehicle $vehicle): void
	{
		if (strtolower((string) $request->user()->role) !== 'customer') return;
		$customerId = $this->customerFor($request)?->customer_id;
		abort_unless((int) $vehicle->customer_id === (int) $customerId, 404);
	}

	private function customerFor(Request $request): ?Customer
	{
		return Customer::where('user_id', $request->user()->user_id)->first();
	}

	private function missingCustomerResponse()
	{
		return response()->json([
			'success' => false,
			'message' => 'Customer profile not found for the logged-in account. Please complete your customer profile.',
		], 422);
	}
}
