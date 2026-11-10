# PrintHub com Docker

## Requisitos

- Docker Desktop com Docker Compose v2

## Primeira inicialização

No PowerShell, na raiz do repositório:

```powershell
Copy-Item printhub-back\.env.example printhub-back\.env
docker compose build
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose ps
```

O front-end ficará disponível em <http://localhost:3000> e a API Laravel em
<http://localhost:8000>. A verificação de saúde da API pode ser acessada em
<http://localhost:8000/up>.

O Compose usa MySQL 8.4 e mantém os dados do banco e os arquivos do Laravel em
volumes nomeados. O MySQL não é publicado na máquina host. Para alterar a URL
da API incluída no build do front-end, defina `VITE_API_URL` no arquivo `.env`
da raiz e reconstrua com `docker compose build frontend`.

## Comandos úteis

```powershell
docker compose logs -f
docker compose down
```

`docker compose down` mantém os volumes. Para reiniciar também os dados do banco
e os arquivos enviados, remova os volumes explicitamente com
`docker compose down --volumes`.

O front-end é servido como build estático; após alterar seu código, execute
`docker compose build frontend` e `docker compose up -d frontend`.
