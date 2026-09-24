<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with('vehicles')->orderBy('full_name');
        if ($request->filled('search')) $query->where('full_name', 'like', '%' . $request->string('search') . '%')->orWhere('email', 'like', '%' . $request->string('search') . '%');
        if (strtolower((string) $request->user()->role) === 'customer') $query->where('user_id', $request->user()->user_id);
        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,user_id'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
        $customer = DB::transaction(function () use ($validated) {
            $password = $validated['password'] ?? null;
            unset($validated['password']);
            if (! empty($validated['user_id'])) return Customer::create($validated);
            $user = User::create(['username' => $validated['email'], 'email' => $validated['email'], 'password_hash' => Hash::make($password ?: bin2hex(random_bytes(16))), 'role' => 'CUSTOMER']);
            $validated['user_id'] = $user->user_id;
            return Customer::create($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer)
    {
        return response()->json([
            'success' => true,
            'data' => $customer->load('vehicles', 'bookings'),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:customers,email,' . $customer->id],
            'phone' => ['sometimes', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }
}
