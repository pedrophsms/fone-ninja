# Fone Ninja — Frontend

Vue 3 + TypeScript SPA for the Inventory ERP challenge, consuming the `fone-ninja-backend` API.

## Setup

```bash
npm install
cp .env.example .env
npm run dev
```

`VITE_API_BASE_URL` in `.env` must point at the running backend (defaults to `http://localhost/api`, matching Laravel Sail's default port).

## Docker (frontend + backend + mysql)

A full three-service stack per the challenge README (SPA + API + MySQL) in a single consolidated repo:

```
fone-ninja/
├── docker-compose.yml
├── frontend/   <- this folder (Dockerfile + nginx /api proxy)
└── backend/    <- Laravel API (Dockerfile)
```

This folder's `Dockerfile` is two-stage: a Node build (`VITE_API_BASE_URL=/api`) then nginx serving the SPA and reverse-proxying `/api` to the backend. The SPA and API are same-origin through the proxy, so no CORS is involved.

From the repo root:

```bash
cp .env.docker.example .env.docker
# set APP_KEY (run: php artisan key:generate --show)
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker exec backend php artisan migrate --seed --force
```

Open `http://localhost:8080`. Backend and MySQL stay internal to the stack.

## Login (seeded user)

The backend seeds a test user via `php artisan migrate --seed`:

- email: `demo@fone-ninja.test`
- senha: `password123`

## Testing

```bash
npm run test
```
