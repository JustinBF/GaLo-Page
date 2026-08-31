<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DuesPayment extends Model
{
    protected $fillable = [
        'member_id', 'week_start', 'amount', 'bank_movement_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * El lunes de la semana ISO a la que pertenece una fecha. Todas las
     * cuotas se guardan contra ese lunes para que la clave unica funcione.
     */
    public static function weekStart(?string $date = null): Carbon
    {
        return Carbon::parse($date ?? 'today')->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function bankMovement(): BelongsTo
    {
        return $this->belongsTo(BankMovement::class);
    }
}
