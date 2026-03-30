<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // pg_trgm é necessário para índices GIN em buscas ILIKE com % no início
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // ── pedidos ──────────────────────────────────────────────────────────

        // Remove o B-tree existente em nomecliente (inútil para ILIKE '%...%')
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['nomecliente']);
        });

        // GIN trigram para ILIKE '%busca%' em nomecliente
        DB::statement('CREATE INDEX idx_pedidos_nomecliente_trgm ON pedidos USING GIN (nomecliente gin_trgm_ops)');

        // B-tree em created_at para o filtro do dashboard (últimos 6 meses)
        Schema::table('pedidos', function (Blueprint $table) {
            $table->index('created_at', 'idx_pedidos_created_at');
        });

        // ── transportadoras ──────────────────────────────────────────────────

        // GIN trigram para ILIKE '%busca%' em nome e cnpj
        DB::statement('CREATE INDEX idx_transportadoras_nome_trgm ON transportadoras USING GIN (nome gin_trgm_ops)');
        DB::statement('CREATE INDEX idx_transportadoras_cnpj_trgm ON transportadoras USING GIN (cnpj gin_trgm_ops)');

        // ── importacoes ───────────────────────────────────────────────────────

        // B-tree em created_at para ORDER BY created_at DESC (histórico recente)
        Schema::table('importacoes', function (Blueprint $table) {
            $table->index('created_at', 'idx_importacoes_created_at');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_pedidos_nomecliente_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_transportadoras_nome_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_transportadoras_cnpj_trgm');

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pedidos_created_at');
            $table->index('nomecliente');
        });

        Schema::table('importacoes', function (Blueprint $table) {
            $table->dropIndex('idx_importacoes_created_at');
        });
    }
};
