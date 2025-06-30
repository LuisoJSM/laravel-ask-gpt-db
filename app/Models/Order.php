<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;


    protected $fillable = [
        'user_id',
        'order_number',
        'total_amount',
        'status',
        'delivery_date'
    ];


    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

}
