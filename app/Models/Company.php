<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $attributes = ['active' => true, 'timezone' => 'America/Montevideo'];

    protected $fillable = ['name', 'tax_id', 'address', 'phone', 'timezone', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'demo_loaded_at' => 'datetime'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
