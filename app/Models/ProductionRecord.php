<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRecord extends Model
{
    protected $fillable = [
        'record_id',
        'timestamp',
        'shift_id',
        'line_id',
        'work_order_id',
        'station_id',
        'input_qty',
        'good_qty',
        'scrap_qty',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}
