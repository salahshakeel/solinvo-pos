<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
     protected $fillable = [
        'invoice_number', 'customer_name', 'customer_phone', 'total_amount',
        'tax_amount', 'discount_amount', 'payment_method', 'items'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'items' => 'array'
    ];
}
