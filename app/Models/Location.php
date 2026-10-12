<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use BelongsToCompany;

    protected $fillable = ['customer_id', 'name', 'address', 'contact_name', 'phone', 'notes'];
}
