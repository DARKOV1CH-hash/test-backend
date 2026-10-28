<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postback extends Model
{
    protected $fillable = [
        'click_id',
        'event',
        'amount',
        'status',
        'partner_response',
        'attempts',
    ];

    protected $casts = [
        'amount' => 'float',
        'attempts' => 'integer',
    ];
}
