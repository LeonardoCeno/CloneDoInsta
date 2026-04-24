# InstaClone

REST API em Laravel + SPA em Vue 3, containerizados com Docker.

## Pré-requisitos

- Docker + Docker Compose instalados

## Rodar

**1. Suba os containers:**

```bash
docker compose up -d --build
```

Aguarde ~30s para o MySQL inicializar e as migrations rodarem automaticamente.

**2. Acesse:**

| Serviço       | URL                              |
|---------------|----------------------------------|
| Frontend      | http://localhost:3000            |
| Swagger UI    | http://localhost:8000/docs       |
| OpenAPI YAML  | http://localhost:8000/api/openapi.yaml |
| Health        | http://localhost:8000/up         |

## Seed (dados de exemplo, não são obrigatórios)

```bash
docker compose exec app php artisan db:seed
```

Cria 3 usuários de teste:

| Nome  | Email             | Senha    |
|-------|-------------------|----------|
| Alice | alice@example.com | password |
| Bob   | bob@example.com   | password |
| Carol | carol@example.com | password |


## Parar

```bash
docker compose down
```

Para remover volumes (banco de dados):

```bash
docker compose down -v
```
