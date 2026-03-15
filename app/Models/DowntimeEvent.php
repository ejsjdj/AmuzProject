<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DowntimeEvent extends Model
{
    protected $fillable = [
        'event_id',
        'started_at',
        'ended_at',
        'line_id',
        'work_order_id',
        'station_id',
        'reason_code',
        'duration_minutes',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];
}
