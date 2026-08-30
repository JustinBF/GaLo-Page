<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Rank;
use App\Models\Reward;
use App\Services\CreditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private Member $player;

    private Member $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->player = Member::create(['nick' => 'Justin', 'is_player' => true]);
        $this->organizer = Member::create(['nick' => 'Leo', 'is_organizer' => true]);
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

    private function giveCredits(Member $member, string $currency, int $amount): void
    {
        app(CreditService::class)->post(
            member: $member,
            currency: $currency,
            amount: $amount,
            reason: 'manual_adjust',
            note: 'Saldo de prueba',
        );
        $member->refresh();
    }

    // ---------- Catalogo ----------

    public function test_el_admin_crea_un_premio_de_la_tienda_ce(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/rewards', [
                'name' => 'Ditto 6IV',
                'description' => 'Perfecto para criar',
                'currency' => 'CE',
                'cost' => 300,
                'category' => 'pokemon',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ditto 6IV')
            ->assertJsonPath('data.currency', 'CE')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.in_stock', true);
    }

    public function test_las_dos_tiendas_estan_separadas(): void
    {
        Reward::create(['name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon']);
        Reward::create(['name' => 'Ascenso', 'currency' => 'CO', 'cost' => 500, 'category' => 'especial']);

        $this->as($this->playerToken())
            ->getJson('/api/rewards?currency=CE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ditto');

        $this->as($this->playerToken())
            ->getJson('/api/rewards?currency=CO')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ascenso');
    }

    public function test_un_jugador_no_puede_crear_premios(): void
    {
        $this->as($this->playerToken())
            ->postJson('/api/admin/rewards', [
                'name' => 'Gratis', 'currency' => 'CE', 'cost' => 0, 'category' => 'objeto',
            ])
            ->assertStatus(403);
    }

    public function test_el_admin_sube_y_sirve_la_imagen_de_un_premio(): void
    {
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())
            ->post("/api/admin/rewards/{$reward->id}/image", [
                'image' => UploadedFile::fake()->image('ditto.png', 128, 128),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_image', true);

        // Sin token, como hace <img src>.
        $this->get("/api/rewards/{$reward->id}/image")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_retirar_un_premio_no_lo_borra(): void
    {
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())
            ->deleteJson("/api/admin/rewards/{$reward->id}")
            ->assertOk();

        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'is_active' => false]);

        $this->as($this->playerToken())
            ->getJson('/api/rewards?currency=CE')
            ->assertJsonCount(0, 'data');
    }

    public function test_un_jugador_no_ve_los_premios_retirados(): void
    {
        Reward::create([
            'name' => 'Oculto', 'currency' => 'CE', 'cost' => 100,
            'category' => 'objeto', 'is_active' => false,
        ]);

        $this->as($this->playerToken())
            ->getJson('/api/rewards?include_inactive=1')
            ->assertJsonCount(0, 'data');

        $this->as($this->adminToken())
            ->getJson('/api/rewards?include_inactive=1')
            ->assertJsonCount(1, 'data');
    }

    // ---------- Canjes ----------

    public function test_un_canje_descuenta_el_saldo(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto 6IV', 'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->player->id,
                'reward_id' => $reward->id,
            ])
            ->assertCreated()
            ->assertJsonPath('ce_balance', 200)
            ->assertJsonPath('redemption.reward_name', 'Ditto 6IV')
            ->assertJsonPath('redemption.cost_paid', 300)
            ->assertJsonPath('redemption.status', 'pendiente');

        $this->assertSame(200, $this->player->fresh()->ce_balance);
    }

    public function test_no_deja_canjear_sin_saldo(): void
    {
        $this->giveCredits($this->player, 'CE', 100);
        $reward = Reward::create([
            'name' => 'Caro', 'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->player->id,
                'reward_id' => $reward->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('member_id');

        // El saldo no se toca y no queda canje a medias.
        $this->assertSame(100, $this->player->fresh()->ce_balance);
        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_el_saldo_de_ce_no_paga_premios_de_co(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Premio CO', 'currency' => 'CO', 'cost' => 100, 'category' => 'especial',
        ]);

        $this->as($this->adminToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->player->id,
                'reward_id' => $reward->id,
            ])
            ->assertStatus(422);
    }

    public function test_el_canje_consume_stock(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Unico', 'currency' => 'CE', 'cost' => 100,
            'category' => 'objeto', 'stock' => 1,
        ]);

        $this->as($this->adminToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->player->id, 'reward_id' => $reward->id,
            ])
            ->assertCreated();

        $this->assertSame(0, $reward->fresh()->stock);

        // El segundo canje ya no cabe.
        $this->as($this->adminToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->player->id, 'reward_id' => $reward->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reward_id');
    }

    public function test_el_stock_nulo_es_ilimitado(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Infinito', 'currency' => 'CE', 'cost' => 100,
            'category' => 'objeto', 'stock' => null,
        ]);

        foreach (range(1, 3) as $ignored) {
            $this->as($this->adminToken())
                ->postJson('/api/admin/redemptions', [
                    'member_id' => $this->player->id, 'reward_id' => $reward->id,
                ])
                ->assertCreated();
        }

        $this->assertSame(700, $this->player->fresh()->ce_balance);
        $this->assertNull($reward->fresh()->stock);
    }

    public function test_no_deja_canjear_un_premio_retirado(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Retirado', 'currency' => 'CE', 'cost' => 100,
            'category' => 'objeto', 'is_active' => false,
        ]);

        $this->as($this->adminToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->player->id, 'reward_id' => $reward->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reward_id');
    }

    public function test_un_jugador_no_puede_canjear(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $this->as($this->playerToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->player->id, 'reward_id' => $reward->id,
            ])
            ->assertStatus(403);

        $this->assertSame(1000, $this->player->fresh()->ce_balance);
    }

    // ---------- Ascensos de rango ----------

    public function test_canjear_un_ascenso_sube_el_rango_de_organizador(): void
    {
        $this->giveCredits($this->organizer, 'CO', 800);
        $rank = Rank::where('slug', 'gran-felino')->first();

        $reward = Reward::create([
            'name' => 'Ascenso a Gran Felino',
            'currency' => 'CO',
            'cost' => 500,
            'category' => 'ascenso_rango',
            'grants_rank_id' => $rank->id,
        ]);

        $this->as($this->adminToken())
            ->postJson('/api/admin/redemptions', [
                'member_id' => $this->organizer->id, 'reward_id' => $reward->id,
            ])
            ->assertCreated()
            ->assertJsonPath('co_balance', 300);

        $this->assertSame($rank->id, $this->organizer->fresh()->organizer_rank_id);
    }

    public function test_un_ascenso_no_puede_pagarse_con_ce(): void
    {
        $rank = Rank::where('slug', 'persian')->first();

        $this->as($this->adminToken())
            ->postJson('/api/admin/rewards', [
                'name' => 'Ascenso barato',
                'currency' => 'CE',
                'cost' => 10,
                'category' => 'ascenso_rango',
                'grants_rank_id' => $rank->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('grants_rank_id');
    }

    public function test_un_ascenso_exige_indicar_el_rango(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/rewards', [
                'name' => 'Ascenso vacio',
                'currency' => 'CO',
                'cost' => 500,
                'category' => 'ascenso_rango',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('grants_rank_id');
    }

    // ---------- Estado y cancelacion ----------

    public function test_el_admin_marca_un_canje_como_entregado(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $token = $this->adminToken();
        $id = $this->as($token)->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->json('redemption.id');

        $this->as($token)
            ->putJson("/api/admin/redemptions/{$id}", ['status' => 'entregado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'entregado');
    }

    public function test_cancelar_un_canje_devuelve_los_creditos(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 300,
            'category' => 'pokemon', 'stock' => 2,
        ]);

        $token = $this->adminToken();
        $id = $this->as($token)->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->json('redemption.id');

        $this->assertSame(200, $this->player->fresh()->ce_balance);
        $this->assertSame(1, $reward->fresh()->stock);

        $this->as($token)
            ->postJson("/api/admin/redemptions/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('ce_balance', 500)
            ->assertJsonPath('redemption.status', 'cancelado');

        // Saldo y stock vuelven a su sitio.
        $this->assertSame(500, $this->player->fresh()->ce_balance);
        $this->assertSame(2, $reward->fresh()->stock);
    }

    public function test_cancelar_deja_rastro_en_el_libro_en_vez_de_borrar(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',
        ]);

        $token = $this->adminToken();
        $id = $this->as($token)->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->json('redemption.id');

        $this->as($token)->postJson("/api/admin/redemptions/{$id}/cancel")->assertOk();

        // El cargo original sigue ahi, mas la correccion que lo compensa.
        $this->assertDatabaseHas('credit_transactions', [
            'amount' => -300, 'reason' => 'redemption',
        ]);
        $this->assertDatabaseHas('credit_transactions', [
            'amount' => 300, 'reason' => 'correction',
        ]);
    }

    public function test_no_deja_cancelar_dos_veces(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $token = $this->adminToken();
        $id = $this->as($token)->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->json('redemption.id');

        $this->as($token)->postJson("/api/admin/redemptions/{$id}/cancel")->assertOk();
        $this->as($token)->postJson("/api/admin/redemptions/{$id}/cancel")->assertStatus(422);

        // Y no se devuelve el saldo dos veces.
        $this->assertSame(500, $this->player->fresh()->ce_balance);
    }

    public function test_un_jugador_no_puede_cancelar(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $id = $this->as($this->adminToken())->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->json('redemption.id');

        $this->as($this->playerToken())
            ->postJson("/api/admin/redemptions/{$id}/cancel")
            ->assertStatus(403);

        $this->assertSame(400, $this->player->fresh()->ce_balance);
    }

    // ---------- Perfil del jugador ----------

    public function test_el_perfil_lista_los_premios_canjeados(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Ditto 6IV', 'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->assertCreated();

        $this->as($this->playerToken())
            ->getJson("/api/members/{$this->player->id}/redemptions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reward_name', 'Ditto 6IV')
            ->assertJsonPath('data.0.cost_paid', 300)
            ->assertJsonPath('data.0.reward.category', 'pokemon');
    }

    public function test_el_historial_sobrevive_a_que_se_edite_el_premio(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Ditto 6IV', 'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',
        ]);

        $token = $this->adminToken();
        $this->as($token)->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->assertCreated();

        // El admin sube el precio y le cambia el nombre despues del canje.
        $this->as($token)->putJson("/api/admin/rewards/{$reward->id}", [
            'name' => 'Ditto 6IV (edicion 2027)',
            'currency' => 'CE',
            'cost' => 900,
            'category' => 'pokemon',
        ])->assertOk();

        // El canje conserva lo que se pago de verdad.
        $this->as($this->playerToken())
            ->getJson("/api/members/{$this->player->id}/redemptions")
            ->assertJsonPath('data.0.reward_name', 'Ditto 6IV')
            ->assertJsonPath('data.0.cost_paid', 300);
    }

    public function test_el_canje_aparece_en_el_historial_de_creditos(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->assertCreated();

        $this->as($this->playerToken())
            ->getJson("/api/members/{$this->player->id}/credits")
            ->assertOk()
            ->assertJsonPath('data.0.amount', -300)
            ->assertJsonPath('data.0.reason', 'redemption')
            ->assertJsonPath('data.0.note', 'Canje: Ditto');
    }

    public function test_el_saldo_cacheado_coincide_con_el_libro_tras_canjear(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 300, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ]);

        $member = $this->player->fresh();
        $ledger = (int) $member->transactions()->where('currency', 'CE')->sum('amount');

        $this->assertSame($ledger, $member->ce_balance);
    }

    public function test_la_lista_de_canjes_se_filtra_por_estado(): void
    {
        $this->giveCredits($this->player, 'CE', 1000);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $token = $this->adminToken();
        $first = $this->as($token)->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ])->json('redemption.id');
        $this->as($token)->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ]);

        $this->as($token)->putJson("/api/admin/redemptions/{$first}", [
            'status' => 'entregado',
        ])->assertOk();

        $this->as($this->playerToken())
            ->getJson('/api/redemptions?status=entregado')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->as($this->playerToken())
            ->getJson('/api/redemptions?status=pendiente')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_el_canje_queda_en_la_auditoria(): void
    {
        $this->giveCredits($this->player, 'CE', 500);
        $reward = Reward::create([
            'name' => 'Ditto', 'currency' => 'CE', 'cost' => 100, 'category' => 'pokemon',
        ]);

        $this->as($this->adminToken())->postJson('/api/admin/redemptions', [
            'member_id' => $this->player->id, 'reward_id' => $reward->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'redemption.create']);
    }
}
