# Fone Ninja — Backend (ERP de Estoque)

API Laravel 13 para o desafio técnico de ERP de estoque: cadastro de produtos, registro de compras (entrada de estoque + custo médio ponderado), registro de vendas (saída de estoque + lucro), cancelamento de vendas e listagens. Construída com práticas de nível fintech: Value Object `Money` (centavos inteiros), Idempotency-Key nos endpoints financeiros, registros append-only (sem DELETE), locks pessimistas + transações com retry, CHECK constraints no banco, rate limiting e correlação de requisições via logs.

## Stack

- PHP 8.4, Laravel 13
- MySQL 8 (via Sail) para desenvolvimento; SQLite in-memory para a suíte de testes rápida
- Pest para testes
- Laravel Sanctum (autenticação por token)
- Laravel Boost (auxílio de desenvolvimento/IA — dev-only)
- darkaonline/l5-swagger (documentação OpenAPI)



## Pré-requisitos

- Docker + Docker Compose
- (Opcional, só se rodar fora do Sail) PHP 8.4 e Composer localmente



## Configuração rápida (Sail — recomendado para desenvolvimento)

O backend vive em `backend/` do repo consolidado `fone-ninja/`. Rode os comandos abaixo a partir dessa pasta.

```bash
cd backend

composer install          # requer PHP 8.4 local, ou pule e use o passo alternativo abaixo
cp .env.example .env
php artisan key:generate

./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

**Sem PHP 8.4 local:** instale as dependências dentro de um container temporário antes do primeiro `sail up`:

```bash
cd backend
docker run --rm -v "$(pwd):/app" -w /app composer:2 install --ignore-platform-reqs
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

A API sobe em `http://localhost` (porta 80) e o MySQL do Sail na porta 3306.

Se essas portas já estiverem em uso por outro projeto, defina no `.env` antes do `sail up`:

```
APP_PORT=8899
FORWARD_DB_PORT=33061
VITE_PORT=5174
```



### Rodando os testes

```bash
./vendor/bin/sail artisan test
```

A suíte usa SQLite em memória (configurado em `phpunit.xml`), então roda rápido e não depende do MySQL do Sail. Alguns testes de CHECK constraint são pulados nesse driver (só são reforçados de fato no MySQL) — isso é esperado.

## Configuração via Docker puro (sem Sail — conforme requisito do desafio)

O repo consolidado também traz um `docker-compose.yml` na raiz que sobe **frontend + backend + MySQL juntos** (o caminho recomendado para a demo completa — ver `frontend/README.md`). Esta seção cobre apenas o backend isolado, via `docker-compose.prod.yml`.

O desafio original pede um compose file na raiz com Dockerfile do backend, independente do Sail. Esse setup (`docker-compose.prod.yml`) existe separado do Sail (que é a ferramenta de dev/teste) e sobe a aplicação em modo mais próximo de produção — o nome não é `docker-compose.yml` de propósito, pra não colidir com o `compose.yaml` do Sail (dois arquivos "padrão" no mesmo diretório fariam o Docker Compose reclamar em todo comando `sail`):

```bash
cd backend
cp .env.example .env.docker
# edite .env.docker: defina APP_KEY (veja abaixo) e as credenciais DB_* se quiser mudar os padrões

php artisan key:generate --show   # copie o valor gerado para APP_KEY em .env.docker

docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

A API sobe em `http://localhost:8080` e o MySQL na porta `3307` — portas diferentes do Sail, para poder rodar os dois setups ao mesmo tempo sem conflito. Um `name:` explícito no compose evita colisão de containers/rede/volumes com o Sail.

```bash
curl http://localhost:8080/up   # healthcheck
```



## Autenticação

A API usa Sanctum (token simples). Rotas e campos seguem o contrato do README original (em português):

```bash
# Registro
curl -X POST http://localhost/api/registro \
  -H "Content-Type: application/json" \
  -d '{"nome":"Fulano","email":"fulano@example.com","senha":"12345678","senha_confirmation":"12345678"}'

# Login
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"fulano@example.com","senha":"12345678"}'
```

A resposta traz um token; use-o em `Authorization: Bearer <token>` nas demais chamadas.

## Endpoints principais


| Método | Rota                        | Descrição                                   |
| ------ | --------------------------- | ------------------------------------------- |
| POST   | `/api/registro`             | Cria usuário                                |
| POST   | `/api/login`                | Autentica e retorna token                   |
| POST   | `/api/logout`               | Revoga o token atual                        |
| GET    | `/api/produtos`             | Lista produtos (paginado)                   |
| POST   | `/api/produtos`             | Cria produto                                |
| GET    | `/api/compras`              | Lista compras (com itens)                   |
| POST   | `/api/compras`              | Registra compra (requer `Idempotency-Key`)  |
| GET    | `/api/vendas`               | Lista vendas (com itens)                    |
| POST   | `/api/vendas`               | Registra venda (requer `Idempotency-Key`)   |
| POST   | `/api/vendas/preview`       | Projeta total e lucro estimado (sem gravar) |
| POST   | `/api/vendas/{id}/cancelar` | Cancela venda (estorna estoque)             |


Todas as rotas (exceto registro/login) exigem `Authorization: Bearer <token>`.

`POST /api/compras` e `POST /api/vendas` exigem um cabeçalho `Idempotency-Key` (qualquer string única por requisição, ex. um UUID gerado pelo cliente) — reenviar a mesma chave com o mesmo corpo replica a resposta original em vez de duplicar o registro financeiro.

## Documentação da API (Swagger / OpenAPI)

Com a aplicação no ar:

```bash
./vendor/bin/sail artisan l5-swagger:generate   # regenera o spec, se necessário
```

UI disponível em `http://localhost/api/documentation`.

## Dados de exemplo

`php artisan migrate --seed` (ou `sail artisan migrate --seed`) cria um usuário de demonstração, produtos, uma compra e uma venda de exemplo — veja `database/seeders/DatabaseSeeder.php` para as credenciais/valores exatos.

## Decisões de arquitetura

Ver `docs/superpowers/specs/2026-08-05-backend-architecture-design.md` para a justificativa completa (Value Object Money, idempotência, append-only, locks/transações, CHECK constraints, rate limiting, observabilidade) e `docs/superpowers/plans/2026-08-05-backend-implementation.md` para o plano de implementação tarefa a tarefa.

Resumo da arquitetura: `Controller → FormRequest → Action → Repository (interface, DIP) → Service`, com `Resource` formatando a saída. `FormRequest`/`Resource` são a única fronteira que fala português (rotas, campos JSON, mensagens de erro) — todo o resto do código (classes, métodos, migrations, logs) é em inglês.