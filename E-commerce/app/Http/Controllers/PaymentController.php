<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class PaymentController extends Controller
{
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

    ]);

    $validated['trans_date'] = now();
    $validated['trans_ref'] = uniqid('txn_');
    return Payment::create($validated);
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

public function initiateMpesa(Request $request)
{
    $validated = $request->validate([
        'customer_id' => 'required|exists:customers,id',
        'amount' => 'required|numeric',
        'phone' => 'required|string',
        'cart_id' => 'required|exists:carts,id',
    ]);

    $customer = \App\Models\Customer::find($validated['customer_id']);
    $cart = \App\Models\Cart::find($validated['cart_id']);

    $client = new Client();
    $consumerKey = env('MPESA_CONSUMER_KEY');
    $consumerSecret = env('MPESA_CONSUMER_SECRET');
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

    $tokenResponse = $client->request('GET',
        'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
        ['headers' => ['Authorization' => 'Basic ' . $credentials]]
    );
    $token = json_decode((string) $tokenResponse->getBody())->access_token;

    $shortcode = env('MPESA_SHORTCODE');
    $passkey = env('MPESA_PASSKEY');
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    $callbackUrl = env('MPESA_CALLBACK_URL');

    $payload = [
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $validated['amount'],
        'PartyA' => $validated['phone'],
        'PartyB' => $shortcode,
        'PhoneNumber' => $validated['phone'],
        'CallBackURL' => $callbackUrl,
        'AccountReference' => 'Order' . $cart->id,
        'TransactionDesc' => 'Payment for cart ' . $cart->id,
    ];

    try {
        $stkResponse = $client->request('POST',
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]
        );

        $responseData = json_decode((string) $stkResponse->getBody());

        if (isset($responseData->ResponseCode) && $responseData->ResponseCode == 0) {
            $payment = Payment::create([
                'customer_id' => $validated['customer_id'],
                'trans_date' => now(),
                'trans_ref' => $responseData->CheckoutRequestID,
                'amount' => $validated['amount'],
                'payload' => json_encode($responseData),
            ]);

            $cart->payment_id = $payment->id;
            $cart->save();

            return response()->json(['message' => 'STK Push initiated', 'data' => $responseData]);
        }

        return response()->json(['error' => 'Failed to initiate payment', 'data' => $responseData], 400);

    } catch (\GuzzleHttp\Exception\RequestException $e) {
        \Log::error("Mpesa Error: " . $e->getResponse()->getBody()->getContents());
        return response()->json([
            'message' => 'Mpesa STK push failed',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function mpesaCallback(Request $request)
{
    \Log::info('Mpesa Callback Data:', $request->all());

    $data = $request->all();

    if (isset($data['Body']['stkCallback'])) {
        $callback = $data['Body']['stkCallback'];

        $checkoutRequestID = $callback['CheckoutRequestID'];
        $resultCode = $callback['ResultCode'];
        $resultDesc = $callback['ResultDesc'];

        $payment = Payment::where('trans_ref', $checkoutRequestID)->first();

        if ($payment) {
            if ($resultCode == 0) {

                $amount = $callback['CallbackMetadata']['Item'][0]['Value'] ?? null;
                $mpesaReceipt = $callback['CallbackMetadata']['Item'][1]['Value'] ?? null;

                $payment->update([
                    'trans_date' => now(),
                    'payload' => json_encode($callback),
                    'trans_ref' => $mpesaReceipt,
                ]);

                if ($payment->cart) {
                    $payment->cart->status = 'paid';
                    $payment->cart->save();
                }
            } else {

                $payment->update([
                    'payload' => json_encode($callback),
                ]);
            }
        }
    }


    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Success'
    ]);
}

}
