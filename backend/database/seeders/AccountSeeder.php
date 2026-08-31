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

        // Solo la primera vez: el seeder corre en cada despliegue y estos
        // valores los edita el admin desde la web. Con put() se perderia el
        // nombre del team en cada deploy.
        Setting::firstOrCreate(
            ['key' => 'team_name'],
            ['value' => ['value' => 'GaLo']],
        );

        Setting::firstOrCreate(
            ['key' => 'dues_amount'],
            ['value' => ['amount' => 0]],
        );
    }
}
