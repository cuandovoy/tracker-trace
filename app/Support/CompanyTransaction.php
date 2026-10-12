<?php

namespace App\Support;

use App\Models\Company;
use Closure;
use Illuminate\Support\Facades\DB;

class CompanyTransaction
{
    // Serialize availability changes within a company, including edits and archiving.
    // This lock is deliberately coarse; independent companies never block one another.
    public function run(Closure $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            Company::whereKey(app(Tenant::class)->id())->lockForUpdate()->firstOrFail();

            return $callback();
        }, 3);
    }
}
