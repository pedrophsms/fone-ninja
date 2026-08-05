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

A full three-service stack per the challenge README (SPA + API + MySQL). This repo holds the frontend `Dockerfile` (Node build → nginx serving the SPA and proxying `/api` to the backend) and a `docker-compose.yml` that also builds the backend from the sibling `../fone-ninja-backend` directory.

```bash
cp .env.docker.example .env.docker
# set APP_KEY (run: php artisan key:generate --show)
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker exec backend php artisan migrate --seed --force
```

Open `http://localhost:8080`. The SPA and the API are same-origin through the nginx proxy, so no CORS is involved. Backend and MySQL stay internal to the stack.

## Login (seeded user)

The backend seeds a test user via `php artisan migrate --seed`:

- email: `demo@fone-ninja.test`
- senha: `password123`

## Testing

```bash
npm run test
```
