# Contexto do projeto — Dashboard de Faturamento (Laravel)

> Este arquivo é lido automaticamente pelo Claude (Cowork/Claude Code) ao abrir a pasta.
> Ele resume o que é o sistema, como está construído e as regras importantes.

## O que é

Dashboard consolidado de faturamento das **3 empresas do Renan** — **Bella Primavera**, **Linda Casa** e **GV Casa Shop** — consumindo a **API v3 do Tiny ERP** (REST/OAuth2) de cada empresa. Mostra faturamento do mês, projeção, hoje vs. ontem, quebra por empresa e por canal, e uma matriz empresa × canal, sempre comparando com o mês anterior no mesmo período. Tem auto-reload ("Live").

> **Histórico:** é uma reescrita em Laravel de um sistema anterior em Python (que rodava numa VM Oracle). A lógica de negócio (mapeamento de status, canais, sincronização incremental) veio de lá.

## Stack

- **Laravel 12**, **PHP 8.2**
- **Laravel Breeze** (Blade + Tailwind + Alpine), build via **Vite**
- **MySQL** no ambiente local (XAMPP) · **PostgreSQL** no Laravel Cloud (produção)
- Sem fila/worker ainda — sincronização roda via comando agendado (scheduler)

## Ambientes

| Ambiente | Onde | Banco | Observação |
|---|---|---|---|
| Local (dev) | XAMPP, `C:\xampp\htdocs\dashboard-faturamento`, `http://localhost/dashboard-faturamento/public` | MySQL | Banco vazio; **não** conectar no Tiny real (ver Regras) |
| GitHub | branch `main` | — | `git push` no `main` dispara deploy no Cloud |
| Laravel Cloud | domínio `*.laravel.cloud` | Postgres | Scheduler ligado; deploy command `php artisan migrate --force` |

## Mapa do código

| Caminho | Função |
|---|---|
| `config/tiny.php` | Empresas (bella/linda/gv), URLs OAuth, `redirect_uri`, filtro de status, aliases de canal, timezone. Credenciais vêm do `.env` (`TINY_*`). |
| `app/Models/User.php` | Usuário + flag `is_admin`. |
| `app/Models/TinyToken.php` | Tokens OAuth por empresa (refresh/access). |
| `app/Models/Order.php` | Pedido: `company`, `tiny_order_id` (único por empresa), `order_date`, `value`, `status_code`, `channel`. |
| `app/Models/SyncLog.php` | Log de cada execução do sync (status, totais, mensagem). |
| `app/Services/Tiny/TinyClient.php` | OAuth2 (refresh com rotação de token) + `GET /pedidos` paginado com retry/backoff (429/5xx). |
| `app/Services/Tiny/OrderSyncService.php` | Sincronização: `full`, `incremental`, `--month`, `--from/--to`. Dia a dia, aplica filtro de status, faz upsert. Empresa não conectada é **pulada** (não derruba o sync). |
| `app/Services/Tiny/DashboardAggregator.php` | Agrega `orders` no payload do painel (KPIs, projeção, hoje vs. ontem, por empresa, por canal, matriz). **Queries portáveis** MySQL/Postgres. |
| `app/Services/Tiny/ChannelNormalizer.php` | Normaliza nome do canal via aliases. |
| `app/Console/Commands/TinyOAuthCommand.php` | `php artisan tiny:oauth <empresa>` — bootstrap OAuth interativo (cola o `code`). Uso local/CLI. |
| `app/Console/Commands/TinySyncCommand.php` | `php artisan tiny:sync` — opções `--mode=incremental|full`, `--company=`, `--month=YYYY-MM`, `--from=`/`--to=`. |
| `routes/console.php` | Agendamento: `tiny:sync --mode=incremental` a cada 5 min, 8h–22h, todos os dias, `onOneServer()`. |
| `app/Http/Controllers/DashboardController.php` | Monta o dashboard (seletor de mês via `?month=`). |
| `app/Http/Controllers/StatusController.php` | Tela de saúde (`/sync-status`): última sync, conexões, erros. |
| `app/Http/Controllers/TinyOAuthController.php` | Fluxo OAuth **via web** (botão "Conectar" no Status) — usado no Cloud, onde o comando interativo não roda. |
| `app/Http/Controllers/Admin/UserController.php` | CRUD de usuários (só admin). |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Middleware `admin`. |
| `resources/views/dashboard.blade.php` | Painel "Live" (tema escuro), com auto-reload via Alpine. |
| `resources/views/status.blade.php`, `admin/users/*` | Telas no mesmo tema escuro. |

## Rotas principais

- `/dashboard` — painel (auth + verified)
- `/sync-status` (nome de rota `status`) — saúde do sync. **Path renomeado**: o edge do Laravel Cloud bloqueia `/status` (403).
- `/admin/users` — gestão de usuários (admin)
- `/tiny/{company}/connect` e `/tiny/callback` — OAuth web (admin)

## Regras de negócio (Tiny v3)

- **Status (códigos numéricos):** 0=Aberta, 1=Faturada, 2=Cancelada, 3=Aprovada, 4=Preparando Envio, 5=Enviada, 6=Entregue, 7=Pronto Envio, 8=Dados Incompletos, 9=Não Entregue.
- **Filtro de faturamento:** `["1","3","4","5","6","7","9"]` (exclui Aberta, Cancelada, Dados Incompletos). Em `config/tiny.php > status_filter`.
- **Canais:** normalizados por `channel_aliases`; sem canal cai em "Sem canal".
- **Incremental** re-busca os **últimos 2 dias** por `dataPedido` (o filtro `dataAlteracao` da v3 dá HTTP 400). ⚠️ Limitação conhecida: mudança de status num pedido com mais de 2 dias **não** é capturada pelo incremental — precisa de um `full`/`--month` de reconciliação.

## Regras importantes (NÃO violar)

1. **Não conectar o ambiente local no Tiny real.** O Tiny rotaciona o refresh_token a cada uso; se local e Cloud usarem as mesmas credenciais, um dos dois pega **401** e para. No `.env` local, deixe `TINY_*` em branco. Conexão/sync com o Tiny é só no Cloud.
2. **Nunca commitar `.env`** (está no `.gitignore`). Segredos só nas env vars do Cloud.
3. **SQL portável.** Local é MySQL, Cloud é Postgres. Evite funções específicas (`DATE_FORMAT`, `DAYOFMONTH`, etc.); o `DashboardAggregator` usa `whereBetween`/`whereDate`/`COALESCE`/`NULLIF` de propósito.
4. **Backfill grande estoura o limite de 30 min** dos Commands do Cloud. Para reprocessar histórico pesado, fatie com `--month=YYYY-MM` ou `--from/--to`. (Migrar o sync para fila/worker está na lista de melhorias.)
5. Mexeu em **views/Tailwind**? Rode `npm run build` (local). No Cloud o build é automático no deploy.

## Estado atual (junho/2026)

- **Linda Casa:** conectada e sincronizando (~20 mil pedidos de maio). **Junho** pendente de backfill (o `full` estourou o tempo no volume de maio — usar `--month=2026-06`).
- **Bella Primavera / GV Casa Shop:** ainda **não conectadas** (faltam credenciais/acesso). Quando tiver, setar `TINY_BELLA_*`/`TINY_GV_*` no Cloud → redeploy → botão "Conectar" no Status.

## Melhorias sugeridas (boas primeiras tarefas)

1. **Sync via fila/worker** (elimina o limite de 30 min do Command).
2. **Reconciliação periódica** (`--month` do mês corrente 1x/dia) pra pegar cancelamentos/mudanças de status antigos.
3. **Alerta de sync parado** (e-mail se não houver sync OK há X horas).
4. **Cache da agregação** (~60s) — alivia o banco com o auto-reload.
5. **Testes** da matemática (delta, projeção, matriz).
6. **Página de diagnóstico** por código de status (validar faturamento vs. Tiny).

## Fluxo de trabalho

```
git checkout -b minha-feature
# editar; npm run dev (ou build) se mexer em views
git add . && git commit -m "..."
git push -u origin minha-feature   # abrir PR; merge no main = deploy no Cloud
```

Mais detalhes de setup da máquina em `SETUP-RENAN.md`.
