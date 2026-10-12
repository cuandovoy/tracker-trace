<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FleetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->company_id && in_array($user->role, ['admin', 'operator']) && $user->company?->active;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user) && $user->company_id === $model->company_id;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }

    public function archive(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }
}
