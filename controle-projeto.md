# Controle e Memória do Projeto

> Este arquivo deve ficar ao lado do `orquestrador.md`.
>
> Função: registrar onde o projeto está, o que já foi feito, quais decisões foram tomadas e quais são os próximos passos.
>
> Atualize este arquivo sempre que houver mudança relevante.

---

# 1. Identificação do projeto

```txt
Nome do projeto: Roh & Sih — CRM & Secretaria
Cliente/empresa: Escola de Dança de Salão Roh & Sih
Responsável: rodrigo.arnaldo@gmail.com
Data de início: 2026-07-08
Última atualização: 2026-07-09
Status geral: em desenvolvimento (fatia 1 entregue)
```

Status sugeridos:

```txt
planejamento
em desenvolvimento
em homologação
em produção
em manutenção
pausado
```

---

# 2. Visão geral

## Objetivo do projeto

```txt
Descrever em poucas linhas o que o sistema faz e qual problema resolve.
```

## Stack definida

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
Docker/Docker Compose quando aplicável
EasyPanel
Apache ou Nginx
Domínio com SSL
```

## Observações importantes

```txt
Registrar restrições, decisões gerais, dependências externas ou cuidados especiais.
```

---

# 3. Etapa atual

```txt
Etapa atual: backend + frontend do módulo Contatos entregue; próximos módulos pendentes
Protocolo em uso: protocolo-criacao-projeto-zero.md
Especialista principal: especialista-produto-planejamento.md
Especialistas de apoio: especialista-banco-dados.md, especialista-backend-api-php.md, especialista-frontend-tecnico.md, especialista-seguranca-auditoria.md
Skills principais: skill-arquitetura, skill-dados, skill-mysql, skill-backend, skill-api-rest, skill-frontend, skill-fetch, skill-seguranca
```

---

# 4. Progresso por fase

| Fase | Status | Observação |
|---|---|---|
| Briefing | feito | docs/02 |
| Perfis e permissões | em andamento | admin/secretaria; RBAC fino pendente |
| Arquitetura | feito | pasta `sistema/`, docs/05 |
| Mapa de telas | em andamento | login, dashboard, contatos, follow-up |
| Design/UX/UI | em andamento | tema roxo/rosa em public/css |
| Banco de dados | feito | schema.sql cobre todos os módulos |
| Backend/API/PHP | em andamento | auth+referencias+contatos ok; demais pendentes |
| Frontend | em andamento | módulo Contatos ok; demais pendentes |
| Segurança/auditoria | em andamento | sessão, PDO, hash, logs; falta CSRF/rate-limit |
| QA/testes | pendente | sem runtime PHP no ambiente de dev da IA |
| Documentação | feito | docs 01,02,08,09,15 + sistema/README |
| Git/GitHub | feito | pushado em github.com/rodrigoarnaldo/roh-e-sih (branch main) via SSH |
| Docker/EasyPanel | feito | serviços MySQL + App criados; deploy ok; health.php respondendo |
| Homologação | pendente | validação direto em produção por enquanto |
| Produção | em andamento | app no ar via EasyPanel, banco conectado e schema importado |
| Monitoramento | pendente | falta backup automático, rollback e alertas |

Status sugeridos:

```txt
pendente
em andamento
bloqueado
feito
validado
revisar
```

---

# 5. Histórico de decisões

Registre decisões importantes para evitar que outra IA ou programador refaça discussão já resolvida.

| Data | Decisão | Motivo | Impacto | Quem decidiu |
|---|---|---|---|---|
| 2026-07-09 | Matricular um contato numa turma promove automaticamente `tipo_contato` para `aluno` (exceto `nao_contatar`). Cancelar/excluir a última matrícula ativa/pausada rebaixa para `ex_aluno`. | Evitar que alguém já em turma continue tratado como "não aluno" no CRM/follow-up. | `api/matriculas.php` (criar/atualizar_status/excluir) passa a alterar `contatos.tipo_contato` e sincronizar os `status_*`. Sem migration. | rodrigo.arnaldo |

Exemplos de decisões:

```txt
Stack escolhida
Nome de tabelas
Regras de permissão
Padrão de status
Fluxo de pagamento
Estratégia de deploy
Domínio
Banco por ambiente
```

---

# 6. O que já foi feito

| Data | Item concluído | Arquivos afetados | Evidência | Observação |
|---|---|---|---|---|
|  |  |  |  |  |

Exemplos de evidência:

```txt
commit
print
log
payload
resposta de API
checklist
link de homologação
arquivo gerado
```

---

# 7. Próximos passos

## Próxima ação imediata

```txt
Descrever exatamente o próximo passo.
```

## Lista de próximos passos

| Ordem | Próximo passo | Responsável | Prioridade | Status |
|---:|---|---|---|---|
| 1 |  |  | alta | pendente |
| 2 |  |  | média | pendente |
| 3 |  |  | baixa | pendente |

---

# 8. Pendências e bloqueios

| Tipo | Descrição | Impacto | Precisa decisão de quem? | Status |
|---|---|---|---|---|
| pendência |  |  |  |  |
| bloqueio |  |  |  |  |

Tipos sugeridos:

```txt
pendência
bloqueio
dúvida
risco
decisão necessária
dependência externa
```

---

# 9. Riscos conhecidos

| Risco | Área | Gravidade | Mitigação | Status |
|---|---|---|---|---|
|  |  | baixa/média/alta/crítica |  |  |

Áreas comuns:

```txt
produto
banco
backend
frontend
segurança
pagamento
integração
deploy
produção
```

---

# 10. Arquivos e pastas importantes

## Estrutura principal

```txt
/
  README.md
  orquestrador.md
  controle-projeto.md
  /protocols
  /speciality
  /skills
```

## Arquivos do projeto real

| Caminho | Função | Observação |
|---|---|---|
|  |  |  |

---

# 11. Banco de dados

```txt
Banco usado:
Ambiente atual:
Host:
Administração visual:
```

## Ambientes

| Ambiente | Banco | Usuário | Observação |
|---|---|---|---|
| desenvolvimento |  |  |  |
| homologação |  |  |  |
| produção |  |  |  |

## Migrations / alterações estruturais

| Data | Migration/SQL | Ambiente | Backup feito? | Status |
|---|---|---|---|---|
|  |  |  | sim/não |  |

---

# 12. Deploy e produção

```txt
Painel:
Servidor:
Repositório:
Branch desenvolvimento:
Branch homologação:
Branch produção:
Domínio:
SSL:
Status do deploy:
```

## Checklist rápido de produção

```md
- [ ] Código versionado.
- [ ] Branch/tag correta.
- [ ] .env configurado fora do Git.
- [ ] Banco criado.
- [ ] MySQL não exposto publicamente.
- [ ] phpMyAdmin protegido.
- [ ] Volumes persistentes validados.
- [ ] Domínio configurado.
- [ ] SSL funcionando.
- [ ] Backup feito.
- [ ] Rollback definido.
- [ ] Health check funcionando.
- [ ] Logs ativos.
```

---

# 13. Último checkpoint

Use esta seção para outra IA ou programador continuar exatamente de onde parou.

```txt
Última coisa feita:
Regra de negócio nova em api/matriculas.php: ao criar/reativar matrícula o contato
é promovido a tipo_contato='aluno' (exceto 'nao_contatar'), com status_aluno='novo'
e limpeza dos status_* de outros tipos; ao cancelar/excluir a última matrícula
ativa/pausada o contato volta a 'ex_aluno'. Funções promoverParaAluno() e
reverterSeSemTurma(). Sem migration (enums já cobrem). Falta commit + deploy.

Antes disso: Deploy em PRODUÇÃO no EasyPanel concluído: serviços MySQL (roh-e-sih-db)
e App (roh-e-sih-app) criados, DB_HOST corrigido para o host interno
"roh-e-sih_roh-e-sih-db", schema+seed importados e health.php respondendo
banco: ok. Também entregue a importação de não alunos por CSV (tela + endpoint).
Repo: github.com/rodrigoarnaldo/roh-e-sih (push via SSH deste ambiente).

Estado atual:
Sistema NO AR. Funcionando: login/instalação, Contatos (CRM), importação CSV,
Matrícula (Turmas), Presença (chamada + frequência) e Eventos (inscrições +
follow-up). Módulos ainda SEM API/tela (schema pronto): pagamentos, scripts de
mensagem, indicações, avaliações.

ATENÇÃO: o módulo Eventos exige rodar a migration
database/migrations/001_eventos_inscricoes_status.sql no banco de produção
(altera enum de evento_inscricoes.status e adiciona data_followup) ANTES de usar
a tela de interessados. Deploy é por push no GitHub + Deploy manual no EasyPanel.

Próximo passo recomendado:
1) `git remote add origin <url>` + `git push -u origin main` (repo já commitado).
2) No EasyPanel: criar serviço MySQL, importar schema.sql+seed.sql, criar serviço
   App (build por Dockerfile), setar env (DB_* + APP_AMBIENTE=producao), domínio+SSL,
   volume em /var/www/html/storage/videos. Depois abrir o domínio e criar o admin.
3) Testar login/Contatos em produção; depois implementar Pagamentos ou Matrícula/
   Presença reusando o padrão de contatos.php + app.js.

Arquivos que devem ser lidos primeiro:
sistema/README.md, sistema/database/schema.sql, sistema/api/_bootstrap.php,
sistema/api/contatos.php, sistema/public/js/app.js, docs/02 e docs/09.

Cuidados antes de continuar:
Não há git neste diretório — sem histórico nem rollback. Não expor config/ nem
storage/ na web (docroot = sistema/). Manter validação de regra no backend.
Não versionar .env real.

O que não deve ser alterado agora:
O contrato do envelope JSON, os nomes de tabelas/colunas do schema já criado e a
estrutura de pastas sistema/ — mudanças aí exigem decisão registrada na seção 5.
```

---

# 14. Resumo para próxima IA ou programador

```txt
Contexto rápido:

O projeto está na etapa:

Já foi decidido:

Já foi implementado:

Está pendente:

Principal risco:

Próxima ação:
```

---

# 15. Regra de atualização obrigatória

Atualizar este arquivo sempre que houver:

- nova decisão técnica;
- mudança de fase;
- criação de módulo;
- alteração de regra de negócio;
- criação ou alteração de tabela;
- criação ou alteração de endpoint;
- mudança em permissão;
- correção de bug relevante;
- deploy;
- homologação;
- incidente;
- mudança de domínio, banco, porta ou ambiente;
- entrega final.

---

# 16. Regra final

```txt
orquestrador.md decide o caminho.
protocolos conduzem o processo.
especialistas analisam.
skills orientam.
controle-projeto.md guarda a memória.
```

Se este arquivo estiver desatualizado, a próxima IA ou programador pode tomar decisão errada.
