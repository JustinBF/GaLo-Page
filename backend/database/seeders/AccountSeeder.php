<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea las dos unicas cuentas del sistema. Las credenciales se leen del
 * entorno para que las reales nunca vivan en el repositorio.
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => env('PLAYER_USERNAME', 'galo')],
            [
                'name' => 'Jugadores GaLo',
                'role' => 'player',
                'password' => env('PLAYER_PASSWORD', 'cambiame'),
            ],
        );

        User::updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'galo_admin')],
            [
                'name' => 'Administración GaLo',
                'role' => 'admin',
                'password' => env('ADMIN_PASSWORD', 'cambiame-ya'),
            ],
        );

        // Indicador informativo de la barra superior. El Banco del Team se
        // gestiona fuera de la web; aquí solo se muestra la cifra.
        Setting::put('bank_balance', ['amount' => 0, 'currency' => 'pokeyenes']);
        Setting::put('team_name', ['value' => 'GaLo']);
    }
}
