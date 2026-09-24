<?php

namespace App\Http\Controllers;

use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MechanicController extends Controller
{
	public function index()
	{
		return response()->json(['success' => true, 'data' => Mechanic::orderBy('full_name')->get()]);
	}

	public function store(Request $request)
	{
		$data = $request->validate([
			'full_name' => ['required', 'string', 'max:150'],
			'phone' => ['nullable', 'string', 'max:30'],
			'email' => ['required', 'email', 'max:150', 'unique:users,email'],
			'specialization' => ['nullable', 'string', 'max:150'],
			'password' => ['required', 'string', 'min:8'],
		]);
		$mechanic = DB::transaction(function () use ($data) {
			$user = User::create(['username' => $data['email'], 'email' => $data['email'], 'password_hash' => Hash::make($data['password']), 'role' => 'MECHANIC']);
			return Mechanic::create(['user_id' => $user->user_id, 'full_name' => $data['full_name'], 'phone' => $data['phone'] ?? null, 'email' => $data['email'], 'specialization' => $data['specialization'] ?? null]);
		});

		return response()->json(['success' => true, 'data' => $mechanic], 201);
	}

	public function show(Mechanic $mechanic)
	{
		return response()->json(['success' => true, 'data' => $mechanic]);
	}

	public function update(Request $request, Mechanic $mechanic)
	{
		$mechanic->update($request->validate([
			'full_name' => ['sometimes', 'string', 'max:150'],
			'phone' => ['nullable', 'string', 'max:30'],
			'email' => ['nullable', 'email', 'max:150'],
			'specialization' => ['nullable', 'string', 'max:150'],
		]));

		return response()->json(['success' => true, 'data' => $mechanic->fresh()]);
	}

	public function destroy(Mechanic $mechanic)
	{
		$mechanic->delete();
		return response()->json(['success' => true, 'message' => 'Mechanic deleted successfully']);
	}
}
