<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    protected $fillable = [
        'work_order_id',
        'line_id',
        'product_id',
        'routing_id',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'target_qty',
        'status',
        'priority',
    ];

    protected $casts = [
        'planned_start_at' => 'datetime',
        'planned_end_at'   => 'datetime',
        'actual_start_at'  => 'datetime',
        'actual_end_at'    => 'datetime',
    ];
}
