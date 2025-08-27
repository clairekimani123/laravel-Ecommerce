<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Traits\Mpesa;

class PaymentController extends Controller
{
    use Mpesa;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        return Payment::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric',
            'phone' => 'required|string', // Added for M-Pesa STK push
        ]);

        $validated['trans_date'] = now();
        $validated['trans_ref'] = uniqid('txn_');
        $payment = Payment::create($validated);

        // Integrate M-Pesa STK push
        $token = $this->getAccessToken();
        if ($token) {
            $cart = $payment->cart; // Assuming a relationship exists
            $stkResponse = $this->sendStkPushRequest($validated, $cart, $token);
            if ($stkResponse && isset($stkResponse->CheckoutRequestID)) {
                $payment->update(['trans_ref' => $stkResponse->CheckoutRequestID]);
            }
        }

        return $payment;
    }

    public function show(Payment $payment)
    {
        return $payment;
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'trans_date' => 'date',
            'trans_ref' => 'string|unique:payments,trans_ref,' . $payment->id,
            'amount' => 'numeric',
            'payload' => 'nullable|json',
        ]);

        $payment->update($validated);
        return $payment;
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
