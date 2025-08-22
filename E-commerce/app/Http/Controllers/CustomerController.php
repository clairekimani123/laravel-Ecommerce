<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        return Customer::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string|unique:customers,phone',
            'location' => 'nullable|string',
            'email' => 'nullable|email|unique:customers,email',
            'password' => 'required|string|min:8|confirmed', // 'confirmed' requires password_confirmation
        ]);

        $validated['password'] = Hash::make($validated['password']);

        return Customer::create($validated);
    }

    public function show(Customer $customer)
    {
        return $customer;
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'string',
            'phone' => 'string|unique:customers,phone,' . $customer->id,
            'location' => 'nullable|string',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'password' => 'sometimes|string|min:8|confirmed', // Optional, requires password_confirmation
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $customer->update($validated);
        return $customer;
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
