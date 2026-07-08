# Briefing e Regras de Negócio

## Problema que o sistema resolve

A escola gerencia contatos e alunos manualmente (WhatsApp/planilha), perdendo
follow-ups, cobranças e histórico. Precisa centralizar o funil de vendas e a
secretaria em um só lugar.

## Processo atual

Lead chega pelo WhatsApp → conversa informal → matrícula sem registro
estruturado → cobrança e presença controladas de forma solta.

## Processo desejado

Cadastro único do contato → classificação e status → follow-up por data →
matrícula em turma → presença → cobrança → eventos → avaliação. Mensagens de
venda padronizadas para copiar no WhatsApp; indicações premiadas para crescer.

## Domínio: classificação do contato

Um contato tem um **tipo** e, conforme o tipo, um **status** específico:

| tipo_contato | status válido |
|---|---|
| `nao_aluno` | não conhece · conhece não quer · conhece não quer agora · conhece quer |
| `aluno` | novo · fidelizado (+ plano: mensalista / trimestral / anual) |
| `ex_aluno` | quer voltar · não quer voltar · quer voltar mas não agora |
| `nao_contatar` | bloqueou · mal educado · concorrência · pediu para sair |

Outros campos: whatsapp, nome/sobrenome, cidade/uf, cpf, nascimento, par
(com/sem) e vínculo com o par (outro cadastro), papel (líder/seguidora), origem,
estilos de interesse (vaneira, forró, samba, sertanejo — vários), disponibilidade
(seg/ter/qua/qui — vários), datas de relacionamento (matrícula, primeiro/último/
próximo contato, pausa).

Grade de turmas fixa: Seg/Ter/Qua/Qui em 19:30 e 20:40.

## Regras de negócio

| Código | Regra | Status |
|---|---|---|
| RN-001 | Todo contato exige `nome`, `whatsapp` e `tipo_contato`. | ativo |
| RN-002 | O `status` deve ser coerente com o `tipo_contato` (validado no backend). | ativo |
| RN-003 | Campos de aluno (plano, status_aluno, data_matrícula) só valem para `tipo=aluno`. | ativo |
| RN-004 | CPF, quando informado, deve ter 11 dígitos; WhatsApp ao menos 10. | ativo |
| RN-005 | `nao_contatar` não aparece na fila de follow-up. | ativo |
| RN-006 | Follow-up = contatos com `data_proximo_contato <= hoje`. | ativo |
| RN-007 | O par vinculado deve ser outro cadastro existente (não ele mesmo). | ativo |
| RN-008 | Nota média da avaliação = média dos 6 critérios (calculada no backend). | planejado |
| RN-009 | Indicação premiada: registra indicador → indicado e o prêmio concedido. | planejado |

## Regras críticas

- Toda validação de tipo/status/obrigatoriedade ocorre no **backend**.
- Exclusão de contato exige confirmação (frontend) e é auditada (backend).
- Estilos e disponibilidade são regravados por transação ao salvar.

## Dúvidas abertas

| Dúvida | Impacto | Status |
|---|---|---|
| Um aluno pode estar em mais de uma turma? (schema permite N:N) | matrícula | assumido: sim |
| Pagamento é por competência mensal ou por plano fechado? | cobrança | a confirmar |
| Vídeo da prova: upload no servidor ou link externo (YouTube)? | avaliação | schema suporta arquivo; a confirmar |
