/**
 * Definição das tools MCP relacionadas a pedidos.
 *
 * Cada tool exposta ao Claude tem três partes:
 *   - name:        identificador em snake_case que o Claude usa para chamar
 *   - description: texto em linguagem natural que o Claude lê para decidir
 *                  QUANDO e POR QUE usar essa tool
 *   - inputSchema: JSON Schema com os parâmetros que o Claude deve preencher
 *   - handler:     função que executa quando a tool é invocada
 *
 * Boas descriptions são cruciais — quanto mais claras, melhor o Claude
 * escolhe a tool certa no momento certo.
 */

import { api } from '../api/client.js';

// ─── Tipos de resposta da API ─────────────────────────────────────────────────

interface Pedido {
  id:          number;
  descricao:   string;
  nomecliente: string;
  produto:     string;
  preco:       string;
  quantidade:  number;
  total:       string;
  created_at:  string;
  updated_at:  string;
}

interface PaginatedPedidos {
  data:          Pedido[];
  current_page:  number;
  last_page:     number;
  per_page:      number;
  total:         number;
  from:          number | null;
  to:            number | null;
}

// ─── Definição das tools ──────────────────────────────────────────────────────

export const pedidoTools = [

  // ── 1. Listar / buscar pedidos ──────────────────────────────────────────────
  {
    name: 'listar_pedidos',
    description:
      'Lista pedidos cadastrados no sistema com paginação (50 por página). ' +
      'Use o parâmetro "busca" para filtrar por nome do cliente. ' +
      'Retorna dados paginados: lista de pedidos, página atual, total de registros, etc.',

    inputSchema: {
      type: 'object',
      properties: {
        busca: {
          type: 'string',
          description: 'Filtro por nome do cliente (busca parcial, ignora maiúsculas/minúsculas). Omita para listar todos.',
        },
        page: {
          type: 'number',
          description: 'Número da página a retornar. Padrão: 1.',
        },
      },
    },

    async handler({ busca, page }: { busca?: string; page?: number }) {
      const params = new URLSearchParams();
      if (busca) params.set('busca', busca);
      if (page)  params.set('page',  String(page));

      const qs = params.size ? `?${params.toString()}` : '';
      return api.get<PaginatedPedidos>(`/pedidos${qs}`);
    },
  },

  // ── 2. Buscar pedido por ID ─────────────────────────────────────────────────
  {
    name: 'buscar_pedido',
    description:
      'Retorna os detalhes completos de um pedido específico a partir do seu ID numérico. ' +
      'Use quando o usuário mencionar um número de pedido ou quiser ver detalhes de um item específico.',

    inputSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'number',
          description: 'ID numérico do pedido.',
        },
      },
      required: ['id'],
    },

    async handler({ id }: { id: number }) {
      return api.get<Pedido>(`/pedidos/${id}`);
    },
  },

  // ── 3. Criar pedido ─────────────────────────────────────────────────────────
  {
    name: 'criar_pedido',
    description:
      'Cadastra um novo pedido no sistema. ' +
      'O total é calculado automaticamente (preço × quantidade). ' +
      'Retorna o pedido recém-criado com o ID gerado.',

    inputSchema: {
      type: 'object',
      properties: {
        descricao: {
          type: 'string',
          description: 'Descrição do pedido (entre 3 e 120 caracteres).',
        },
        nomecliente: {
          type: 'string',
          description: 'Nome completo do cliente (até 100 caracteres).',
        },
        produto: {
          type: 'string',
          description: 'Nome do produto (até 70 caracteres).',
        },
        preco: {
          type: 'number',
          description: 'Preço unitário em reais (deve ser maior que zero).',
        },
        quantidade: {
          type: 'number',
          description: 'Quantidade de itens (entre 1 e 9999).',
        },
      },
      required: ['descricao', 'nomecliente', 'produto', 'preco', 'quantidade'],
    },

    async handler(body: {
      descricao:   string;
      nomecliente: string;
      produto:     string;
      preco:       number;
      quantidade:  number;
    }) {
      return api.post<Pedido>('/pedidos', body);
    },
  },

] as const;

// Tipo helper para extrair o nome das tools — usado no index para tipagem do mapa
export type PedidoToolName = (typeof pedidoTools)[number]['name'];
