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
        $this->middleware('auth:sanctum');
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
        ]);

        return Cart::create($validated);
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
        ]);

        $cart->total_quantity = $cart->items->sum('quantity');
        $cart->total_price = $cart->items->sum(fn($item) => $item->quantity * $item->price);
        $cart->update($validated);
        return $cart;
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
