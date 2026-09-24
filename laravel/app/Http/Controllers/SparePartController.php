<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use Illuminate\Http\Request;

class SparePartController extends Controller
{
	public function index() { return response()->json(['success' => true, 'data' => SparePart::orderBy('part_name')->get()]); }

	public function store(Request $request)
	{
		$part = SparePart::create($request->validate([
			'part_name' => ['required', 'string', 'max:150'],
			'stock_quantity' => ['required', 'integer', 'min:0'],
			'unit_price' => ['required', 'numeric', 'min:0'],
		]));
		return response()->json(['success' => true, 'data' => $part], 201);
	}

	public function show(SparePart $sparePart) { return response()->json(['success' => true, 'data' => $sparePart]); }

	public function update(Request $request, SparePart $sparePart)
	{
		$sparePart->update($request->validate([
			'part_name' => ['sometimes', 'string', 'max:150'],
			'stock_quantity' => ['sometimes', 'integer', 'min:0'],
			'unit_price' => ['sometimes', 'numeric', 'min:0'],
		]));
		return response()->json(['success' => true, 'data' => $sparePart->fresh()]);
	}

	public function destroy(SparePart $sparePart)
	{
		$sparePart->delete();
		return response()->json(['success' => true, 'message' => 'Spare part deleted successfully']);
	}
}
