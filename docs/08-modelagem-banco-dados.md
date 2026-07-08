# Modelagem de Banco de Dados

Script oficial: `sistema/database/schema.sql` (+ `seed.sql`).
Banco `roh_e_sih`, InnoDB, utf8mb4. Colunas de controle em português.

## Tabelas

| Tabela | Objetivo | Status |
|---|---|---|
| `usuarios` | Login da secretaria/admin (senha em hash) | ✅ |
| `turmas` | Grade fixa (Seg/Ter/Qua/Qui × 19:30/20:40) | ✅ |
| `contatos` | Núcleo do CRM (todos os campos e status por tipo) | ✅ |
| `contato_estilos` | N:N estilos de interesse | ✅ |
| `contato_disponibilidade` | N:N dias disponíveis | ✅ |
| `matriculas` | Aluno × turma | ✅ schema |
| `presencas` | Presença/falta por aula | ✅ schema |
| `pagamentos` | Mensalidade, vencimento, próxima cobrança | ✅ schema |
| `eventos` | Baile / curso intensivo / workshop / turma regular | ✅ schema |
| `evento_inscricoes` | Inscrição (negociando/reservado/inscrito/cancelado) | ✅ schema |
| `scripts_mensagem` | Scripts de venda para WhatsApp | ✅ schema+seed |
| `indicacoes` | Indicação premiada (indicador → indicado) | ✅ schema |
| `avaliacoes` | Notas dos 6 critérios + média + vídeo da prova | ✅ schema |
| `logs_auditoria` | Rastro de ações (criar/atualizar/excluir/login) | ✅ |

## Relacionamentos-chave

- `contatos.par_contato_id` → `contatos.id` (auto-relacionamento: o par).
- `contatos.criado_por` → `usuarios.id`.
- Tabelas N:N e filhas usam `ON DELETE CASCADE` a partir de `contatos`.
- `indicacoes.indicado_contato_id` usa `ON DELETE SET NULL` (guarda histórico).

## Status por tipo de contato (ENUMs)

- `tipo_contato`: nao_aluno · aluno · ex_aluno · nao_contatar
- `status_nao_aluno`, `status_ex_aluno`, `status_nao_contatar`, `status_aluno`,
  `plano`, `papel`, `par_situacao`, `estilo`, `dia` — ver `schema.sql`.

## Campos padrão

```txt
criado_em
atualizado_em
excluido_em
criado_por
atualizado_por
excluido_por
status
```

## Migrations

| Arquivo | Objetivo | Backup? | Status |
|---|---|---|---|
|  |  | sim/não |  |

## Regras

- Não alterar produção sem backup.
- Não criar campo sem regra.
- Não misturar padrões de nomes.
