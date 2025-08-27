<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;

class AfricasTalkingService
{
    protected $sms;

    public function __construct()
    {
        $AT = new AfricasTalking(
            config('services.africastalking.username'),
            config('services.africastalking.api_key')
        );

        $this->sms = $AT->sms();
    }

    public function sendSms($to, $message)
    {
        return $this->sms->send([
            'to'      => $to,
            'message' => $message,
            'from'    => config('services.africastalking.from'), // optional
        ]);
    }
}
