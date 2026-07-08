# Changelog e Decisões

## Changelog

### [0.3.2] — 2026-07-08

#### Corrigido

- **Busca quebrada** em Contatos, Turmas e Eventos: as queries reusavam o mesmo
  placeholder `:q` em vários `LIKE`, o que falha com prepared statements nativos
  (`EMULATE_PREPARES=false`, erro HY093). Trocado por `CONCAT_WS(' ', ...) LIKE :q`
  (um único placeholder; ainda busca nome+sobrenome juntos).

### [0.3.1] — 2026-07-08

#### Adicionado

- Importação de contatos ganhou colunas **opcionais**: Par (com/sem), Papel
  (líder/seguidora), Estilo de interesse (vários por célula), Disponibilidade
  (vários) e Data do contato (aceita DD/MM/AAAA). Normalizadores no backend;
  estilos/disponibilidade gravam nas tabelas N:N. Modelo CSV atualizado.

### [0.3.0] — 2026-07-08

#### Adicionado

- Módulo **Eventos**: curso intensivo, baile/festa, workshop, turma regular.
  - `api/eventos.php` (CRUD + contadores) e `api/evento_inscricoes.php`
    (interessados, status, valor, follow-up).
  - Situação do interessado: negociando · reservado · pago · cancelado ·
    sem_interesse ("não tenho mais interesse").
  - Tela "Eventos" (lista + modal de evento + modal de interessados com edição
    inline de situação/valor/data de follow-up).
  - **Follow-up** agora tem 2 seções: contatos a retornar + negociações de
    eventos (negociando/reservado).

#### Banco (requer migration)

- `evento_inscricoes.status` passou a `negociando/reservado/pago/cancelado/sem_interesse`
  e ganhou coluna `data_followup`. Rodar `database/migrations/001_eventos_inscricoes_status.sql`
  no banco de produção (base sem inscrições ainda → seguro).

### [0.2.1] — 2026-07-08

#### Alterado

- Importação por CSV agora é de **contatos** (não mais só "não alunos"): o tipo
  é lido de uma coluna do arquivo, com **normalizador** (interpreta "Aluno",
  "Ex-aluno", "Não aluno", "Não contatar" e variações). Tipo padrão configurável
  para linhas sem tipo reconhecido; prévia mostra o tipo interpretado; resultado
  traz contagem por tipo. `status_nao_aluno` aplicado só a linhas `nao_aluno`.

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
