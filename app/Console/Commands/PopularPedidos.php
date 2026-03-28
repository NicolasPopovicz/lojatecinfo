<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopularPedidos extends Command
{
    protected $signature = 'pedidos:popular
                            {--total=1000000 : Total de registros a inserir}
                            {--lote=2000     : Registros por lote de INSERT}';

    protected $description = 'Popula a tabela de pedidos com dados fictícios para testes de performance';

    // Pool fixo — gerado uma vez, reutilizado aleatoriamente para evitar
    // chamar o Faker 1.000.000 vezes individualmente (muito mais rápido)
    private const TAMANHO_POOL = 500;

    private const PRODUTOS = [
        'iPhone 15', 'iPhone 15 Pro', 'Samsung Galaxy S24', 'Samsung Galaxy A54',
        'MacBook Pro M3', 'MacBook Air M2', 'Dell XPS 15', 'Lenovo ThinkPad X1',
        'iPad Pro 12.9', 'iPad Air', 'Kindle Paperwhite', 'Amazon Echo',
        'PlayStation 5', 'Xbox Series X', 'Nintendo Switch', 'Steam Deck',
        'AirPods Pro', 'Galaxy Buds 2', 'Sony WH-1000XM5', 'JBL Tune 760NC',
        'Apple Watch Series 9', 'Samsung Galaxy Watch 6', 'Garmin Fenix 7',
        'Monitor LG 27" 4K', 'Monitor Samsung 32" Curvo', 'Monitor Dell 24" IPS',
        'Teclado Mecânico Keychron K2', 'Mouse Logitech MX Master 3',
        'Webcam Logitech C920', 'Headset HyperX Cloud II',
        'SSD Samsung 1TB NVMe', 'SSD Kingston 512GB', 'HD Seagate 2TB',
        'Memória RAM Corsair 16GB DDR5', 'Placa de Vídeo RTX 4070',
        'Processador Intel i9-13900K', 'Processador AMD Ryzen 9 7950X',
        'Fonte Corsair 750W Gold', 'Gabinete NZXT H510', 'Cooler DeepCool AK620',
        'Roteador TP-Link AX3000', 'Switch TP-Link 8 Portas', 'Cabo HDMI 2.1 3m',
        'Carregador USB-C 65W', 'Hub USB-C 7 em 1', 'Suporte para Notebook',
    ];

    public function handle(): int
    {
        $total = (int) $this->option('total');
        $lote  = (int) $this->option('lote');

        if ($total <= 0 || $lote <= 0) {
            $this->error('Os valores de --total e --lote devem ser maiores que zero.');
            return self::FAILURE;
        }

        $totalLotes = (int) ceil($total / $lote);

        $this->newLine();
        $this->info("┌─────────────────────────────────────────┐");
        $this->info("│     Popular Pedidos — Início             │");
        $this->info("└─────────────────────────────────────────┘");
        $this->line("  Total de registros : <fg=cyan>" . number_format($total, 0, ',', '.') . "</>");
        $this->line("  Tamanho do lote     : <fg=cyan>" . number_format($lote, 0, ',', '.') . "</>");
        $this->line("  Total de lotes      : <fg=cyan>" . number_format($totalLotes, 0, ',', '.') . "</>");
        $this->newLine();

        $this->line("  Gerando pool de dados fictícios...");
        $pool   = $this->gerarPool();
        $agora  = now()->toDateTimeString();
        $inicio = microtime(true);

        $barra = $this->output->createProgressBar($total);
        $barra->setFormat(
            "  %current:10s% / %max:-10s% [%bar%] %percent:3s%%\n" .
            "  Lote <fg=yellow>%lote_atual%/%total_lotes%</>  |  Velocidade: <fg=green>%velocidade% reg/s</>  |  ETA: %estimated:-6s%\n"
        );
        $barra->setMessage('0', 'lote_atual');
        $barra->setMessage((string) $totalLotes, 'total_lotes');
        $barra->setMessage('--', 'velocidade');
        $barra->start();

        $inseridos  = 0;
        $loteAtual  = 0;
        $poolTamanho = count($pool);

        while ($inseridos < $total) {
            $loteAtual++;
            $quantidade = min($lote, $total - $inseridos);
            $registros  = [];

            for ($i = 0; $i < $quantidade; $i++) {
                $item = $pool[($inseridos + $i) % $poolTamanho];
                $registros[] = [
                    'descricao'   => $item['descricao'],
                    'nomecliente' => $item['nomecliente'],
                    'produto'     => self::PRODUTOS[array_rand(self::PRODUTOS)],
                    'preco'       => $item['preco'],
                    'quantidade'  => $item['quantidade'],
                    'total'       => $item['total'],
                    'created_at'  => $agora,
                    'updated_at'  => $agora,
                ];
            }

            DB::table('pedidos')->insert($registros);
            $inseridos += $quantidade;

            $decorrido   = microtime(true) - $inicio;
            $velocidade  = $decorrido > 0 ? (int) round($inseridos / $decorrido) : 0;

            $barra->setMessage((string) $loteAtual, 'lote_atual');
            $barra->setMessage(number_format($velocidade, 0, ',', '.'), 'velocidade');
            $barra->advance($quantidade);
        }

        $barra->finish();

        $tempoTotal = microtime(true) - $inicio;
        $velocidade = (int) round($total / $tempoTotal);

        $this->newLine(2);
        $this->info("┌─────────────────────────────────────────┐");
        $this->info("│     Popular Pedidos — Concluído          │");
        $this->info("└─────────────────────────────────────────┘");
        $this->line("  Registros inseridos : <fg=green>" . number_format($inseridos, 0, ',', '.') . "</>");
        $this->line("  Tempo total         : <fg=green>" . number_format($tempoTotal, 2, '.', ',') . "s</>");
        $this->line("  Velocidade média    : <fg=green>" . number_format($velocidade, 0, ',', '.') . " reg/s</>");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Gera um pool de registros fictícios pré-computados.
     * Reutilizar o pool é muito mais rápido do que invocar o Faker 1M vezes.
     */
    private function gerarPool(): array
    {
        $nomes = [
            'Ana Silva', 'Carlos Oliveira', 'Fernanda Santos', 'João Pereira',
            'Mariana Costa', 'Pedro Souza', 'Juliana Lima', 'Rafael Alves',
            'Camila Rodrigues', 'Lucas Ferreira', 'Beatriz Gomes', 'Thiago Martins',
            'Larissa Nascimento', 'Felipe Carvalho', 'Amanda Ribeiro', 'Bruno Araujo',
            'Letícia Mendes', 'Gustavo Barbosa', 'Natalia Rocha', 'Diego Cardoso',
            'Patricia Melo', 'Eduardo Teixeira', 'Vanessa Borges', 'Rodrigo Dias',
            'Aline Monteiro', 'Marcelo Castro', 'Priscila Moreira', 'André Neves',
            'Simone Freitas', 'Paulo Azevedo', 'Tatiane Correia', 'Vinícius Pinto',
            'Cristiane Moura', 'Fábio Cavalcanti', 'Renata Cunha', 'Alexandre Leal',
            'Jéssica Campos', 'Matheus Vieira', 'Daniela Coelho', 'Leandro Fonseca',
        ];

        $descricoes = [
            'Pedido realizado pelo site', 'Compra via aplicativo', 'Pedido presencial',
            'Encomenda especial', 'Compra corporativa', 'Pedido urgente',
            'Produto para presentear', 'Reposição de estoque', 'Uso pessoal',
            'Compra para escritório', 'Pedido recorrente', 'Primeira compra',
            'Pedido com desconto aplicado', 'Compra parcelada', 'Pedido com nota fiscal',
            'Produto importado sob encomenda', 'Entrega agendada', 'Retirada na loja',
            'Troca de produto anterior', 'Compra por indicação',
        ];

        $pool = [];

        for ($i = 0; $i < self::TAMANHO_POOL; $i++) {
            $preco      = round(rand(2990, 1999990) / 100, 2);
            $quantidade = rand(1, 50);

            $pool[] = [
                'descricao'   => $descricoes[array_rand($descricoes)],
                'nomecliente' => $nomes[array_rand($nomes)],
                'preco'       => $preco,
                'quantidade'  => $quantidade,
                'total'       => round($preco * $quantidade, 2),
            ];
        }

        return $pool;
    }
}
