<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'model', 'specifications', 'purchase_price', 'selling_price',
        'warranty_period', 'warranty_type', 'quantity', 'categories', 'brands',
        'description', 'weight', 'supplier_invoice_no'
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'quantity' => 'integer',
        'warranty_period' => 'integer',
    ];
}
