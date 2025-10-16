<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    // Массовое заполнение разрешено
    protected $guarded = [];

    // Полезные касты
    protected $casts = [
        'is_active' => 'boolean',
        'cpc'       => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status'    => 'string',
    ];

    // Связи
    public function advertiser()
    {
        return $this->belongsTo(User::class, 'advertiser_id');
    }

    public function topics()
    {
        return $this->belongsToMany(Topic::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // скоупы для удобства запросов
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOfAdvertiser($q, int $userId)
    {
        return $q->where('advertiser_id', $userId);
    }

    // клики через подписки
    public function clicks()
    {
        return $this->hasManyThrough(
            \App\Models\Click::class,       // конечная модель
            \App\Models\Subscription::class, // промежуточная
            'offer_id',                     // FK на offers в subscriptions
            'subscription_id',              // FK на subscriptions в clicks
            'id',                           // локальный ключ offers
            'id'                            // локальный ключ subscriptions
        );
    }

    protected static function booted()
    {
        static::saving(function (Offer $offer) {
            // Если меняют is_active в старых формах — обновим status
            if ($offer->isDirty('is_active')) {
                $offer->status = $offer->is_active ? 'active' : ($offer->status === 'archived' ? 'archived' : 'paused');
            }
            // Если меняют status — синхронизируем is_active
            if ($offer->isDirty('status')) {
                $offer->is_active = ($offer->status === 'active');
            }
        });
    }
}
