<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mesin extends Model
{
    protected $table = 'mesin';

    protected $fillable = ['uid', 'nama', 'nomor', 'status', 'started_at', 'timer_sec'];

    protected $casts = [
        'status'     => 'boolean',
        'started_at' => 'datetime',
        'timer_sec'  => 'integer',
    ];

    public function currentTimer(): int
    {
        if ($this->status && $this->started_at) {
            return (int) $this->started_at->diffInSeconds(now(), true);
        }

        return $this->timer_sec;
    }
}
