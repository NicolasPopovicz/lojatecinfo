/**
 * Servidor MCP — LojaTecInfo
 *
 * MCP (Model Context Protocol) é o protocolo que permite ao Claude
 * chamar ferramentas externas. Este servidor expõe tools de pedidos
 * que o Claude pode usar durante uma conversa.
 *
 * Funcionamento:
 *   O cliente MCP (Claude Desktop / Claude CLI) inicia este processo
 *   como um subprocesso e se comunica com ele via stdin/stdout usando
 *   o protocolo MCP. Por isso o servidor NUNCA deve escrever no stdout
 *   diretamente — use console.error() para logs de debug.
 */

import { Server }               from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

import { pedidoTools } from './tools/pedidos.js';

// Mapa name → handler para lookup O(1) na hora da chamada.
// Tipado com string como chave para aceitar o name que vem do protocolo MCP.
const toolHandlers = new Map<string, (args: never) => Promise<unknown>>(
  pedidoTools.map(tool => [tool.name, tool.handler])
);

// ─── Instância do servidor ────────────────────────────────────────────────────

const server = new Server(
  {
    name:    'lojatecinfo',
    version: '1.0.0',
  },
  {
    capabilities: {
      tools: {}, // habilita o suporte a tools neste servidor
    },
  },
);

// ─── Handler: listar tools disponíveis ───────────────────────────────────────
//
// O cliente chama este endpoint ao iniciar para saber o que o servidor oferece.
// Retornamos name, description e inputSchema de cada tool — sem o handler,
// que é código interno e não precisa trafegar pelo protocolo.

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: pedidoTools.map(({ name, description, inputSchema }) => ({
    name,
    description,
    inputSchema,
  })),
}));

// ─── Handler: executar uma tool ───────────────────────────────────────────────
//
// Chamado quando o Claude decide usar uma das tools. O protocolo envia
// o "name" da tool e os "arguments" preenchidos pelo modelo.

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args = {} } = req.params;

  const handler = toolHandlers.get(name as string);

  if (!handler) {
    // Tool inexistente — isso não deveria acontecer se o cliente usou
    // a lista retornada pelo ListTools, mas protegemos mesmo assim.
    throw new Error(`Tool desconhecida: "${name}"`);
  }

  try {
    const result = await handler(args as never);

    // O protocolo MCP espera o resultado como array de "content blocks".
    // Usamos type "text" com o JSON formatado — o Claude lê e interpreta.
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    console.error(`[mcp] Erro ao executar tool "${name}":`, message);

    // isError: true sinaliza ao cliente que algo deu errado,
    // mas sem lançar exceção — o Claude recebe o erro como texto
    // e pode decidir o que fazer (informar o usuário, tentar novamente, etc.)
    return {
      content: [{ type: 'text', text: `Erro: ${message}` }],
      isError: true,
    };
  }
});

// ─── Inicialização ────────────────────────────────────────────────────────────
//
// StdioServerTransport conecta o servidor ao stdin/stdout do processo.
// A partir daqui o servidor fica aguardando mensagens do cliente MCP.

const transport = new StdioServerTransport();
await server.connect(transport);

console.error('[mcp] Servidor LojaTecInfo iniciado. Aguardando conexão...');
