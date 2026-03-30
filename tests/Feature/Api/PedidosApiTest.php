<?php

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$pedidoValido = [
    'descricao'   => 'Pedido de teste automatizado',
    'nomecliente' => 'João Silva',
    'produto'     => 'Notebook',
    'preco'       => 2999.90,
    'quantidade'  => 2,
];

// ── Autenticação ─────────────────────────────────────────────────────────────

test('rejeita requisição sem token', function () use ($pedidoValido) {
    $this->getJson('/api/pedidos')->assertUnauthorized();
    $this->getJson('/api/pedidos/1')->assertUnauthorized();
    $this->postJson('/api/pedidos', $pedidoValido)->assertUnauthorized();
});

// ── GET /api/pedidos ──────────────────────────────────────────────────────────

test('lista pedidos paginados', function () {
    $user = User::factory()->create();

    Pedido::create([
        'descricao' => 'Desc A', 'nomecliente' => 'Maria', 'produto' => 'TV',
        'preco' => 1500, 'quantidade' => 1, 'total' => 1500,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/pedidos')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'nomecliente', 'produto', 'preco', 'quantidade', 'total']],
            'current_page', 'per_page', 'total',
        ]);
});

test('filtra pedidos por busca de cliente', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('ILIKE requer PostgreSQL.');
    }
    $user = User::factory()->create();

    Pedido::create([
        'descricao' => 'Desc', 'nomecliente' => 'Carlos Souza', 'produto' => 'Mouse',
        'preco' => 50, 'quantidade' => 1, 'total' => 50,
    ]);
    Pedido::create([
        'descricao' => 'Desc', 'nomecliente' => 'Ana Lima', 'produto' => 'Teclado',
        'preco' => 80, 'quantidade' => 1, 'total' => 80,
    ]);

    $resposta = $this->actingAs($user, 'sanctum')
        ->getJson('/api/pedidos?busca=Carlos')
        ->assertOk();

    expect($resposta->json('data'))->toHaveCount(1)
        ->and($resposta->json('data.0.nomecliente'))->toBe('Carlos Souza');
});

// ── GET /api/pedidos/{id} ─────────────────────────────────────────────────────

test('retorna pedido pelo id', function () {
    $user   = User::factory()->create();
    $pedido = Pedido::create([
        'descricao' => 'Desc', 'nomecliente' => 'Pedro', 'produto' => 'Monitor',
        'preco' => 900, 'quantidade' => 1, 'total' => 900,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/pedidos/{$pedido->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $pedido->id, 'nomecliente' => 'Pedro']);
});

test('retorna 404 para id inexistente', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/pedidos/999999')
        ->assertNotFound();
});

// ── POST /api/pedidos ─────────────────────────────────────────────────────────

test('cria pedido com dados válidos', function () use ($pedidoValido) {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pedidos', $pedidoValido)
        ->assertCreated()
        ->assertJsonFragment([
            'nomecliente' => 'João Silva',
            'produto'     => 'Notebook',
        ]);

    $this->assertDatabaseHas('pedidos', [
        'nomecliente' => 'João Silva',
        'produto'     => 'Notebook',
    ]);
});

test('rejeita pedido com campos obrigatórios ausentes', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pedidos', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['descricao', 'nomecliente', 'produto', 'preco', 'quantidade']);
});

test('rejeita preço zero ou negativo', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pedidos', [
            'descricao' => 'Desc', 'nomecliente' => 'X', 'produto' => 'Y',
            'preco' => 0, 'quantidade' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preco']);
});

test('rejeita quantidade acima do limite', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pedidos', [
            'descricao' => 'Desc', 'nomecliente' => 'X', 'produto' => 'Y',
            'preco' => 10, 'quantidade' => 10000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantidade']);
});
