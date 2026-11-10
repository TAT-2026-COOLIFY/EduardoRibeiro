# PrintHub API: Guia do Frontend

Este documento descreve o contrato atual da API implementada no backend.

## 1. Configuracao

### URL base

Em desenvolvimento, inicie o backend com:

```powershell
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

URL base da API:

```text
http://127.0.0.1:8000/api
```

Todas as rotas abaixo ja consideram o prefixo `/api`.

Exemplo com Axios:

```ts
const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('PrintHub_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
```

Para requests JSON, envie tambem `Content-Type: application/json`. Para upload STL, use `multipart/form-data` e nao defina manualmente o boundary.

## 2. Autenticacao

Rotas publicas:

| Metodo | Rota | Body | Resposta |
|---|---|---|---|
| POST | `/register` | `name`, `email`, `password`, `password_confirmation` | `201` com `message`, `token`, `data` |
| POST | `/login` | `email`, `password` | `200` com `message`, `token` |

Cadastro:

```json
{
  "name": "Eduardo",
  "email": "eduardo@example.com",
  "password": "senha1234",
  "password_confirmation": "senha1234"
}
```

Resposta `201`:

```json
{
  "message": "Registro realizado com sucesso.",
  "token": "1|token...",
  "data": {
    "id": 1,
    "name": "Eduardo",
    "email": "eduardo@example.com",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Login:

```json
{
  "email": "eduardo@example.com",
  "password": "senha1234"
}
```

Depois de login ou cadastro, salve `token` e envie-o em toda rota protegida:

```http
Authorization: Bearer 1|token...
Accept: application/json
```

Logout:

| Metodo | Rota | Body | Resposta |
|---|---|---|---|
| DELETE | `/logout` | nenhum | `204 No Content` |

O logout revoga o token atual. Depois de receber `204`, remova o token do `localStorage`.

## 3. Rotas de usuario

Todas exigem Bearer token.

| Metodo | Rota | Resposta |
|---|---|---|
| GET | `/me` | `200` com o usuario autenticado |
| GET | `/users` | `200` com array contendo o usuario autenticado |
| GET | `/users/{id}` | `200` somente se `{id}` for o usuario autenticado |
| POST | `/users` | `201` cria usuario |
| PUT/PATCH | `/users/{id}` | `200`, somente no proprio usuario |
| DELETE | `/users/{id}` | `204`, somente no proprio usuario |

Body para criar usuario:

```json
{
  "name": "Novo usuario",
  "email": "novo@example.com",
  "password": "senha1234"
}
```

Body parcial para atualizar:

```json
{
  "name": "Nome atualizado",
  "email": "novo@example.com"
}
```

## 4. Filamentos

Todos os filamentos pertencem ao usuario autenticado. Nunca envie `user_id`; o backend define esse campo pelo token.

| Metodo | Rota | Body | Resposta |
|---|---|---|---|
| GET | `/filaments` | nenhum | `200` array de filamentos do usuario |
| POST | `/filaments` | JSON abaixo | `201` filamento criado |
| GET | `/filaments/{id}` | nenhum | `200` filamento |
| PUT/PATCH | `/filaments/{id}` | campos parciais | `200` filamento atualizado |
| DELETE | `/filaments/{id}` | nenhum | `204` |

Body para criar:

```json
{
  "brand": "Voolt3D",
  "type": "PLA",
  "colorName": "Azul",
  "colorHex": "#0000FF",
  "pricePerKg": 89.90,
  "initialQuantity": 1000,
  "currentQuantity": 1000
}
```

Campos:

| Campo | Tipo | Regra |
|---|---|---|
| `brand` | string | obrigatorio, maximo 45 |
| `type` | string | obrigatorio, maximo 20 |
| `colorName` | string | obrigatorio, maximo 45 |
| `colorHex` | string | formato `#RRGGBB` |
| `pricePerKg` | number | maior que zero, ate 2 casas |
| `initialQuantity` | number | maior que zero, em gramas, ate 3 casas |
| `currentQuantity` | number | minimo zero, em gramas, nao maior que o inicial |

Resposta de exemplo:

```json
{
  "id": 10,
  "user_id": 1,
  "brand": "Voolt3D",
  "type": "PLA",
  "colorName": "Azul",
  "colorHex": "#0000FF",
  "pricePerKg": "89.90",
  "initialQuantity": "1000.000",
  "currentQuantity": "950.000",
  "created_at": "...",
  "updated_at": "..."
}
```

Os campos decimais podem retornar como **strings**. O frontend deve converter para numero antes de fazer calculos:

```ts
const currentQuantity = Number(filament.currentQuantity);
```

`currentQuantity` e o estoque real. `initialQuantity` e apenas a referencia inicial. Ao editar, envie `currentQuantity` explicitamente se a alteracao de estoque for intencional.

Um filamento vinculado a qualquer job nao pode ser excluido. Nesse caso a API responde `409`.

## 5. Jobs de impressao

Todos os jobs pertencem ao usuario autenticado e retornam a relacao `filaments` carregada.

| Metodo | Rota | Body | Resposta |
|---|---|---|---|
| GET | `/jobs` | nenhum | `200` array de jobs |
| POST | `/jobs` | `multipart/form-data` | `201` job criado |
| GET | `/jobs/{id}` | nenhum | `200` job |
| PUT/PATCH | `/jobs/{id}` | JSON ou multipart | `200` job atualizado |
| PATCH | `/jobs/{id}/status` | `{ "status": "..." }` | `200` job atualizado |
| DELETE | `/jobs/{id}` | nenhum | `204` |

### Criar job com um filamento

Use `FormData`:

```ts
const form = new FormData();
form.append('fileName', 'peca.stl');
form.append('status', 'BACKLOG');
form.append('filament_id', String(filamentId));
form.append('materialWeightGrams', '50');
form.append('estimatedTimeMinutes', '30');
form.append('quantity', '1');
form.append('layers', '20');
form.append('stl_file', file);

await api.post('/jobs', form);
```

Campos obrigatorios na criacao:

| Campo | Tipo | Regra |
|---|---|---|
| `fileName` | string | obrigatorio, maximo 255 |
| `status` | string | `BACKLOG`, `TODO`, `INPROGRESS`, `APPROVAL` ou `COMPLETED` |
| `filament_id` | integer | obrigatorio quando `filaments` nao for enviado |
| `materialWeightGrams` | number | peso por unidade, maior que zero |
| `filaments` | array | alternativa a `filament_id` |
| `estimatedTimeMinutes` | integer | minimo 1 |
| `quantity` | integer | minimo 1 |
| `layers` | integer | minimo 1 |
| `stl_file` | arquivo | STL, maximo 50 MB |

`estimatedCost` pode ser enviado, mas o backend calcula e persiste o custo com base no preco dos filamentos e nos pesos. O valor retornado pela API e a fonte de verdade.

### Criar job com varios filamentos

Em `FormData`, envie:

```text
filaments[0][filament_id] = 10
filaments[0][weight_grams] = 40
filaments[1][filament_id] = 11
filaments[1][weight_grams] = 10
```

Nao misture `filaments` com `filament_id`/`materialWeightGrams` no mesmo request.

### Resposta de job

```json
{
  "id": 20,
  "user_id": 1,
  "fileName": "peca.stl",
  "file_path": "stl_files/abc.stl",
  "status": "BACKLOG",
  "completed_at": null,
  "estimatedTimeMinutes": 30,
  "estimatedCost": "4.50",
  "quantity": 1,
  "materialWeightGrams": "50.00",
  "layers": 20,
  "created_at": "...",
  "updated_at": "...",
  "filaments": [
    {
      "id": 10,
      "brand": "Voolt3D",
      "type": "PLA",
      "colorName": "Azul",
      "colorHex": "#0000FF",
      "pricePerKg": "89.90",
      "pivot": {
        "print_job_id": 20,
        "filament_id": 10,
        "weight_grams": "50.00"
      }
    }
  ]
}
```

Para exibir ou baixar o STL, o campo retornado e `file_path`; ele nao e retornado como `stl_file`.

### Alterar status

```http
PATCH /api/jobs/20/status
Content-Type: application/json
```

```json
{
  "status": "COMPLETED"
}
```

Ao mudar para `COMPLETED`:

- `completed_at` recebe a data/hora atual;
- o estoque e reduzido por `peso de cada filamento x quantity`;
- se nao houver estoque suficiente, responde `422`;
- a operacao e transacional, portanto nao deve haver meia atualizacao.

O estoque nao e reduzido ao criar um job em `BACKLOG`, `TODO`, `INPROGRESS` ou `APPROVAL`.

Um job concluido nao pode mudar de status, quantidade ou materiais. Excluir um job concluido restaura o estoque consumido.

### Atualizar job com arquivo

Para atualizar arquivo e dados juntos, prefira `POST` com `_method=PUT`, pois uploads multipart com PUT podem nao ser interpretados corretamente pelo PHP:

```ts
const form = new FormData();
form.append('_method', 'PUT');
form.append('fileName', 'peca-v2.stl');
form.append('stl_file', file);
await api.post(`/jobs/${jobId}`, form);
```

## 6. Settings

| Metodo | Rota | Body | Resposta |
|---|---|---|---|
| GET | `/settings` | nenhum | `200` com `{ settings: {...} }` |
| PUT | `/settings` | `{ settings: {...} }` | `200` com `{ settings: {...} }` |

Body correto:

```json
{
  "settings": {
    "workspace": "Meu PrintHub",
    "currency": "BRL",
    "emailNotifications": true,
    "lowStockAlerts": true,
    "energyCost": 0.75,
    "printerPower": 250,
    "includeEnergyCost": true,
    "autoArchive": false,
    "autoUpdateInventory": true
  }
}
```

O frontend deve ler e enviar o envelope `settings`. `energyCost` e `printerPower` devem ser numeros; os campos de notificacao e comportamento devem ser booleans.

## 7. Erros padrao

| Status | Significado | Acao do frontend |
|---|---|---|
| `401` | token ausente, invalido ou credencial invalida | limpar sessao e voltar para login |
| `404` | recurso nao pertence ao usuario ou nao existe | informar que nao foi encontrado |
| `409` | conflito, por exemplo filamento usado por job | mostrar mensagem e atualizar dados |
| `422` | validacao ou estoque insuficiente | ler `message` e `errors` |
| `204` | sucesso sem corpo | nao tentar fazer `response.json()` |

Resposta de validacao:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Helper recomendado:

```ts
function apiErrorMessage(error: any): string {
  return error.response?.data?.message
    ?? Object.values(error.response?.data?.errors ?? {}).flat()[0]
    ?? 'Nao foi possivel concluir a operacao.';
}
```

## 8. Checklist de integracao do frontend

- [ ] Configurar `baseURL` como `http://127.0.0.1:8000/api`.
- [ ] Salvar o token recebido em login/cadastro.
- [ ] Enviar `Authorization: Bearer {token}` nas rotas protegidas.
- [ ] Remover o token ao receber logout ou `401`.
- [ ] Usar `/filaments` e respeitar `currentQuantity` como estoque real.
- [ ] Converter decimais retornados como strings com `Number(...)`.
- [ ] Usar exatamente os status `BACKLOG`, `TODO`, `INPROGRESS`, `APPROVAL`, `COMPLETED`.
- [ ] Criar jobs com `FormData` e campo `stl_file`.
- [ ] Para varios filamentos, usar `filaments[n][filament_id]` e `filaments[n][weight_grams]`.
- [ ] Enviar settings como `{ settings: {...} }`.
- [ ] Ler settings de `response.data.settings`.
- [ ] Usar `PATCH /jobs/{id}/status` para concluir jobs.
- [ ] Nao deduzir estoque no frontend; a API faz essa operacao.
- [ ] Nao assumir que `file_path` e uma URL publica pronta.
- [ ] Tratar `204` sem tentar ler JSON.
- [ ] Tratar `409` ao excluir filamento vinculado a job.
- [ ] Ao recarregar a pagina, sempre usar o GET da API como fonte de verdade.

## 9. Fluxo recomendado da aplicacao

1. Fazer login e guardar o token.
2. Buscar `/me`, `/filaments`, `/jobs` e `/settings`.
3. Criar ou editar filamentos usando os campos do contrato.
4. Criar jobs via `FormData`.
5. Mover o job entre os status usando `PUT/PATCH /jobs/{id}` ou concluir usando `PATCH /jobs/{id}/status`.
6. Ao concluir, recarregar `/filaments` e `/jobs` para refletir o estoque e `completed_at` persistidos.
7. Para relatorios, considerar apenas jobs com `status === 'COMPLETED'` e usar `completed_at` como data de conclusao.
