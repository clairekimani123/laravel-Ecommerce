<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Vendor;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use AfricasTalking\SDK\AfricasTalking;


class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy', 'checkout']);
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

    public function checkout($cartId)
{
    $cart = Cart::with('items')->findOrFail($cartId);

    // Get customer & vendor
    $customer = Customer::find($cart->customer_id);
    $vendor   = Vendor::find($cart->vendor_id);

    if (!$customer) {
        return response()->json(['error' => 'No customer found for this cart'], 404);
    }
    if (!$vendor) {
        return response()->json(['error' => 'No vendor found for this cart'], 404);
    }

    // Init Africa’s Talking
    $username = env('AFRICASTALKING_USERNAME');
    $apiKey   = env('AFRICASTALKING_API_KEY');
    $from = env('AFRICASTALKING_FROM');

    \Log::info("username : " . $username);
    \Log::info("apiKey : " . $apiKey);
    \Log::info("from : " . $from);

    $AT       = new AfricasTalking($username, $apiKey);
    $sms      = $AT->sms();

    try {
        // SMS to customer
        $sms->send([
            'to'      => $customer->phone,
            'message' => "Hi {$customer->name}, your order #{$cart->id} has been placed successfully. Total: KES {$cart->total_price}.",
            'from'    => $from
        ]);

        // SMS to vendor
        $sms->send([
            'to'      => $vendor->phone,
            'message' => "Hi {$vendor->name}, you have a new order #{$cart->id}. Please prepare the items for delivery.",
            'from'    => env('AFRICASTALKING_FROM')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checkout complete. SMS sent to customer and vendor.'
        ]);

    } catch (\Exception $e) {
        \Log::error($e);
        return response()->json([
            'success' => false,
            'error'   => $e->getMessage()
        ], 500);
    }
}
}
