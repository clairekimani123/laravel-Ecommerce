<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    // Register new customer
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'                  => ['required','string','max:255'],
            'phone'                 => ['nullable','string','max:50', Rule::unique('customers','phone')],
            'email'                 => ['required','string','email','max:255', Rule::unique('customers','email')],
            'location'              => ['nullable','string','max:255'],
            'password'              => ['required','string','min:8','confirmed'], // needs password_confirmation
        ]);

        // Hash password
        $validated['password'] = Hash::make($validated['password']);

        // Create customer
        $customer = Customer::create($validated);

        // Create token for immediate login
        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Customer registered successfully',
            'customer'     => $customer,
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 201);
    }

    // Login by phone OR email + password
    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone'    => 'required_without:email|string',
            'email'    => 'required_without:phone|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::query()
            ->when($request->filled('phone'), fn ($q) => $q->where('phone', $validated['phone']))
            ->when($request->filled('email'), fn ($q) => $q->orWhere('email', $validated['email']))
            ->first();

        if (! $customer || ! Hash::check($validated['password'], $customer->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'customer'     => $customer,
            'message'      => 'Login successful',
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ]);
    }
}
