# Prompt — Padronizar Caminhos da Biblioteca

Use este prompt com uma IA de programação para corrigir referências internas da biblioteca.

```txt
Você vai revisar a biblioteca de IA e padronizar todos os caminhos internos.

Leia primeiro:

1. README.md
2. manifesto-biblioteca.md
3. checklist-integridade-biblioteca.md
4. padronizacao-caminhos.md
5. orquestrador.md
6. controle-projeto.md

Estrutura oficial:

/docss
/prompts
/protocols
/skills
/speciality

Tarefa:
1. Procurar em todos os arquivos `.md` referências antigas:
   - docs/
   - /docs
   - protocols/
   - /protocols
   - speciality/
   - /speciality
   - speciality/
   - /speciality

2. Substituir pelos caminhos oficiais:
   - docs/
   - /docss
   - protocols/
   - /protocols
   - speciality/
   - /speciality

3. Não renomear arquivos de especialista, protocolo ou skill.
   Exemplo:
   - manter `especialista-banco-dados.md`
   - manter `protocolo-criacao-projeto-zero.md`
   - manter `skill-mysql.md`

4. Atualizar:
   - README.md
   - orquestrador.md
   - controle-projeto.md
   - docs/*.md
   - prompts/*.md
   - protocols/*.md
   - speciality/*.md
   - skills/README.md, se necessário

5. Gerar relatório:
   - arquivos revisados;
   - arquivos alterados;
   - substituições feitas;
   - referências antigas que ainda restaram;
   - checklist final.

Regras:
- Não alterar conteúdo técnico sem necessidade.
- Não mudar stack.
- Não remover regras.
- Não apagar arquivos.
- Alterar apenas caminhos e referências de estrutura.
- Ao final, atualizar controle-projeto.md com o checkpoint da padronização.
```
