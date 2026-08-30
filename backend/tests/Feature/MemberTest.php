<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventResult;
use App\Models\Member;
use App\Models\Rank;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * El guard de Sanctum cachea el primer usuario resuelto dentro de un
     * mismo test. Sin este reset, la segunda peticion con otro token
     * seguiria autenticada como el usuario anterior.
     */
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

    public function test_el_admin_crea_un_miembro(): void
    {
        $rank = Rank::where('slug', 'meowth')->first();

        $this->as($this->adminToken())
            ->postJson('/api/admin/members', [
                'nick' => 'Justin',
                'rank_id' => $rank->id,
                'is_player' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nick', 'Justin')
            ->assertJsonPath('data.rank.name', 'Meowth')
            ->assertJsonPath('data.has_avatar', false)
            // Valores por defecto de la BD: deben venir resueltos, no null.
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.ce_balance', 0)
            ->assertJsonPath('data.co_balance', 0);

        $this->assertDatabaseHas('members', ['nick' => 'Justin']);
    }

    public function test_rechaza_nicks_duplicados(): void
    {
        Member::create(['nick' => 'Justin']);

        $this->as($this->adminToken())
            ->postJson('/api/admin/members', ['nick' => 'Justin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nick');
    }

    public function test_un_jugador_no_puede_crear_miembros(): void
    {
        $this->as($this->playerToken())
            ->postJson('/api/admin/members', ['nick' => 'Colado'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('members', ['nick' => 'Colado']);
    }

    public function test_un_jugador_puede_leer_la_tabla(): void
    {
        Member::create(['nick' => 'Justin', 'is_player' => true]);

        $this->as($this->playerToken())
            ->getJson('/api/members?scope=players')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nick', 'Justin');
    }

    public function test_la_tabla_ce_cuenta_los_podios(): void
    {
        $member = Member::create(['nick' => 'Justin', 'is_player' => true]);
        $event = Event::create([
            'name' => 'Torneo 1',
            'held_at' => '2026-08-01',
            'prize_value' => 500000,
        ]);
        $otherEvent = Event::create([
            'name' => 'Torneo 2',
            'held_at' => '2026-08-02',
            'prize_value' => 0,
        ]);

        EventResult::create([
            'event_id' => $event->id, 'member_id' => $member->id, 'position' => 1,
        ]);
        EventResult::create([
            'event_id' => $otherEvent->id, 'member_id' => $member->id, 'position' => 3,
        ]);

        $this->as($this->playerToken())
            ->getJson('/api/members?scope=players')
            ->assertOk()
            ->assertJsonPath('data.0.top1', 1)
            ->assertJsonPath('data.0.top2', 0)
            ->assertJsonPath('data.0.top3', 1);
    }

    public function test_la_tabla_co_suma_el_valor_de_los_premios(): void
    {
        $organizer = Member::create(['nick' => 'Ana', 'is_organizer' => true]);

        Event::create([
            'name' => 'Torneo 1', 'held_at' => '2026-08-01',
            'prize_value' => 500000, 'organizer_id' => $organizer->id,
        ]);
        Event::create([
            'name' => 'Torneo 2', 'held_at' => '2026-08-02',
            'prize_value' => 1500000, 'organizer_id' => $organizer->id,
        ]);

        $this->as($this->playerToken())
            ->getJson('/api/members?scope=organizers')
            ->assertOk()
            ->assertJsonPath('data.0.events_organized', 2)
            ->assertJsonPath('data.0.prizes_total', 2000000);
    }

    public function test_el_admin_sube_y_sirve_un_avatar(): void
    {
        $member = Member::create(['nick' => 'Justin', 'is_player' => true]);

        $this->as($this->adminToken())
            ->post("/api/admin/members/{$member->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('avatar.png', 128, 128),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_avatar', true);

        $this->as($this->playerToken())
            ->get("/api/members/{$member->id}/avatar")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_rechaza_avatares_demasiado_grandes(): void
    {
        $member = Member::create(['nick' => 'Justin']);

        $this->as($this->adminToken())
            ->post("/api/admin/members/{$member->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('grande.png', 1024, 1024),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_un_jugador_no_puede_subir_avatares(): void
    {
        $member = Member::create(['nick' => 'Justin']);

        $this->as($this->playerToken())
            ->post("/api/admin/members/{$member->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('avatar.png', 128, 128),
            ], ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    public function test_devuelve_404_si_no_hay_avatar(): void
    {
        $member = Member::create(['nick' => 'Justin']);

        $this->as($this->playerToken())
            ->get("/api/members/{$member->id}/avatar")
            ->assertNotFound();
    }

    public function test_borrar_un_miembro_solo_lo_desactiva(): void
    {
        $member = Member::create(['nick' => 'Justin', 'is_player' => true]);

        $this->as($this->adminToken())
            ->deleteJson("/api/admin/members/{$member->id}")
            ->assertOk();

        // Sigue existiendo, para no perder su historial.
        $this->assertDatabaseHas('members', ['id' => $member->id, 'is_active' => false]);

        $this->as($this->playerToken())
            ->getJson('/api/members?scope=players')
            ->assertJsonCount(0, 'data');
    }

    public function test_un_jugador_no_puede_ver_miembros_inactivos(): void
    {
        Member::create(['nick' => 'Fantasma', 'is_player' => true, 'is_active' => false]);

        $this->as($this->playerToken())
            ->getJson('/api/members?scope=players&include_inactive=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->as($this->adminToken())
            ->getJson('/api/members?scope=players&include_inactive=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
