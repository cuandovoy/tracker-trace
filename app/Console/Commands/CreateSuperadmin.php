<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperadmin extends Command
{
    protected $signature = 'app:superadmin {email} {--name=Administrador}';

    protected $description = 'Crea el primer superadministrador con contraseña ingresada de forma segura.';

    public function handle(): int
    {
        $password = $this->secret('Contraseña (mínimo 12 caracteres, letras y números)');
        $data = ['email' => $this->argument('email'), 'name' => $this->option('name'), 'password' => $password];
        $validator = Validator::make($data, ['email' => 'required|email|unique:users', 'name' => 'required|string|max:160', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        User::create([...$data, 'role' => 'superadmin', 'active' => true]);
        $this->info('Superadministrador creado.');

        return self::SUCCESS;
    }
}
