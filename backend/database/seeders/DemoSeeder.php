<?php

namespace Database\Seeders;

use App\Models\BankMovement;
use App\Models\Event;
use App\Models\Member;
use App\Models\Rank;
use App\Models\Reward;
use App\Models\User;
use App\Services\CoCalculator;
use App\Services\CreditService;
use App\Services\RedemptionService;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo para ver la interfaz en local.
 *
 * No se ejecuta en produccion: solo con `php artisan db:seed --class=DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $credits = app(CreditService::class);
        $redemptions = app(RedemptionService::class);
        $admin = User::where('role', 'admin')->first();

        $deposits = [
            ['Rodri',  8_000_000, 'Venta de shinies del torneo de julio'],
            ['Marta',  4_500_000, 'Aporte mensual al fondo del team'],
            ['Kevin',  3_000_000, 'Sobrante de la caza de agosto'],
            ['Leo',   -1_000_000, 'Pago de premios del Torneo Doble Battle'],
        ];

        foreach ($deposits as [$who, $amount, $why]) {
            BankMovement::create([
                'contributor_name' => $who,
                'amount' => $amount,
                'description' => $why,
                'created_by' => $admin?->id,
            ]);
        }

        $ranks = Rank::pluck('id', 'slug');

        $members = [
            ['nick' => 'Justin',   'rank' => 'gato-alpha',   'player' => true,  'organizer' => false, 'color' => [139, 92, 246]],
            ['nick' => 'Ana',      'rank' => 'gran-felino',  'player' => true,  'organizer' => false, 'color' => [56, 189, 248]],
            ['nick' => 'Mia',      'rank' => 'gatos-sombra', 'player' => true,  'organizer' => false, 'color' => [244, 114, 182]],
            ['nick' => 'Kuro',     'rank' => 'persian',      'player' => true,  'organizer' => false, 'color' => [251, 191, 36]],
            ['nick' => 'Nube',     'rank' => 'meowth',       'player' => true,  'organizer' => false, 'color' => [74, 222, 128]],
            ['nick' => 'Pixel',    'rank' => 'minino',       'player' => true,  'organizer' => false, 'color' => [248, 113, 113]],
            ['nick' => 'Leo',      'rank' => null,           'player' => false, 'organizer' => true,  'color' => [99, 102, 241]],
            ['nick' => 'Sombra',   'rank' => null,           'player' => true,  'organizer' => true,  'color' => [45, 212, 191]],
        ];

        $created = [];

        foreach ($members as $data) {
            $member = Member::updateOrCreate(
                ['nick' => $data['nick']],
                [
                    'rank_id' => $data['rank'] ? $ranks[$data['rank']] : null,
                    'is_player' => $data['player'],
                    'is_organizer' => $data['organizer'],
                ],
            );

            $member->update([
                'avatar_mime' => 'image/png',
                'avatar_data' => $this->avatar($data['nick'], $data['color']),
            ]);

            $created[$data['nick']] = $member->fresh();
        }

        $events = [
            [
                'name' => 'Torneo Doble Battle',
                'type' => 'torneo',
                'held_at' => now()->subDays(3)->toDateString(),
                'difficulty' => 'alta',
                'prize_value' => 1_500_000,
                'organizer' => 'Leo',
                'podium' => [['Justin', 1, 180], ['Ana', 2, 100], ['Mia', 3, 60]],
            ],
            [
                'name' => 'Caza del Shiny',
                'type' => 'caza',
                'held_at' => now()->subDays(10)->toDateString(),
                'difficulty' => 'extrema',
                'prize_value' => 3_000_000,
                'organizer' => 'Sombra',
                'podium' => [['Ana', 1, 250], ['Kuro', 2, 140], ['Justin', 3, 90]],
            ],
            [
                'name' => 'Sorteo de bienvenida',
                'type' => 'sorteo',
                'held_at' => now()->subDays(18)->toDateString(),
                'difficulty' => 'baja',
                'prize_value' => 500_000,
                'organizer' => 'Leo',
                'podium' => [['Nube', 1, 50], ['Pixel', 2, 30]],
            ],
            [
                'name' => 'Liga interna de agosto',
                'type' => 'torneo',
                'held_at' => now()->subDays(25)->toDateString(),
                'difficulty' => 'media',
                'prize_value' => 800_000,
                'organizer' => 'Sombra',
                'podium' => [['Mia', 1, 120], ['Justin', 2, 70], ['Nube', 3, 40]],
            ],
        ];

        foreach ($events as $data) {
            $organizer = $created[$data['organizer']];

            $event = Event::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'held_at' => $data['held_at'],
                'difficulty' => $data['difficulty'],
                'prize_value' => $data['prize_value'],
                'organizer_id' => $organizer->id,
                'co_awarded' => app(CoCalculator::class)->suggest($data['prize_value']),
            ]);

            foreach ($data['podium'] as [$nick, $position, $ce]) {
                $member = $created[$nick];

                $event->results()->create([
                    'member_id' => $member->id,
                    'position' => $position,
                    'ce_awarded' => $ce,
                ]);

                $credits->post(
                    member: $member,
                    currency: 'CE',
                    amount: $ce,
                    reason: 'event_win',
                    userId: $admin?->id,
                    note: "Top {$position} en {$event->name}",
                    eventId: $event->id,
                );
            }

            if ($event->co_awarded > 0) {
                $credits->post(
                    member: $organizer,
                    currency: 'CO',
                    amount: $event->co_awarded,
                    reason: 'event_organized',
                    userId: $admin?->id,
                    note: "Organizó {$event->name}",
                    eventId: $event->id,
                );
            }
        }

        $rewards = [
            ['name' => 'Ditto 6IV',          'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',   'stock' => 3,    'featured' => true,  'desc' => 'Ideal para criar competitivos.', 'color' => [167, 139, 250]],
            ['name' => 'Master Ball',        'currency' => 'CE', 'cost' => 500, 'category' => 'objeto',    'stock' => 1,    'featured' => false, 'desc' => 'Captura garantizada.',           'color' => [244, 114, 182]],
            ['name' => 'Vitaminas x10',      'currency' => 'CE', 'cost' => 120, 'category' => 'objeto',    'stock' => null, 'featured' => false, 'desc' => 'Pack de EV training.',           'color' => [56, 189, 248]],
            ['name' => 'Sombrero raro',      'currency' => 'CE', 'cost' => 250, 'category' => 'cosmetico', 'stock' => 5,    'featured' => false, 'desc' => 'Cosmético exclusivo del team.',  'color' => [74, 222, 128]],
            ['name' => 'Ascenso a Persian',      'currency' => 'CO', 'cost' => 200, 'category' => 'ascenso_rango', 'rank' => 'persian',      'stock' => null, 'featured' => false, 'desc' => null, 'color' => [251, 146, 60]],
            ['name' => 'Ascenso a Gran Felino',  'currency' => 'CO', 'cost' => 500, 'category' => 'ascenso_rango', 'rank' => 'gran-felino',  'stock' => null, 'featured' => true,  'desc' => null, 'color' => [6, 182, 212]],
            ['name' => 'Pack de organizador',    'currency' => 'CO', 'cost' => 150, 'category' => 'especial',      'stock' => null, 'featured' => false, 'desc' => 'Recursos para montar eventos.', 'color' => [251, 191, 36]],
        ];

        $rewardModels = [];

        foreach ($rewards as $data) {
            $reward = Reward::create([
                'name' => $data['name'],
                'description' => $data['desc'] ?? null,
                'currency' => $data['currency'],
                'cost' => $data['cost'],
                'category' => $data['category'],
                'grants_rank_id' => isset($data['rank']) ? $ranks[$data['rank']] : null,
                'stock' => $data['stock'],
                'is_featured' => $data['featured'],
            ]);

            $reward->update([
                'image_mime' => 'image/png',
                'image_data' => $this->avatar($data['name'], $data['color']),
            ]);

            $rewardModels[$data['name']] = $reward->fresh();
        }

        // Un par de canjes para que la tienda y los perfiles no salgan vacios.
        $redemptions->redeem(
            member: $created['Justin']->fresh(),
            reward: $rewardModels['Vitaminas x10'],
            userId: $admin?->id ?? 1,
            note: 'Entregado por Discord',
        );

        $redemptions->redeem(
            member: $created['Ana']->fresh(),
            reward: $rewardModels['Ditto 6IV'],
            userId: $admin?->id ?? 1,
        );

        // Ajuste manual, para que el historial muestre también este motivo.
        $credits->post(
            member: $created['Leo']->fresh(),
            currency: 'CO',
            amount: 120,
            reason: 'manual_adjust',
            userId: $admin?->id,
            note: 'Bonus por ayudar en la organización',
        );

        $redemptions->redeem(
            member: $created['Leo']->fresh(),
            reward: $rewardModels['Ascenso a Persian'],
            userId: $admin?->id ?? 1,
        );
    }

    /**
     * Genera un PNG de 128x128 con la inicial del nombre sobre un degradado.
     * Solo para la demo: en produccion los avatares los sube el admin.
     *
     * @param  array{int, int, int}  $rgb
     */
    private function avatar(string $label, array $rgb): string
    {
        $size = 128;
        $image = imagecreatetruecolor($size, $size);

        [$r, $g, $b] = $rgb;

        for ($y = 0; $y < $size; $y++) {
            $factor = 1 - ($y / $size) * 0.45;
            $line = imagecolorallocate(
                $image,
                (int) ($r * $factor),
                (int) ($g * $factor),
                (int) ($b * $factor),
            );
            imageline($image, 0, $y, $size, $y, $line);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $initial = mb_strtoupper(mb_substr($label, 0, 1));
        imagestring($image, 5, (int) ($size / 2) - 4, (int) ($size / 2) - 8, $initial, $white);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return base64_encode($binary);
    }
}
