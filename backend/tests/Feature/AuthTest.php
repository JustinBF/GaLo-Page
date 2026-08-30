<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function loginAs(string $username, string $password): string
    {
        return $this->postJson('/api/login', [
            'username' => $username,
            'password' => $password,
        ])->json('token');
    }

    public function test_permite_entrar_con_la_cuenta_de_admin(): void
    {
        $this->postJson('/api/login', [
            'username' => 'galo_admin',
            'password' => 'admin-secreto-cambiame',
            'actor_label' => 'Justin',
        ])
            ->assertOk()
            ->assertJsonPath('user.is_admin', true)
            ->assertJsonStructure(['token']);
    }

    public function test_permite_entrar_con_la_cuenta_de_jugador(): void
    {
        $this->postJson('/api/login', [
            'username' => 'galo',
            'password' => 'galo2026',
        ])
            ->assertOk()
            ->assertJsonPath('user.is_admin', false);
    }

    public function test_rechaza_contrasenas_incorrectas(): void
    {
        $this->postJson('/api/login', [
            'username' => 'galo_admin',
            'password' => 'nope',
        ])->assertStatus(422);
    }

    public function test_varias_personas_pueden_usar_la_misma_cuenta_a_la_vez(): void
    {
        $first = $this->loginAs('galo_admin', 'admin-secreto-cambiame');
        $second = $this->loginAs('galo_admin', 'admin-secreto-cambiame');

        $this->assertNotSame($first, $second);

        // El segundo login no debe invalidar la sesion del primero.
        $this->withToken($first)->getJson('/api/me')->assertOk();
        $this->withToken($second)->getJson('/api/me')->assertOk();
    }

    public function test_impide_que_un_jugador_escriba(): void
    {
        $token = $this->loginAs('galo', 'galo2026');

        $this->withToken($token)
            ->putJson('/api/admin/settings', ['team_name' => 'Hackeado'])
            ->assertStatus(403);
    }

    public function test_el_admin_puede_actualizar_los_ajustes(): void
    {
        $token = $this->loginAs('galo_admin', 'admin-secreto-cambiame');

        $this->withToken($token)
            ->putJson('/api/admin/settings', ['team_name' => 'GaLo'])
            ->assertOk()
            ->assertJsonPath('team_name', 'GaLo');

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.update']);
    }

    public function test_exige_token_para_leer(): void
    {
        $this->getJson('/api/settings')->assertStatus(401);
    }

    public function test_devuelve_401_sin_cabecera_accept(): void
    {
        // Abrir una URL de la API en el navegador no debe soltar un 500.
        $this->get('/api/settings')
            ->assertStatus(401)
            ->assertJsonPath('message', 'No autenticado.');
    }
}
