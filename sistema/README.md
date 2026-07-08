# Roh & Sih — Sistema de CRM & Secretaria

Sistema de gestão para escola de dança de salão: contatos (CRM), follow-up,
alunos, turmas, presença, pagamentos, eventos, scripts de WhatsApp, indicações
premiadas e avaliações.

**Stack:** PHP procedural puro · MySQL/MariaDB · HTML/CSS/JS puro · Fetch · JSON.

## Estrutura

```
sistema/
  config/      config.php (env), db.php (PDO)  [protegido por .htaccess]
  api/         endpoints JSON (_bootstrap, auth, instalar, referencias, contatos)
  database/    schema.sql, seed.sql
  public/      frontend (index.html, css/, js/)  <- servir como raiz web
  storage/     videos/ (provas)  [protegido]
```

## Instalação (desenvolvimento)

1. Crie o banco e rode os scripts:
   ```sql
   CREATE DATABASE roh_e_sih CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   ```bash
   mysql -u root -p roh_e_sih < database/schema.sql
   mysql -u root -p roh_e_sih < database/seed.sql
   ```
2. Copie `.env.example` → `.env` e ajuste as credenciais (ou use variáveis de ambiente).
3. Aponte o **document root do Apache/PHP para a pasta `sistema/`**
   (assim `public/` fica acessível e `../api` resolve para `/api`; `config/` e
   `storage/` continuam bloqueados por `.htaccess`).
4. Acesse `/public/` no navegador. Na primeira vez, crie o **administrador**.

## Convenções

- Envelope JSON oficial: `{ success, message, data, meta, errors }`.
- Toda regra crítica é validada no **backend** (`api/`).
- Colunas de controle em português (`criado_em`, `atualizado_em`, ...).

## Módulos

| Módulo | Status |
|---|---|
| Autenticação + instalação | ✅ implementado |
| Contatos (CRM) + follow-up + dashboard | ✅ implementado |
| Importação de contatos por CSV (tipo por coluna) | ✅ implementado |
| Turmas / matrícula | ✅ implementado |
| Presença / frequência | ✅ implementado |
| Pagamentos / cobrança | 🟡 schema pronto |
| Eventos / inscrições | 🟡 schema pronto |
| Scripts de mensagem | 🟡 schema + seed prontos |
| Indicações premiadas | 🟡 schema pronto |
| Avaliações + vídeo da prova | 🟡 schema pronto |

O banco (`schema.sql`) já cobre **todos** os módulos; as próximas entregas plugam
API + tela sobre a base existente.
