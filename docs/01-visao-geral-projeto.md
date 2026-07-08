# Visão Geral do Projeto

## Identificação

```txt
Nome do projeto: Roh & Sih — CRM & Secretaria
Cliente/empresa: Escola de Dança de Salão Roh & Sih
Responsável: rodrigo.arnaldo@gmail.com
Data de início: 2026-07-08
Última atualização: 2026-07-08
Status: em desenvolvimento (fatia 1 entregue)
```

## Objetivo

Sistema de **CRM e secretaria** para a escola de dança de salão Roh & Sih. A
escola recebe mensagens pelo WhatsApp e faz toda a gestão de relacionamento,
matrículas, presença, pagamentos, eventos e avaliações por este sistema.

Objetivo do cliente: "ter tudo na palma da mão", saber com quem falar, fazer
follow-up, cobrar em dia, controlar frequência — tudo que ajuda a **fidelizar e
vender**.

## Stack oficial

```txt
PHP procedural puro
MySQL/MariaDB
phpMyAdmin
HTML
CSS
JavaScript puro
Fetch API
JSON
Git/GitHub
Docker/EasyPanel quando aplicável
Apache ou Nginx
Domínio com SSL
```

## Usuários

| Perfil | Objetivo no sistema |
|---|---|
| admin | Acesso total; cria usuários; toda gestão |
| secretaria | Operar CRM, follow-up, matrículas, cobranças, presença |

## Ambientes

| Ambiente | URL | Banco | Status |
|---|---|---|---|
| desenvolvimento | local (docroot em `sistema/`) | roh_e_sih | ativo |
| homologação |  |  | pendente |
| produção |  |  | pendente |

## Escopo entregue (fatia 1)

- Banco de dados completo (todos os módulos modelados em `sistema/database/schema.sql`).
- Autenticação da secretaria + instalação do primeiro admin.
- Módulo **Contatos (CRM)**: cadastro com todos os campos pedidos, filtros,
  paginação, follow-up por data e dashboard de resumo.

## Escopo planejado (próximas fatias)

Turmas/matrícula, presença/frequência, pagamentos/cobrança, eventos/inscrições,
scripts de mensagem (WhatsApp), indicações premiadas e avaliações com vídeo da
prova. O schema já cobre todos; falta API + tela de cada um.

## Localização do código

Pasta `sistema/` (separada dos arquivos da biblioteca IA Dev). Instalação em
`sistema/README.md`.
