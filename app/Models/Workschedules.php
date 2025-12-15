<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    protected $fillable = [
        'shift_id',
        'day',
        'start_time',
        'end_time',
        'is_working_day',
    ];

    protected $casts = [
        'is_working_day' => 'boolean',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
