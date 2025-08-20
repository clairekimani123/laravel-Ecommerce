<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity', // Kept as per migration
    ];

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
