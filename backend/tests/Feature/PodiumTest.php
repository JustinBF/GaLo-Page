<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Rank;
use App\Services\CreditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PodiumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function adminToken(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/login', [
            'username' => 'galo_admin',
            'password' => 'admin-secreto-cambiame',
        ])->json('token');
    }

    private function playerToken(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/login', [
            'username' => 'galo',
            'password' => 'galo2026',
        ])->json('token');
    }

    private function player(string $nick, int $ce): Member
    {
        $member = Member::create(['nick' => $nick, 'is_player' => true]);

        app(CreditService::class)->post(
            member: $member,
            currency: 'CE',
            amount: $ce,
            reason: 'event_win',
        );

        return $member->fresh();
    }

    // ---------- Podio ----------

    public function test_ordena_a_los_jugadores_por_ce(): void
    {
        $this->player('Bronce', 50);
        $this->player('Oro', 300);
        $this->player('Plata', 150);

        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players')
            ->assertOk()
            ->assertJsonCount(3, 'podium')
            ->assertJsonPath('podium.0.nick', 'Oro')
            ->assertJsonPath('podium.0.position', 1)
            ->assertJsonPath('podium.0.score', 300)
            ->assertJsonPath('podium.1.nick', 'Plata')
            ->assertJsonPath('podium.2.nick', 'Bronce');
    }

    public function test_solo_devuelve_tres(): void
    {
        foreach (['A' => 500, 'B' => 400, 'C' => 300, 'D' => 200] as $nick => $ce) {
            $this->player($nick, $ce);
        }

        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players')
            ->assertOk()
            ->assertJsonCount(3, 'podium');
    }

    public function test_excluye_a_quien_no_tiene_creditos(): void
    {
        Member::create(['nick' => 'SinCreditos', 'is_player' => true]);
        $this->player('ConCreditos', 100);

        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players')
            ->assertOk()
            ->assertJsonCount(1, 'podium')
            ->assertJsonPath('podium.0.nick', 'ConCreditos');
    }

    public function test_excluye_a_los_inactivos(): void
    {
        $fantasma = $this->player('Fantasma', 900);
        $fantasma->update(['is_active' => false]);
        $this->player('Activo', 100);

        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players')
            ->assertOk()
            ->assertJsonCount(1, 'podium')
            ->assertJsonPath('podium.0.nick', 'Activo');
    }

    public function test_el_podio_de_organizadores_usa_co(): void
    {
        $organizer = Member::create(['nick' => 'Leo', 'is_organizer' => true]);
        app(CreditService::class)->post(
            member: $organizer,
            currency: 'CO',
            amount: 250,
            reason: 'event_organized',
        );

        // Un jugador con mucho CE no debe colarse en el podio de CO.
        $this->player('Justin', 9999);

        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=organizers')
            ->assertOk()
            ->assertJsonPath('currency', 'CO')
            ->assertJsonCount(1, 'podium')
            ->assertJsonPath('podium.0.nick', 'Leo')
            ->assertJsonPath('podium.0.score', 250);
    }

    public function test_devuelve_un_podio_vacio_sin_datos(): void
    {
        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players')
            ->assertOk()
            ->assertJsonCount(0, 'podium');
    }

    public function test_el_podio_mensual_ignora_lo_antiguo(): void
    {
        $viejo = $this->player('Veterano', 0);
        $nuevo = $this->player('Novato', 0);

        $credits = app(CreditService::class);

        $credits->post(member: $viejo, currency: 'CE', amount: 900, reason: 'event_win');
        // Lo movemos fuera de la ventana del mes actual.
        $viejo->transactions()->update(['created_at' => Carbon::now()->subMonths(3)]);

        $credits->post(member: $nuevo, currency: 'CE', amount: 100, reason: 'event_win');

        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players&period=month')
            ->assertOk()
            ->assertJsonCount(1, 'podium')
            ->assertJsonPath('podium.0.nick', 'Novato')
            ->assertJsonPath('podium.0.score', 100);
    }

    public function test_los_gastos_de_tienda_no_bajan_el_podio_del_mes(): void
    {
        $member = $this->player('Justin', 0);
        $credits = app(CreditService::class);

        $credits->post(member: $member, currency: 'CE', amount: 500, reason: 'event_win');
        $credits->post(member: $member, currency: 'CE', amount: -400, reason: 'redemption');

        // El podio premia lo conseguido, no lo que queda sin gastar.
        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players&period=month')
            ->assertOk()
            ->assertJsonPath('podium.0.score', 500);
    }

    public function test_el_podio_incluye_el_rango_y_el_avatar(): void
    {
        $rank = Rank::where('slug', 'persian')->first();
        $member = $this->player('Justin', 100);
        $member->update(['rank_id' => $rank->id]);

        $this->as($this->adminToken())
            ->post("/api/admin/members/{$member->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('a.png', 100, 100),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=players')
            ->assertOk()
            ->assertJsonPath('podium.0.rank.name', 'Persian')
            ->assertJsonPath('podium.0.has_avatar', true);
    }

    public function test_rechaza_un_scope_invalido(): void
    {
        $this->as($this->playerToken())
            ->getJson('/api/podium?scope=inventado')
            ->assertStatus(422);
    }

    public function test_el_podio_exige_estar_autenticado(): void
    {
        $this->getJson('/api/podium?scope=players')->assertStatus(401);
    }

    // ---------- Iconos de rango ----------

    public function test_el_admin_sube_y_sirve_el_icono_de_un_rango(): void
    {
        $rank = Rank::where('slug', 'gato-alpha')->first();

        $this->as($this->adminToken())
            ->post("/api/admin/ranks/{$rank->id}/icon", [
                'icon' => UploadedFile::fake()->image('alpha.png', 64, 64),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('has_icon', true);

        // Sin token, como hace <img src>.
        $this->get("/api/ranks/{$rank->id}/icon")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->as($this->playerToken())
            ->getJson('/api/ranks')
            ->assertOk()
            ->assertJsonPath('5.has_icon', true);
    }

    public function test_un_jugador_no_puede_subir_iconos(): void
    {
        $rank = Rank::first();

        $this->as($this->playerToken())
            ->post("/api/admin/ranks/{$rank->id}/icon", [
                'icon' => UploadedFile::fake()->image('x.png', 64, 64),
            ], ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    public function test_rechaza_iconos_demasiado_grandes(): void
    {
        $rank = Rank::first();

        $this->as($this->adminToken())
            ->post("/api/admin/ranks/{$rank->id}/icon", [
                'icon' => UploadedFile::fake()->image('grande.png', 800, 800),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('icon');
    }

    public function test_el_admin_renombra_un_rango_y_le_cambia_el_color(): void
    {
        $rank = Rank::where('slug', 'minino')->first();

        $this->as($this->adminToken())
            ->putJson("/api/admin/ranks/{$rank->id}", [
                'name' => 'Gatito',
                'color_hex' => '#ff00aa',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Gatito')
            ->assertJsonPath('color_hex', '#ff00aa');
    }

    public function test_rechaza_un_color_mal_formado(): void
    {
        $rank = Rank::first();

        $this->as($this->adminToken())
            ->putJson("/api/admin/ranks/{$rank->id}", ['color_hex' => 'rojo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('color_hex');
    }

    public function test_un_jugador_no_puede_editar_rangos(): void
    {
        $rank = Rank::first();

        $this->as($this->playerToken())
            ->putJson("/api/admin/ranks/{$rank->id}", ['name' => 'Hackeado'])
            ->assertStatus(403);
    }

    // ---------- Banco del equipo ----------

    public function test_el_admin_renombra_el_team(): void
    {
        $this->as($this->adminToken())
            ->putJson('/api/admin/settings', ['team_name' => 'GaLo'])
            ->assertOk()
            ->assertJsonPath('team_name', 'GaLo');
    }

    public function test_un_jugador_no_puede_editar_los_ajustes(): void
    {
        $this->as($this->playerToken())
            ->putJson('/api/admin/settings', ['team_name' => 'Hackeado'])
            ->assertStatus(403);
    }
}
