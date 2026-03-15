<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Defect extends Model
{
    protected $fillable = [
        'defect_id',
        'timestamp',
        'shift_id',
        'line_id',
        'work_order_id',
        'station_id',
        'defect_code',
        'qty',
        'operator_id',
        'lot_no',
        'note',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}

