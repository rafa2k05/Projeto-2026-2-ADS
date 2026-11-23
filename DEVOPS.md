# DevOps no projeto Larissa Beauty

## 1. O que é DevOps?
DevOps é a união de **Dev**elopment (desenvolvimento) e **Op**erations (operação). A ideia é
automatizar o caminho que o código percorre desde o `git push` do desenvolvedor até o sistema
funcionando, para entregar mais rápido e com menos erros.

Neste projeto aplicamos duas técnicas, que se completam:

| Técnica | Ferramenta | Problema que resolve |
|---|---|---|
| **Containerização** | Docker + Docker Compose | "Na minha máquina funciona." O sistema roda igual em qualquer computador. |
| **Integração Contínua (CI)** | GitHub Actions | Bugs descobertos tarde. A cada push o código é verificado e testado automaticamente. |

## 2. Docker (containerização)
Um **container** é uma "caixinha" que leva o sistema com tudo o que ele precisa (PHP, Apache, MySQL, configurações).

Antes (Laragon): instalar Laragon, criar o banco, importar `schema.sql` na mão, copiar `config.php`...
Agora: **um comando**.

```bash
docker compose up --build
```

Arquivos:
- `Dockerfile`: receita da imagem do site (PHP 8.2 + Apache + extensão `pdo_mysql`, com `.htaccess` habilitado).
- `docker-compose.yml`: orquestra 2 containers: `web` (site) e `db` (MySQL 8). O `schema.sql` é importado
  automaticamente na primeira subida.
- `.dockerignore`: arquivos que não entram na imagem.
- `backend/config/conexao.php`: passou a ler `DB_HOST`, `DB_USER`... do ambiente (no Laragon continua igual).

Acessos: site `http://localhost:8080/frontend/` · painel `http://localhost:8080/admin/login.php`

## 3. CI com GitHub Actions (Integração Contínua)
Arquivo: `.github/workflows/ci.yml`. A cada **push** ou **Pull Request** na `main`, o GitHub cria uma
máquina virtual e executa sozinho:

1. **Job `lint-php`**: confere se algum arquivo PHP tem erro de sintaxe (`php -l`).
2. **Job `docker-test`** (só roda se o 1º passar): faz o build do Docker, sobe site + banco e roda
   `scripts/smoke-test.sh`, que verifica:
   - o site e a tela de login abrem (HTTP 200);
   - `database/` e `backend/helpers/` estão bloqueados no navegador (HTTP 403, segurança);
   - a API lê os serviços do MySQL;
   - a API de sessão devolve o token CSRF.

Se qualquer etapa falhar, aparece ❌ vermelho na aba **Actions** (e no commit/PR). Se tudo passar, ✅ verde.

## 4. Como testar
**Local:**
```bash
docker compose up --build -d
./scripts/smoke-test.sh
docker compose down -v      # desliga e apaga o banco de teste
```
**No GitHub:** faça push → aba **Actions** → workflow **CI**.

## 5. Fluxo completo
`código → git push → GitHub Actions (lint → build Docker → testes) → ✅ ou ❌`
