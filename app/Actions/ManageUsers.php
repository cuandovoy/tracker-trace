<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageUsers
{
    public function save(Company $company, array $data, ?User $member = null): User
    {
        return DB::transaction(function () use ($company, $data, $member) {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            if ($member) {
                $member->refresh();
                abort_unless($member->company_id === $company->id && ! $member->isSuper(), 404);
                if ($member->isAdmin() && $member->active && (! $data['active'] || $data['role'] !== 'admin') && ! $company->users()->where('role', 'admin')->where('active', true)->where('id', '!=', $member->id)->exists()) {
                    throw ValidationException::withMessages(['active' => 'La empresa debe conservar al menos un administrador activo.']);
                }
                $emailChanged = $member->email !== $data['email'];
                $member->update($data);
                if ($emailChanged) {
                    $member->update(['password' => Str::random(64)]);
                    DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
                }
                $member->forceFill(['remember_token' => null])->save();
                DB::table('sessions')->where('user_id', $member->id)->delete();
            } else {
                $member = User::create([...$data, 'company_id' => $company->id, 'password' => Str::random(64)]);
            }

            return $member;
        });
    }

    public function invite(User $member): bool
    {
        try {
            return Password::sendResetLink(['email' => $member->email]) === Password::ResetLinkSent;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
