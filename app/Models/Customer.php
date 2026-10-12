<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'tax_id', 'contact_name', 'email', 'phone', 'address', 'notes'];
}
