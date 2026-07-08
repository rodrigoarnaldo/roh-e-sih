# Deploy, EasyPanel e Produção

## Arquitetura de deploy

- Imagem única da aplicação via `Dockerfile` (php:8.3-apache + PDO MySQL).
- **Docroot = `sistema/public`**; a API é exposta por `Alias /api` → `sistema/api`.
- `config/`, `database/` e `storage/` ficam **fora do webroot** (inacessíveis pela web).
- Banco MySQL/MariaDB roda como **serviço separado** no EasyPanel (rede interna).
- Config por variáveis de ambiente (`config/config.php` lê `getenv`).
- Health check: `GET /health.php` (checa app + banco, sem vazar dados).

## Repositório

```txt
GitHub: (definir — ver passo a passo abaixo)
Branch produção: main
Tag/release atual: v0.1.0 (a taguear)
```

## Passo a passo — subir para o GitHub

```bash
# 1. Criar o repositório no github.com (vazio, sem README).
# 2. Na pasta do projeto:
git remote add origin git@github.com:USUARIO/roh-e-sih.git   # ou https://...
git push -u origin main
```

## Passo a passo — EasyPanel

1. **Serviço MySQL**: criar um serviço "MySQL" (ou MariaDB). Anotar o *host
   interno* (nome do serviço), banco, usuário e senha. Não expor a porta 3306.
2. **Importar o schema**: abrir o phpMyAdmin do serviço (ou console) e rodar
   `sistema/database/schema.sql` e depois `sistema/database/seed.sql`.
3. **Serviço App**: criar um serviço "App" apontando para o repositório GitHub,
   build por **Dockerfile** (raiz do repo). Porta interna 80.
4. **Variáveis de ambiente do App**:
   ```
   APP_AMBIENTE=producao
   DB_HOST=<nome-interno-do-servico-mysql>
   DB_PORT=3306
   DB_NOME=<banco>
   DB_USUARIO=<usuario-da-app>   # não usar root
   DB_SENHA=<senha-forte>
   ```
5. **Domínio + SSL**: adicionar o domínio no serviço App e ativar SSL (Let's Encrypt).
6. **Primeiro acesso**: abrir `https://SEU-DOMINIO/` → criar o administrador
   (a tela de instalação aparece só enquanto não houver usuário).
7. **Volume**: mapear `/var/www/html/storage/videos` para um volume persistente
   (vídeos de prova não podem se perder em redeploy).

## EasyPanel

```txt
Servidor:
Projeto/app:
Domínio:
SSL:
```

## Portas e exposição

| Serviço | Porta | Exposição |
|---|---:|---|
| HTTP | 80 | pública |
| HTTPS | 443 | pública |
| MySQL | 3306 | interna |
| phpMyAdmin | 80 interno | restrita |

## Checklist pré-deploy

```md
- [ ] Branch/tag correta.
- [ ] .env fora do Git.
- [ ] Banco criado.
- [ ] Backup feito.
- [ ] Volumes persistentes.
- [ ] Domínio configurado.
- [ ] SSL funcionando.
- [ ] Rollback definido.
```
