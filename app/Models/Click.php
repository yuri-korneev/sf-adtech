<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Click extends Model
{
    use HasFactory;

    // У нас свои временные поля (clicked_at/redirected_at):
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'clicked_at'    => 'datetime',
        'redirected_at' => 'datetime',
        'is_valid'      => 'boolean',

        // Если колонок нет, вернётся null
        'adv_cost'      => 'decimal:4',
        'wm_payout'     => 'decimal:4',
    ];

    /** Базовая связь — всё остальное получаем через неё */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /* -------------------------
       "виртуальные" геттеры (через подписку)
       ------------------------- */

    // $click->offer
    public function getOfferAttribute()
    {
        return $this->subscription?->offer;
    }

    // $click->webmaster
    public function getWebmasterAttribute()
    {
        return $this->subscription?->webmaster;
    }

    // $click->advertiser
    public function getAdvertiserAttribute()
    {
        return $this->subscription?->offer?->advertiser ?? null;
    }

    /* -------------------------
       Скоупы для удобных выборок
       ------------------------- */

    // Click::between($from, $to)->get()
    public function scopeBetween($q, $from, $to)
    {
        return $q->whereBetween(\DB::raw('COALESCE(clicked_at, created_at)'), [$from, $to]);
    }

    // Click::valid()->get()
    public function scopeValid($q)
    {
        return $q->where('is_valid', 1);
    }

    // Click::refused()->get() — наши причины отказа
    public function scopeRefused($q)
    {
        return $q->where('is_valid', 0)
                 ->whereIn('invalid_reason', ['not_subscribed', 'inactive', 'offer_inactive']);
    }
}
