# Contrato de API e Endpoints

## Padrão JSON

Sucesso:

```json
{
  "success": true,
  "message": "Operação realizada com sucesso.",
  "data": {},
  "meta": null,
  "errors": []
}
```

Erro:

```json
{
  "success": false,
  "message": "Verifique os campos destacados.",
  "data": null,
  "meta": {
    "request_id": "req_123"
  },
  "errors": []
}
```

## Endpoints (fatia 1)

Base: `sistema/api/`. Todos usam o envelope oficial. `meta` carrega paginação.

| Método | Endpoint | Objetivo | Auth | Log? |
|---|---|---|---|---|
| POST | `instalar.php` | Cria o 1º admin (só se não houver usuário) | não | sim |
| GET  | `auth.php?acao=status` | Sistema instalado? sessão ativa? | não | não |
| POST | `auth.php?acao=login` | Login (`email`, `senha`) | não | sim |
| POST | `auth.php?acao=logout` | Encerra sessão | sim | não |
| GET  | `auth.php?acao=me` | Usuário logado | não | não |
| GET  | `referencias.php` | Listas de ENUMs + turmas p/ selects | sim | não |
| GET  | `contatos.php?acao=listar` | Lista paginada (filtros `q`, `tipo_contato`, `pagina`) | sim | não |
| GET  | `contatos.php?acao=obter&id=` | Contato + estilos + disponibilidade | sim | não |
| GET  | `contatos.php?acao=followup` | Contatos com próximo contato vencido/hoje | sim | não |
| GET  | `contatos.php?acao=resumo` | Contadores do dashboard | sim | não |
| POST | `contatos.php?acao=criar` | Cria contato | sim | sim |
| POST | `contatos.php?acao=atualizar&id=` | Atualiza contato | sim | sim |
| POST | `contatos.php?acao=excluir&id=` | Exclui contato | sim | sim |
| POST | `contatos_importar.php` | Importa CSV em lote como `nao_aluno` (ignora duplicados por whatsapp) | sim | sim |
| GET  | `matriculas.php?acao=turmas_resumo` | Turmas + nº de matriculados ativos | sim | não |
| GET  | `matriculas.php?acao=por_turma&turma_id=` | Alunos matriculados na turma | sim | não |
| GET  | `matriculas.php?acao=por_contato&contato_id=` | Turmas de um contato | sim | não |
| GET  | `matriculas.php?acao=buscar_contatos&q=` | Busca contatos para matricular | sim | não |
| POST | `matriculas.php?acao=criar` | Matricula contato em turma (bloqueia duplicada) | sim | sim |
| POST | `matriculas.php?acao=atualizar_status&id=` | ativa/pausada/cancelada | sim | sim |
| POST | `matriculas.php?acao=excluir&id=` | Remove matrícula | sim | sim |
| GET  | `presencas.php?acao=chamada&turma_id=&data=` | Alunos ativos + presença lançada na data | sim | não |
| POST | `presencas.php?acao=salvar` | Salva chamada em lote (upsert por aluno/turma/data) | sim | sim |
| GET  | `presencas.php?acao=frequencia&contato_id=` | Resumo de frequência do aluno | sim | não |

## Validações críticas (backend)

- `nome`, `whatsapp`, `tipo_contato` obrigatórios; whatsapp/cpf só dígitos.
- Status aceito conforme `tipo_contato` (`validar_enum`).
- Par vinculado deve existir e não ser o próprio contato.
- Datas em `AAAA-MM-DD`. Estilos/disponibilidade filtrados por lista permitida.

## Regras

- API valida permissão no backend.
- API não expõe erro técnico bruto.
- Frontend não decide regra crítica.
