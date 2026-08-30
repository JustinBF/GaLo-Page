<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Member;
use App\Services\CreditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private Member $winner;

    private Member $runnerUp;

    private Member $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->winner = Member::create(['nick' => 'Justin', 'is_player' => true]);
        $this->runnerUp = Member::create(['nick' => 'Ana', 'is_player' => true]);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Torneo de agosto',
            'type' => 'torneo',
            'held_at' => '2026-08-15',
            'difficulty' => 'alta',
            'prize_value' => 1500000,
            'organizer_id' => $this->organizer->id,
            'results' => [
                ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 100],
                ['member_id' => $this->runnerUp->id, 'position' => 2, 'ce_awarded' => 50],
            ],
        ], $overrides);
    }

    public function test_crear_un_evento_reparte_ce_y_co(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload())
            ->assertCreated()
            // 1.5M cae en la regla de "+1M" -> 100 CO
            ->assertJsonPath('data.co_awarded', 100)
            ->assertJsonPath('data.total_ce_awarded', 150);

        $this->assertSame(100, $this->winner->fresh()->ce_balance);
        $this->assertSame(50, $this->runnerUp->fresh()->ce_balance);
        $this->assertSame(100, $this->organizer->fresh()->co_balance);
    }

    public function test_aplica_la_regla_de_500k(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload(['prize_value' => 500000]))
            ->assertCreated()
            ->assertJsonPath('data.co_awarded', 50);

        $this->assertSame(50, $this->organizer->fresh()->co_balance);
    }

    public function test_un_premio_pequeno_no_da_co(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload(['prize_value' => 100000]))
            ->assertCreated()
            ->assertJsonPath('data.co_awarded', 0);

        $this->assertSame(0, $this->organizer->fresh()->co_balance);
    }

    public function test_el_admin_puede_forzar_el_co_a_mano(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload(['co_awarded' => 250]))
            ->assertCreated()
            ->assertJsonPath('data.co_awarded', 250)
            ->assertJsonPath('data.co_manual_override', true);

        $this->assertSame(250, $this->organizer->fresh()->co_balance);
    }

    public function test_editar_un_evento_no_duplica_creditos(): void
    {
        $token = $this->adminToken();

        $id = $this->as($token)
            ->postJson('/api/admin/events', $this->payload())
            ->json('data.id');

        $this->assertSame(100, $this->winner->fresh()->ce_balance);

        // Mismo evento, el ganador pasa de 100 a 400 CE.
        $this->as($token)
            ->putJson("/api/admin/events/{$id}", $this->payload([
                'results' => [
                    ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 400],
                ],
            ]))
            ->assertOk();

        // 400, no 500: el reparto se rehace, no se acumula.
        $this->assertSame(400, $this->winner->fresh()->ce_balance);
        // Ana ya no esta en el podio: pierde lo que le dio este evento.
        $this->assertSame(0, $this->runnerUp->fresh()->ce_balance);
        $this->assertSame(100, $this->organizer->fresh()->co_balance);
    }

    public function test_borrar_un_evento_retira_sus_creditos(): void
    {
        $token = $this->adminToken();

        $id = $this->as($token)
            ->postJson('/api/admin/events', $this->payload())
            ->json('data.id');

        $this->as($token)->deleteJson("/api/admin/events/{$id}")->assertOk();

        $this->assertSame(0, $this->winner->fresh()->ce_balance);
        $this->assertSame(0, $this->organizer->fresh()->co_balance);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_un_ajuste_manual_sobrevive_a_editar_el_evento(): void
    {
        $token = $this->adminToken();

        $id = $this->as($token)
            ->postJson('/api/admin/events', $this->payload())
            ->json('data.id');

        $this->as($token)
            ->postJson("/api/admin/members/{$this->winner->id}/credits", [
                'currency' => 'CE',
                'amount' => 25,
                'note' => 'Bonus por ayudar',
            ])
            ->assertOk()
            ->assertJsonPath('ce_balance', 125);

        $this->as($token)
            ->putJson("/api/admin/events/{$id}", $this->payload([
                'results' => [
                    ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 200],
                ],
            ]))
            ->assertOk();

        // 200 del evento + 25 del ajuste manual, que no debe tocarse.
        $this->assertSame(225, $this->winner->fresh()->ce_balance);
    }

    public function test_rechaza_dos_jugadores_en_la_misma_posicion(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload([
                'results' => [
                    ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 10],
                    ['member_id' => $this->runnerUp->id, 'position' => 1, 'ce_awarded' => 10],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('results');
    }

    public function test_rechaza_al_mismo_jugador_dos_veces(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload([
                'results' => [
                    ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 10],
                    ['member_id' => $this->winner->id, 'position' => 2, 'ce_awarded' => 10],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('results');
    }

    public function test_un_ajuste_manual_negativo_resta(): void
    {
        $token = $this->adminToken();

        $this->as($token)
            ->postJson("/api/admin/members/{$this->winner->id}/credits", [
                'currency' => 'CE', 'amount' => 100, 'note' => 'Inicial',
            ])->assertOk();

        $this->as($token)
            ->postJson("/api/admin/members/{$this->winner->id}/credits", [
                'currency' => 'CE', 'amount' => -30, 'note' => 'Correccion',
            ])
            ->assertOk()
            ->assertJsonPath('ce_balance', 70);
    }

    public function test_un_ajuste_exige_motivo(): void
    {
        $this->as($this->adminToken())
            ->postJson("/api/admin/members/{$this->winner->id}/credits", [
                'currency' => 'CE', 'amount' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
    }

    public function test_el_historial_explica_el_saldo(): void
    {
        $token = $this->adminToken();

        $this->as($token)->postJson('/api/admin/events', $this->payload())->assertCreated();

        $this->as($this->playerToken())
            ->getJson("/api/members/{$this->winner->id}/credits")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 100)
            ->assertJsonPath('data.0.reason', 'event_win')
            ->assertJsonPath('data.0.note', 'Top 1 en Torneo de agosto');
    }

    public function test_un_jugador_no_puede_crear_eventos(): void
    {
        $this->as($this->playerToken())
            ->postJson('/api/admin/events', $this->payload())
            ->assertStatus(403);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_un_jugador_no_puede_ajustar_creditos(): void
    {
        $this->as($this->playerToken())
            ->postJson("/api/admin/members/{$this->winner->id}/credits", [
                'currency' => 'CE', 'amount' => 9999, 'note' => 'trampa',
            ])
            ->assertStatus(403);

        $this->assertSame(0, $this->winner->fresh()->ce_balance);
    }

    public function test_un_jugador_puede_leer_los_eventos(): void
    {
        $this->as($this->adminToken())->postJson('/api/admin/events', $this->payload());

        $this->as($this->playerToken())
            ->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.organizer.nick', 'Leo')
            ->assertJsonPath('data.0.results.0.member.nick', 'Justin');
    }

    public function test_sugiere_el_co_segun_el_premio(): void
    {
        $token = $this->adminToken();

        $this->as($token)
            ->postJson('/api/admin/events/suggest-co', ['prize_value' => 750000])
            ->assertOk()
            ->assertJsonPath('co_awarded', 50);

        $this->as($token)
            ->postJson('/api/admin/events/suggest-co', ['prize_value' => 3000000])
            ->assertOk()
            ->assertJsonPath('co_awarded', 100);
    }

    public function test_el_saldo_cacheado_coincide_con_el_libro(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload())
            ->assertCreated();

        $member = $this->winner->fresh();
        $ledger = $member->transactions()->where('currency', 'CE')->sum('amount');

        $this->assertSame((int) $ledger, $member->ce_balance);
    }

    public function test_borrar_un_evento_conserva_los_ajustes_manuales(): void
    {
        $token = $this->adminToken();

        $id = $this->as($token)
            ->postJson('/api/admin/events', $this->payload())
            ->json('data.id');

        $this->as($token)->postJson("/api/admin/members/{$this->winner->id}/credits", [
            'currency' => 'CE', 'amount' => 40, 'note' => 'Bonus',
        ])->assertOk();

        $this->as($token)->deleteJson("/api/admin/events/{$id}")->assertOk();

        $this->assertSame(40, $this->winner->fresh()->ce_balance);
        $this->assertDatabaseMissing('events', ['id' => $id]);
    }

    public function test_el_evento_desaparece_de_los_agregados_al_borrarlo(): void
    {
        $token = $this->adminToken();

        $id = $this->as($token)
            ->postJson('/api/admin/events', $this->payload())
            ->json('data.id');

        $this->as($token)
            ->getJson('/api/members?scope=organizers')
            ->assertJsonPath('data.0.events_organized', 1)
            ->assertJsonPath('data.0.prizes_total', 1500000);

        $this->as($token)->deleteJson("/api/admin/events/{$id}")->assertOk();

        $this->as($token)
            ->getJson('/api/members?scope=organizers')
            ->assertJsonPath('data.0.events_organized', 0);
    }

    public function test_no_deja_borrar_eventos_inexistentes(): void
    {
        $this->as($this->adminToken())
            ->deleteJson('/api/admin/events/999')
            ->assertNotFound();
    }

    public function test_los_eventos_se_listan_del_mas_reciente_al_mas_antiguo(): void
    {
        $token = $this->adminToken();

        $this->as($token)->postJson('/api/admin/events', $this->payload([
            'name' => 'Antiguo', 'held_at' => '2026-01-01', 'results' => [],
        ]));
        $this->as($token)->postJson('/api/admin/events', $this->payload([
            'name' => 'Reciente', 'held_at' => '2026-08-20', 'results' => [],
        ]));

        $this->as($token)
            ->getJson('/api/events')
            ->assertJsonPath('data.0.name', 'Reciente')
            ->assertJsonPath('data.1.name', 'Antiguo');
    }

    public function test_una_regla_de_co_nueva_cambia_la_sugerencia(): void
    {
        $token = $this->adminToken();

        $this->as($token)
            ->postJson('/api/admin/co-rules', [
                'label' => 'Premios de 2M o mas',
                'min_prize_value' => 2000000,
                'max_prize_value' => null,
                'co_amount' => 200,
                'priority' => 30,
            ])
            ->assertCreated();

        $this->as($token)
            ->postJson('/api/admin/events/suggest-co', ['prize_value' => 2500000])
            ->assertOk()
            ->assertJsonPath('co_awarded', 200);
    }

    public function test_un_evento_sin_organizador_no_reparte_co(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload(['organizer_id' => null]))
            ->assertCreated();

        $this->assertDatabaseCount('credit_transactions', 2);
        $this->assertSame(0, $this->organizer->fresh()->co_balance);
    }

    public function test_un_evento_sin_podio_es_valido(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload(['results' => []]))
            ->assertCreated()
            ->assertJsonPath('data.total_ce_awarded', 0);

        // El organizador cobra igual.
        $this->assertSame(100, $this->organizer->fresh()->co_balance);
    }

    public function test_recalcular_arregla_un_saldo_corrompido(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload())
            ->assertCreated();

        // Alguien toca el saldo cacheado por fuera.
        $this->winner->forceFill(['ce_balance' => 99999])->save();

        app(CreditService::class)->recalculate($this->winner);

        $this->assertSame(100, $this->winner->fresh()->ce_balance);
    }

    public function test_los_eventos_borrados_no_dejan_transacciones_huerfanas(): void
    {
        $token = $this->adminToken();

        $id = $this->as($token)
            ->postJson('/api/admin/events', $this->payload())
            ->json('data.id');

        $this->as($token)->deleteJson("/api/admin/events/{$id}")->assertOk();

        $this->assertDatabaseMissing('credit_transactions', ['event_id' => $id]);
        $this->assertDatabaseMissing('event_results', ['event_id' => $id]);
    }

    public function test_el_evento_guarda_la_dificultad(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload(['difficulty' => 'extrema']))
            ->assertCreated()
            ->assertJsonPath('data.difficulty', 'extrema');
    }

    public function test_rechaza_una_dificultad_inventada(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload(['difficulty' => 'imposible']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('difficulty');
    }

    public function test_rechaza_mas_de_tres_puestos(): void
    {
        $extra = Member::create(['nick' => 'Cuarto', 'is_player' => true]);

        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload([
                'results' => [
                    ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 10],
                    ['member_id' => $this->runnerUp->id, 'position' => 2, 'ce_awarded' => 10],
                    ['member_id' => $this->organizer->id, 'position' => 3, 'ce_awarded' => 10],
                    ['member_id' => $extra->id, 'position' => 3, 'ce_awarded' => 10],
                ],
            ]))
            ->assertStatus(422);
    }

    public function test_los_agregados_de_podio_reflejan_los_eventos(): void
    {
        $token = $this->adminToken();

        $this->as($token)->postJson('/api/admin/events', $this->payload());
        $this->as($token)->postJson('/api/admin/events', $this->payload([
            'name' => 'Segundo torneo',
            'results' => [
                ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 80],
            ],
        ]));

        $this->as($token)
            ->getJson('/api/members?scope=players')
            ->assertJsonPath('data.0.nick', 'Justin')
            ->assertJsonPath('data.0.top1', 2)
            ->assertJsonPath('data.0.ce_balance', 180);
    }

    public function test_los_movimientos_recientes_alimentan_el_dashboard(): void
    {
        $token = $this->adminToken();

        $this->as($token)->postJson('/api/admin/events', $this->payload());

        $this->as($this->playerToken())
            ->getJson('/api/credits/recent')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_no_crea_transacciones_con_importe_cero(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload([
                'prize_value' => 0,
                'organizer_id' => $this->organizer->id,
                'results' => [
                    ['member_id' => $this->winner->id, 'position' => 1, 'ce_awarded' => 0],
                ],
            ]))
            ->assertCreated();

        // Ni CE de 0 ni CO de 0 deben ensuciar el libro.
        $this->assertDatabaseCount('credit_transactions', 0);
        $this->assertDatabaseCount('event_results', 1);
    }

    public function test_el_evento_queda_registrado_en_la_auditoria(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/events', $this->payload())
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event.create',
            'model_type' => Event::class,
        ]);
    }
}
