<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'customer_id',
        'trans_date',
        'trans_ref',
        'amount',
        'payload',
    ];

    protected $casts = [
        'payload'    => 'array',
        'trans_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
