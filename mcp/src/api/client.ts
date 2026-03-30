/**
 * Cliente HTTP para a API REST do LojaTecInfo.
 *
 * Carrega o .env do diretório mcp/ antes de ler as variáveis de ambiente.
 * Isso é necessário porque imports ESM são hoistados — o client.ts é
 * avaliado antes de qualquer código em index.ts rodar, então o .env
 * precisa ser carregado aqui, onde as variáveis são de fato consumidas.
 *
 * Variáveis de ambiente esperadas:
 *   LOJA_API_URL   — ex: http://localhost:8080 (fora do Docker)
 *                        http://app             (dentro do Docker, via rede interna)
 *   MCP_API_TOKEN  — Bearer token gerado pelo Sanctum
 */

import { readFileSync }         from 'node:fs';
import { resolve, dirname }     from 'node:path';
import { fileURLToPath }        from 'node:url';

// __dirname aponta para dist/api/ após compilação — sobe dois níveis até mcp/
const __dirname = dirname(fileURLToPath(import.meta.url));
const envPath   = resolve(__dirname, '..', '..', '.env');

try {
  const lines = readFileSync(envPath, 'utf-8').split('\n');
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const [key, ...rest] = trimmed.split('=');
    // Variáveis já presentes no ambiente (ex: Docker -e) têm prioridade
    if (key && !(key.trim() in process.env)) {
      process.env[key.trim()] = rest.join('=').trim();
    }
  }
} catch {
  // .env ausente é aceitável — usa variáveis do sistema
}

const BASE_URL = process.env.LOJA_API_URL?.replace(/\/$/, '') ?? 'http://localhost:8080';
const TOKEN    = process.env.MCP_API_TOKEN ?? '';

if (!TOKEN) {
  console.error('[mcp] AVISO: MCP_API_TOKEN não definido. Requisições vão falhar com 401.');
}

/**
 * Faz uma requisição autenticada à API e retorna o JSON parseado.
 * Lança Error com a mensagem da API se o status não for 2xx.
 */
async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const res = await fetch(`${BASE_URL}/api${path}`, {
    method,
    headers: {
      'Authorization': `Bearer ${TOKEN}`,
      'Accept':        'application/json',
      'Content-Type':  'application/json',
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  const data = await res.json() as Record<string, unknown>;

  if (!res.ok) {
    const msg = typeof data.mensagem === 'string' ? data.mensagem : `Erro HTTP ${res.status}`;
    throw new Error(msg);
  }

  return data as T;
}

export const api = {
  get:  <T>(path: string)                => request<T>('GET',  path),
  post: <T>(path: string, body: unknown) => request<T>('POST', path, body),
};
