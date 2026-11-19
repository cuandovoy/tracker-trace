<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class TripExpense extends Model
{
    use BelongsToCompany;

    public const CATEGORIES = ['combustible' => 'Combustible', 'peajes' => 'Peajes', 'otros' => 'Otros'];

    public const LABELS = [...self::CATEGORIES, 'viaticos' => 'Viáticos'];

    protected $fillable = ['date', 'category', 'description', 'amount', 'currency'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
