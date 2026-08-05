# Fone Ninja — ERP de Estoque

ERP de estoque: cadastro de produtos, compras (entrada + custo médio ponderado), vendas (saída + lucro), cancelamento de vendas. Stack: **Laravel API** (`backend/`) + **Vue 3 SPA** (`frontend/`).

```
fone-ninja/
├── docker-compose.yml      <- sobe os três serviços juntos
├── backend/                <- Laravel API (Dockerfile, rotas /api, Sanctum)
└── frontend/               <- Vue 3 SPA (Dockerfile, nginx, Tailwind CSS v4)
```

## Como rodar os dois ao mesmo tempo

Há dois modos. O **Docker compose** é o recomendado (uma stack, zero CORS); o **modo dev** (Sail + Vite) é para desenvolvimento com hot-reload.

### Modo 1 — Docker compose (recomendado)

Sobe frontend + backend + MySQL juntos. Só o frontend é exposto (`http://localhost:8080`); o nginx dele faz proxy de `/api` para o backend — tudo same-origin.

```bash
cp .env.docker.example .env.docker
php artisan key:generate --show          # copie o valor para APP_KEY em .env.docker
# (ou: sed -i "s/^APP_KEY=$/APP_KEY=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')/" .env.docker)

docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker exec backend php artisan migrate --seed --force
```

Abrir `http://localhost:8080` → login: `demo@fone-ninja.test` / `password123`.

Parar: `docker compose --env-file .env.docker down`

### Modo 2 — Dev (Sail + Vite)

Backend via Laravel Sail (porta 80) e frontend via Vite (porta 5173), com o Vite apontando para `http://localhost/api` (CORS liberado pelo backend).

**Terminal 1 — backend:**

```bash
cd backend
cp .env.example .env
php artisan key:generate
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

**Terminal 2 — frontend:**

```bash
cd frontend
npm install
cp .env.example .env        # VITE_API_BASE_URL=http://localhost/api
npm run dev
```

Abrir `http://localhost:5173` → login: `demo@fone-ninja.test` / `password123`.

Parar: `./vendor/bin/sail down` (no `backend/`).

## Endpoints principais (backend)

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/login` | Autentica e retorna token |
| GET/POST | `/api/produtos` | Lista / cria produto |
| GET/POST | `/api/compras` | Lista / registra compra (requer `Idempotency-Key`) |
| GET/POST | `/api/vendas` | Lista / registra venda (requer `Idempotency-Key`) |
| POST | `/api/vendas/preview` | Total e lucro estimado, sem gravar |
| POST | `/api/vendas/{id}/cancelar` | Cancela venda e estorna estoque |

## Testes

```bash
# backend (Pest, SQLite em memória — via Sail)
cd backend && ./vendor/bin/sail artisan test

# frontend (Vitest)
cd frontend && npm run test
```

## Docs

Documentação unificada em `docs/`:

| Arquivo | Conteúdo |
|---|---|
| [`regras-de-negocio.md`](docs/regras-de-negocio.md) | Glossário, fluxos de compra/venda/cancelamento, cálculos de custo médio e lucro, KPIs, integridade |
| [`arquitetura-backend.md`](docs/arquitetura-backend.md) | Modelo ER, middleware pipeline, Actions, eventos, Money VO, Repository pattern |
| [`arquitetura-frontend.md`](docs/arquitetura-frontend.md) | Camadas View→Composable→Store→Service→HTTP, rotas, stores, pipeline de erro, formulários, tema |
| [`decisoes-estruturais-backend.md`](docs/decisoes-estruturais-backend.md) | 14 ADRs: Laravel, PHP 8.4, Sanctum, Money VO, Action, Repository, DTO, idempotência, eventos, locks, retries, FormRequests, services, Pest |
| [`decisoes-estruturais-frontend.md`](docs/decisoes-estruturais-frontend.md) | 11 ADRs: Vue 3 SPA, TS strict, Pinia, shadcn-vue, TanStack Table, composables, Tailwind v4, Axios interceptor, Vitest |
