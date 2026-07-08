# Deploy, EasyPanel e Produção

## Repositório

```txt
GitHub:
Branch desenvolvimento:
Branch homologação:
Branch produção:
Tag/release atual:
```

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
