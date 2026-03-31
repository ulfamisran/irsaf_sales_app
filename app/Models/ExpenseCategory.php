<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'affects_profit_loss',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'affects_profit_loss' => 'boolean',
        ];
    }

    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class, 'expense_category_id');
    }
}

