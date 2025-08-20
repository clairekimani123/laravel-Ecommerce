<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'customers';

    /**
     * Mass assignable attributes.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'location',
        'password',
    ];

    /**
     * Hidden attributes for arrays/JSON.
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casts.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // If your Laravel version supports it you can use this instead:
        // 'password' => 'hashed',
    ];

    /**
     * Mutator to ensure passwords are hashed when set.
     * (If you use 'password' => 'hashed' in $casts you can remove this.)
     */
    public function setPasswordAttribute($value)
    {
        if ($value === null) {
            $this->attributes['password'] = null;
            return;
        }

        // If already hashed (starts with $2y$) don't re-hash
        if (password_get_info($value)['algo'] !== 0) {
            $this->attributes['password'] = $value;
            return;
        }

        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * Relationships
     */
    public function carts()
    {
        return $this->hasMany(\App\Models\Cart::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }
}
