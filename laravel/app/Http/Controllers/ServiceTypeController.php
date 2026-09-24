<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
	public function index()
	{
		return response()->json(['success' => true, 'data' => ServiceType::orderBy('service_name')->get()]);
	}

	public function store(Request $request)
	{
		$service = ServiceType::create($request->validate([
			'service_name' => ['required', 'string', 'max:150', 'unique:service_types,service_name'],
			'description' => ['nullable', 'string'],
			'price' => ['required', 'numeric', 'min:0'],
			'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
		]));

		return response()->json(['success' => true, 'data' => $service], 201);
	}

	public function show(ServiceType $service)
	{
		return response()->json(['success' => true, 'data' => $service]);
	}

	public function update(Request $request, ServiceType $service)
	{
		$service->update($request->validate([
			'service_name' => ['sometimes', 'string', 'max:150', 'unique:service_types,service_name,' . $service->service_type_id . ',service_type_id'],
			'description' => ['nullable', 'string'],
			'price' => ['sometimes', 'numeric', 'min:0'],
			'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
		]));

		return response()->json(['success' => true, 'data' => $service->fresh()]);
	}

	public function destroy(ServiceType $service)
	{
		$service->delete();
		return response()->json(['success' => true, 'message' => 'Service deleted successfully']);
	}
}
