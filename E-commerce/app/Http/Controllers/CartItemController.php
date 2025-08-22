<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
    }
    public function index()
    {
        return CartItem::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1',
            'cart_id' => 'required|exists:carts,id',
            'price' => 'required|numeric',
        ]);

        $item = CartItem::create($validated);

        $cart = $item->cart;
        $cart->total_quantity = $cart->items->sum('quantity');
        $cart->total_price = $cart->items->sum(fn($i) => $i->quantity * $i->price);
        $cart->save();

        return $item;
    }

    public function show(CartItem $cartItem)
    {
        return $cartItem;
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'integer|min:1',
            'price' => 'numeric',
        ]);

        $cartItem->update($validated);

        $cart = $cartItem->cart;
        $cart->total_quantity = $cart->items->sum('quantity');
        $cart->total_price = $cart->items->sum(fn($i) => $i->quantity * $i->price);
        $cart->save();

        return $cartItem;
    }

    public function destroy(CartItem $cartItem)
    {
        $cart = $cartItem->cart;
        $cartItem->delete();

        $cart->total_quantity = $cart->items->sum('quantity');
        $cart->total_price = $cart->items->sum(fn($i) => $i->quantity * $i->price);
        $cart->save();

        return response()->json(['message' => 'Deleted']);
    }
}
