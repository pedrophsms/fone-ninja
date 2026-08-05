# Fone Ninja — Frontend

Vue 3 + TypeScript SPA for the Inventory ERP challenge, consuming the `fone-ninja-backend` API.

## Setup

```bash
npm install
cp .env.example .env
npm run dev
```

`VITE_API_BASE_URL` in `.env` must point at the running backend (defaults to `http://localhost/api`, matching Laravel Sail's default port).

## Login (seeded user)

The backend seeds a test user via `php artisan migrate --seed`:

- email: `demo@fone-ninja.test`
- senha: `password123`

## Testing

```bash
npm run test
```
