<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const CACHE_KEY = 'dashboard:stats';
    private const CACHE_TTL = 60;

    public function stats(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $totais = DB::selectOne(<<<SQL
                SELECT
                    COUNT(*)                AS total_pedidos,
                    COALESCE(SUM(total), 0) AS total_vendido
                FROM pedidos
                SQL
            );

            return [
                'total_pedidos'         => (int)   $totais->total_pedidos,
                'total_vendido'         => (float) $totais->total_vendido,
                'total_transportadoras' => (int)   DB::table('transportadoras')->count(),
            ];
        });
    }
}
