# Changelog e Decisões

## Changelog

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
