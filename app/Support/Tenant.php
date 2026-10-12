<?php

namespace App\Support;

use Closure;

class Tenant
{
    private ?int $companyId = null;

    public function id(): ?int
    {
        return auth()->user()?->company_id ?? $this->companyId;
    }

    public function run(int $id, Closure $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = $id;
        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
