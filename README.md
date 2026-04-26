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

---

## Rodar sem Docker

**Pré-requisitos:** PHP 8.3+, Composer, Node.js 18+, MySQL (ou SQLite para desenvolvimento)

### Backend

**1. Configure o ambiente:**

```bash
cd backend
composer setup
```

Esse comando instala dependências, copia o `.env.example`, gera a `APP_KEY` e roda as migrations.

**2. Ajuste o `.env` conforme seu banco de dados local:**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=instaclone
DB_USERNAME=root
DB_PASSWORD=sua_senha
```

> Para usar SQLite: `DB_CONNECTION=sqlite` (cria `database/database.sqlite` automaticamente)

**3. Crie o link de storage:**

```bash
php artisan storage:link
```

### Frontend

```bash
cd frontend
npm install
```

### Rodar tudo junto

A partir da pasta `backend`, um único comando sobe o servidor Laravel, o worker de filas e o Vite em paralelo:

```bash
composer dev
```

| Serviço  | URL                        |
|----------|----------------------------|
| Frontend | http://localhost:5173       |
| Swagger  | http://localhost:8000/docs  |
| health | http://localhost:8000/up |

### Seed (opcional)

```bash
php artisan db:seed
```
