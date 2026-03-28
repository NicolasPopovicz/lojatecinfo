<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $dados = Cache::remember('dashboard:stats', 60, function () {
            $totais = DB::selectOne("SELECT
                    COUNT(*)                AS total_pedidos,
                    COALESCE(SUM(total), 0) AS total_vendido
                FROM pedidos
            ");

            return [
                'total_pedidos'         => (int)   $totais->total_pedidos,
                'total_vendido'         => (float)  $totais->total_vendido,
                'total_transportadoras' => (int)    DB::table('transportadoras')->count(),
            ];
        });

        return view('dashboard', $dados);
    }
}
