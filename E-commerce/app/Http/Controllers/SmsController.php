<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AfricasTalkingService;

class SmsController extends Controller
{

    protected $sms;

    public function __construct(AfricasTalkingService $sms)
    {
        $this->sms = $sms;

    }

    public function sendsms(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $response = $this->sms->sendSms($request->phone, $request->message);

        return response()->json([
            'status' => 'success',
            'response' => $response
        ]);

        
    }
}
