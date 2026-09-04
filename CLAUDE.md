# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

SHIAI PRO is a PHP/MySQL SaaS system for managing martial arts academies (multi-tenant "unidades" = franchise units, each with one or more "academias" = physical gyms). The repo is a flat PHP codebase with no build step, no package manager (no `composer.json`/`package.json`), and no automated test suite — it is deployed directly via FTP/shared hosting (Apache + `.htaccess` + `mod_rewrite`).

It contains several largely independent parts:

- **`Gestor/`** — the core application: authenticated admin/management dashboard (PHP + MySQL via PDO, server-rendered pages, Bootstrap 5).
- **`Tema/`** — the public marketing site (static HTML/CSS/JS plus a couple of PHP form-handler scripts). `Tema/Site/` is a separate, unused/reference HTML template bundle — don't confuse it with the live pages directly under `Tema/`.
- **`Marketing/`** — a plain asset dump (images by month), not code.
- **`Ai/`** — brand/logo source assets (AI, PDF, PNG), not code.
- **`inscricao.php`** (repo root) — public, unauthenticated student-enrollment page reached via the friendly URLs rewritten in `.htaccess`.

There is no local dev server config, linter, formatter, or CI checked into the repo. "Testing" a change means reading the PHP for correctness and, where possible, exercising the page against the real MySQL schema — there are no automated tests to run.

Note: `Gestor/unidade/` contains its own initialized-but-empty nested `.git` directory; it is not connected to a remote and has no commits. Don't assume it tracks history independently of the outer repo.

## Architecture (Gestor — the core app)

### Two access tiers, same session/auth model

Every authenticated page starts with `require_once '../config.php'` (or `'config.php'` at the `Gestor/` root), which:
1. Opens a PDO MySQL connection as `$pdo` (credentials hardcoded in `Gestor/config.php` — this is a live secret, be careful not to leak it further, e.g. in commits, logs, or generated docs).
2. Starts the PHP session.
3. Defines the auth/permission helpers used everywhere: `estaLogado()`, `ehMestre()`, `ehAdmin()`, `getUnidadeId()`, `moduloAtivo($modulo)`, `temPermissaoModulo($modulo)`.

There are two parallel page trees, gated by `$_SESSION['nivel']`:
- **`Gestor/mestre/*.php`** — "mestre" (franchisor/super-admin) console. Manages all `unidades` (franchise units), plans, billing, marketing. Guarded by `ehMestre()`; `mestre/header.php` redirects to `../login.php` if not mestre.
- **`Gestor/unidade/*.php`** — per-unit dashboard used by a unit's own staff (admin, financeiro, secretaria, comercial, instrutor). Guarded by `estaLogado() && getUnidadeId()`; `unidade/header.php` redirects otherwise. This is by far the largest part of the app (students, classes/turmas, attendance, CRM, finance, store, blog, events, belts/faixas, etc.).

`login.php` authenticates against the `usuarios` table (`password_verify`), checks the associated unit's status (`unidades.status`) for non-mestre users, sets `$_SESSION['usuario_id'|'nome'|'nivel'|'unidade_id']`, logs every attempt to `login_logs`, and redirects to `mestre/index.php` or `unidade/index.php` based on `nivel`.

### Page conventions (follow these when adding/editing pages)

- Every protected page: `require_once '../config.php';` then `include 'header.php';` / `include 'footer.php';` to wrap content (header handles the auth guard + sidebar/nav chrome, footer closes it).
- **Module gating**: `unidade/` pages that correspond to an optional feature check `moduloAtivo('modulo_key')` first (unit's enabled modules are stored as a JSON array in `unidades.modulos`), rendering a locked-state message if the module isn't active for that unit's plan.
- **Fine-grained permissions**: after the module check, pages call `temPermissaoModulo('modulo_key_acao')` (e.g. `'turmas_visualizar'`) to check the logged-in user's role-based permission, stored per unit/role in `unidade_permissoes`. `ehMestre()` and `nivel === 'admin'` bypass this and always pass.
- **Tenant isolation**: nearly every query is scoped by `unidade_id = ?` (and PDO prepared statements are used throughout — keep using them, never string-concatenate user input into SQL). When writing new queries, always filter by the current `getUnidadeId()` to avoid cross-tenant data leaks, and re-verify ownership (e.g. `WHERE id = ? AND unidade_id = ?`) before acting on any record ID that came from the client.
- **AJAX endpoints**: files named `api_*.php` under `unidade/` (e.g. `api_chamada_salvar.php`, `api_agenda_salvar.php`, `api_criar_subcategoria.php`) read JSON from `php://input`, set `Content-Type: application/json`, and return `{success, message, ...}` payloads. Follow this same shape for new endpoints.
- **CRUD naming pattern**: list pages (`turmas.php`, `alunos.php`, ...) pair with `novo_*.php` (create) and `editar_*.php` (edit) files, e.g. `turmas.php` / `nova_turma.php` / `editar_turma.php`. Follow this naming convention for new entities.
- **Auto-migration pattern**: some scripts defensively `ALTER TABLE` / `CREATE TABLE IF NOT EXISTS` at the top of a request if an expected column/table is missing (see `login.php`'s `login_logs` creation, `api_chamada_salvar.php`'s `atualizado_em` column check). This is the repo's de facto migration mechanism — there is no separate migrations directory/tool.
- **Uploads**: use `getUploadPath($tipo, $academia_id = null)` / `getUploadURL(...)` from `config.php` rather than hardcoding paths — they namespace files under `Gestor/uploads/u_{unidade_id}/...`.

### Payments (Asaas integration)

`Gestor/assets/lib/AsaasClient.php` is a small cURL wrapper around the Asaas payment API (sandbox/production toggle in the constructor). Both `mestre/config_asaas.php` (franchisor billing) and `unidade/config_asaas.php` (per-unit billing) integrate with it; `mestre/faturas.php` handles invoices. When touching payment code, preserve the existing error-surfacing behavior (`AsaasClient` bubbles up the API's `errors[0].description` on HTTP >= 400).

### `Gestor/scratch/`

One-off debug/inspection scripts (e.g. `check_db.php`, `debug_financeiro.php`) meant to be hit directly in a browser during manual debugging. Not part of the app flow — don't wire pages into them, and treat them as disposable/ad hoc.

## Architecture (Tema — public marketing site)

Static HTML pages (`index.html`, `produto.html`, `sobre-nos.html`, `pricing`/`faq`/etc. under `Tema/Site/`) plus two PHP glue scripts:
- `pre-lancamento-action.php` — handles the pre-launch lead form POST, inserts into `comercial_leads` (associates the lead with whichever `unidade` row happens to be first in the table — see the code before changing this if unit assignment matters).
- shares `Gestor/config.php` for DB access (`require_once 'Gestor/config.php'`), so it's coupled to the same MySQL schema as the main app.

CSS/JS are plain (`Tema/css`, `Tema/js`) — no bundler/preprocessor.

## Routing

`.htaccess` (repo root) rewrites friendly enrollment URLs to `inscricao.php`:
- `/SLUG/inscricao` → `inscricao.php?slug=SLUG`
- `/SLUG/inscricao/TURMA-UUID` → `inscricao.php?slug=SLUG&t=UUID`

`inscricao.php` is public/unauthenticated — it looks up a `turmas` row by `uuid_inscricao` + the unit's `slug`, both of which must have `status = 'ativo'`.

## Language/locale

All UI copy, variable names, table/column names, and code comments are in Brazilian Portuguese (pt-BR). Keep new code consistent with this — don't switch to English identifiers or copy in existing files.
