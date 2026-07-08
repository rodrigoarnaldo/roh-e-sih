# Changelog e Decisões

## Changelog

### [0.2.0] — 2026-07-08

#### Adicionado

- Módulo **Matrícula + Presença**:
  - `api/matriculas.php`: vincula contatos às turmas, lista por turma/contato,
    busca contatos, atualiza status (ativa/pausada/cancelada), impede matrícula
    duplicada. Tela "Turmas" com cards por turma e modal de gestão de alunos.
  - `api/presencas.php`: chamada por turma+data (upsert), "marcar todos
    presentes", e resumo de frequência por aluno. Tela "Presença".

### [0.1.2] — 2026-07-08

#### Adicionado

- Importação de contatos por **CSV** (tela "Importar"): lê o arquivo no navegador,
  mapeia colunas (com adivinhação automática), prévia, e importa em lote como
  `nao_aluno`. Endpoint `api/contatos_importar.php` valida no backend, ignora
  duplicados (por whatsapp, no arquivo e no banco) e retorna resumo
  (inseridos / ignorados / erros por linha).
- `config.php` passa a carregar `.env` como fallback das variáveis de ambiente
  (compatível com o "Create env file" do EasyPanel).

### [0.1.1] — 2026-07-08

#### Adicionado

- Infra de deploy para EasyPanel: `Dockerfile` (php:8.3-apache + PDO MySQL),
  `sistema/deploy/apache.conf` (docroot em `public/`, `Alias /api`), `php.ini` de
  produção, `docker-compose.yml` (dev: app + MySQL + phpMyAdmin com schema/seed).
- Health check `GET /health.php`.
- Repositório Git inicializado (branch `main`); `.gitignore` e `.dockerignore`.
- docs/13 (deploy) preenchida com passo a passo GitHub + EasyPanel.

#### Decisão de segurança

- Docroot = `sistema/public`; `config/`, `database/` e `storage/` ficam fora do
  webroot (não dependem de `.htaccess` para ficarem ocultos).

### [0.1.0] — 2026-07-08

#### Adicionado

- Estrutura do sistema em `sistema/` (config, api, database, public, storage).
- `schema.sql` cobrindo TODOS os módulos + `seed.sql` (turmas fixas, scripts).
- Autenticação por sessão + instalação do 1º admin (`password_hash`).
- Envelope JSON oficial e helpers de validação (`api/_bootstrap.php`).
- Módulo **Contatos (CRM)** completo: CRUD, filtros, paginação, follow-up,
  dashboard, estilos/disponibilidade (N:N), vínculo de par.
- Auditoria simples (`logs_auditoria`).

## Histórico de decisões

| Data | Decisão | Motivo | Impacto |
|---|---|---|---|
| 2026-07-08 | Código do sistema em `sistema/` (não na raiz) | Separar o app dos arquivos da biblioteca IA Dev | Docroot deve apontar para `sistema/` |
| 2026-07-08 | PDO + prepared statements (ainda procedural) | Segurança contra SQL injection sem sair da stack | Todas as queries parametrizadas |
| 2026-07-08 | Status por tipo em colunas separadas + validação no backend | Regra RN-002 (coerência tipo/status) | Integridade garantida no servidor |
| 2026-07-08 | Estilos e disponibilidade como tabelas N:N | Campos multivalorados | Regravação transacional ao salvar |
| 2026-07-08 | 1º admin criado via `instalar.php`, não no seed | Nunca gravar senha em texto plano no SQL | Setup em 1 passo no navegador |

## Versões publicadas

| Versão | Ambiente | Branch/tag | Observação |
|---|---|---|---|
|  | homologação/produção |  |  |
