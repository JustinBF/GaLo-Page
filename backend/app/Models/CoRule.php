<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoRule extends Model
{
    protected $fillable = [
        'label', 'min_prize_value', 'max_prize_value', 'co_amount', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_prize_value' => 'integer',
            'max_prize_value' => 'integer',
            'co_amount' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
