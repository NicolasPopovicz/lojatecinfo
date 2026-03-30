# ── Stage 1: build ──────────────────────────────────────────────────────────
# Compila o TypeScript para JavaScript em /app/dist
FROM node:22-alpine AS builder

WORKDIR /app
COPY package*.json tsconfig.json ./
RUN npm ci
COPY src/ ./src/
RUN npm run build

# ── Stage 2: runtime ─────────────────────────────────────────────────────────
# Imagem final enxuta: só o JS compilado e as deps de produção
FROM node:22-alpine

WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY --from=builder /app/dist ./dist

# O servidor MCP se comunica via stdin/stdout — não expõe porta
CMD ["node", "dist/index.js"]
