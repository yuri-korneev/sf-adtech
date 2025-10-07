<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // ← добавить
use Illuminate\Database\Eloquent\Model;

class Click extends Model
{
    use HasFactory; 

    protected $guarded = [];

    protected $casts = [
        'clicked_at'    => 'datetime',
        'redirected_at' => 'datetime',
        'is_valid'      => 'boolean',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
