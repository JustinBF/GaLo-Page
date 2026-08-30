<?php

namespace Tests\Feature;

use App\Models\BankMovement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTest extends TestCase
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

    public function test_el_admin_registra_un_aporte_con_nombre_y_descripcion(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/bank', [
                'contributor_name' => 'Rodri',
                'amount' => 2000000,
                'description' => 'Venta de shinies del torneo',
            ])
            ->assertCreated()
            ->assertJsonPath('balance', 2000000)
            ->assertJsonPath('movements.0.contributor_name', 'Rodri')
            ->assertJsonPath('movements.0.description', 'Venta de shinies del torneo');
    }

    public function test_el_aportante_no_tiene_que_ser_miembro_de_la_web(): void
    {
        // Es texto libre a proposito: quien aporta puede no estar en la tabla.
        $this->as($this->adminToken())
            ->postJson('/api/admin/bank', [
                'contributor_name' => 'Un amigo del team',
                'amount' => 500000,
                'description' => 'Donacion',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('bank_movements', [
            'contributor_name' => 'Un amigo del team',
        ]);
    }

    public function test_el_saldo_es_la_suma_de_los_movimientos(): void
    {
        $token = $this->adminToken();

        foreach ([1000000, 500000, -300000] as $amount) {
            $this->as($token)->postJson('/api/admin/bank', [
                'contributor_name' => 'Rodri',
                'amount' => $amount,
                'description' => 'Movimiento',
            ])->assertCreated();
        }

        $this->as($token)
            ->getJson('/api/bank')
            ->assertOk()
            ->assertJsonPath('balance', 1200000);
    }

    public function test_una_cantidad_negativa_registra_una_salida(): void
    {
        $token = $this->adminToken();

        $this->as($token)->postJson('/api/admin/bank', [
            'contributor_name' => 'Rodri', 'amount' => 1000000, 'description' => 'Aporte',
        ]);

        $this->as($token)
            ->postJson('/api/admin/bank', [
                'contributor_name' => 'Leo',
                'amount' => -400000,
                'description' => 'Pago de premios del torneo',
            ])
            ->assertCreated()
            ->assertJsonPath('balance', 600000);
    }

    public function test_exige_nombre_y_descripcion(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/bank', ['amount' => 100000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contributor_name', 'description']);
    }

    public function test_rechaza_un_movimiento_de_cero(): void
    {
        $this->as($this->adminToken())
            ->postJson('/api/admin/bank', [
                'contributor_name' => 'Rodri', 'amount' => 0, 'description' => 'Nada',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_el_saldo_del_banco_sale_en_los_ajustes(): void
    {
        $this->as($this->adminToken())->postJson('/api/admin/bank', [
            'contributor_name' => 'Rodri', 'amount' => 14500000, 'description' => 'Fondo inicial',
        ]);

        $this->as($this->playerToken())
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('bank_balance', 14500000);
    }

    public function test_un_jugador_puede_ver_quien_aporto(): void
    {
        $this->as($this->adminToken())->postJson('/api/admin/bank', [
            'contributor_name' => 'Rodri', 'amount' => 1000000, 'description' => 'Aporte',
        ]);

        $this->as($this->playerToken())
            ->getJson('/api/bank')
            ->assertOk()
            ->assertJsonPath('movements.0.contributor_name', 'Rodri');
    }

    public function test_un_jugador_no_puede_registrar_aportes(): void
    {
        $this->as($this->playerToken())
            ->postJson('/api/admin/bank', [
                'contributor_name' => 'Trampa', 'amount' => 999999, 'description' => 'x',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('bank_movements', 0);
    }

    public function test_borrar_un_apunte_recalcula_el_saldo(): void
    {
        $token = $this->adminToken();

        $this->as($token)->postJson('/api/admin/bank', [
            'contributor_name' => 'Rodri', 'amount' => 1000000, 'description' => 'Aporte',
        ]);
        $this->as($token)->postJson('/api/admin/bank', [
            'contributor_name' => 'Error', 'amount' => 5000000, 'description' => 'Me equivoque',
        ]);

        $wrong = BankMovement::where('contributor_name', 'Error')->first();

        $this->as($token)
            ->deleteJson("/api/admin/bank/{$wrong->id}")
            ->assertOk()
            ->assertJsonPath('balance', 1000000);
    }

    public function test_un_jugador_no_puede_borrar_apuntes(): void
    {
        $this->as($this->adminToken())->postJson('/api/admin/bank', [
            'contributor_name' => 'Rodri', 'amount' => 1000000, 'description' => 'Aporte',
        ]);

        $movement = BankMovement::first();

        $this->as($this->playerToken())
            ->deleteJson("/api/admin/bank/{$movement->id}")
            ->assertStatus(403);

        $this->assertDatabaseCount('bank_movements', 1);
    }

    public function test_el_aporte_queda_en_la_auditoria(): void
    {
        $this->as($this->adminToken())->postJson('/api/admin/bank', [
            'contributor_name' => 'Rodri', 'amount' => 1000000, 'description' => 'Aporte',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'bank.movement']);
    }

    public function test_el_banco_empieza_a_cero(): void
    {
        $this->as($this->playerToken())
            ->getJson('/api/bank')
            ->assertOk()
            ->assertJsonPath('balance', 0)
            ->assertJsonCount(0, 'movements');
    }
}
