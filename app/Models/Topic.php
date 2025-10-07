<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $guarded = [];

    public function offers()
    {
        return $this->belongsToMany(Offer::class);
    }
}
