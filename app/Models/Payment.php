<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'amount',
        'payment_method',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getMaskedPaymentMethodAttribute(): string
    {
        $value = trim((string) $this->payment_method);
        $digitsOnly = preg_replace('/\D+/', '', $value) ?? '';

        if ($digitsOnly === '') {
            return str($value)->replace('_', ' ')->title()->value();
        }

        $lastFour = substr($digitsOnly, -4);
        $prefix = trim((string) preg_replace('/\d+/', '', $value));
        $prefix = trim((string) preg_replace('/\*+/', '', $prefix));

        if ($prefix === '') {
            return '****'.$lastFour;
        }

        return $prefix.' ****'.$lastFour;
    }
}
