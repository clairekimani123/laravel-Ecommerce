<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use AfricasTalking\SDK\AfricasTalking;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        return Cart::with('items')->get();
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'vendor_id' => 'nullable|exists:vendors,id',
        'customer_id' => 'nullable|exists:customers,id',
        'items' => 'required|array',
        'items.*.product_id' => 'required|integer|exists:products,id',
        'items.*.name' => 'required|string|max:255',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.price' => 'required|numeric|min:0',
    ]);

    $totalQuantity = collect($validated['items'])->sum('quantity');
    $totalPrice = collect($validated['items'])->sum(function ($item) {
        return $item['quantity'] * $item['price'];
    });

    $cart = Cart::create([
        'vendor_id' => $validated['vendor_id'] ?? null,
        'customer_id' => $validated['customer_id'] ?? null,
        'items' => $validated['items'],
        'total_quantity' => $totalQuantity,
        'total_price' => $totalPrice,
    ]);

    return response()->json($cart, 201);
}

    public function show(Cart $cart)
    {
        return $cart->load('items');
    }

    public function update(Request $request, Cart $cart)
{
    $validated = $request->validate([
        'vendor_id' => 'nullable|exists:vendors,id',
        'customer_id' => 'nullable|exists:customers,id',
        'payment_id' => 'nullable|exists:payments,id',
        'items' => 'nullable|array',
        'items.*.product_id' => 'required_with:items|integer|exists:products,id',
        'items.*.name' => 'required_with:items|string|max:255',
        'items.*.quantity' => 'required_with:items|integer|min:1',
        'items.*.price' => 'required_with:items|numeric|min:0',
    ]);

    if (isset($validated['items'])) {
        $cart->items = $validated['items'];
        $cart->total_quantity = collect($validated['items'])->sum('quantity');
        $cart->total_price = collect($validated['items'])->sum(
            fn($item) => $item['quantity'] * $item['price']
        );
    }

    $cart->update($validated);

    return response()->json($cart);
}


    public function destroy(Cart $cart)
    {
        $cart->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function checkout(Request $request, Cart $cart)
    {
        if (!$cart->payment) {
            return response()->json(['error' => 'No payment attached'], 400);
        }

        $AT = new AfricasTalking(env('AT_USERNAME'), env('AT_API_KEY'));
        $sms = $AT->sms();

        $message = "Order placed! Total: KES {$cart->total_price}. Ref: {$cart->payment->trans_ref}.";
        $response = $sms->send([
            'to' => $cart->customer->phone,
            'message' => $message,
            'from' => 'YourShortCode',
        ]);

        return response()->json(['message' => 'Checkout complete, SMS sent', 'sms_response' => $response]);
    }
}
