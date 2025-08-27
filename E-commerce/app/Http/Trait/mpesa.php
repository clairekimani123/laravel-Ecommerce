<?php

namespace App\Traits;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

trait Mpesa
{
    protected function getAccessToken()
    {
        $client = new Client();
        $consumerKey = env('MPESA_CONSUMER_KEY');
        $consumerSecret = env('MPESA_CONSUMER_SECRET');
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        try {
            $tokenResponse = $client->request('GET',
                'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
                ['headers' => ['Authorization' => 'Basic ' . $credentials]]
            );

            $token = json_decode((string) $tokenResponse->getBody())->access_token;
            Log::info("Mpesa Token: " . $token);
            return $token;

        } catch (\Exception $e) {
            Log::error("Mpesa Token Error: " . $e->getMessage());
            return null;
        }
    }

    protected function sendStkPushRequest($validated, $cart, $token)
    {
        $client = new Client();
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

            return json_decode((string) $stkResponse->getBody());

        } catch (\Exception $e) {
            Log::error("Mpesa STK Error: " . $e->getMessage());
            return null;
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
