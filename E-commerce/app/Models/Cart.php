<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'vendor_id',
        'customer_id',
        'total_quantity',
        'total_price',
        'payment_id',
        'items',
    ];

     protected $casts = [
        'items' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
