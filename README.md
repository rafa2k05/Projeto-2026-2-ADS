# Studio Larissa Ferreira Beauty — agendamento online

[![CI](https://github.com/rafa2k05/Projeto-2026-2-ADS/actions/workflows/ci.yml/badge.svg)](https://github.com/rafa2k05/Projeto-2026-2-ADS/actions/workflows/ci.yml)

Sistema web de agendamento para o **Studio Larissa Ferreira Beauty**, um studio de extensão de cílios. A cliente escolhe o serviço, a data e um horário livre pelo site; a Larissa gerencia tudo em um painel administrativo protegido por login e senha.

Projeto Prático de Programação — UNINOVE (Universidade Nove de Julho), 2026. Orientação: Prof. Hebert Bratefixe Alquimim.

## Sumário

- [O que o sistema faz](#o-que-o-sistema-faz)
- [Tecnologias](#tecnologias)
- [Como rodar](#como-rodar)
- [Conta administradora inicial](#conta-administradora-inicial)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Banco de dados](#banco-de-dados)
- [APIs](#apis)
- [Regras e segurança](#regras-e-segurança)
- [Configurações úteis](#configurações-úteis)
- [DevOps: Docker e GitHub Actions](#devops-docker-e-github-actions)

## O que o sistema faz

O Studio não tinha um canal digital próprio: os horários eram combinados por Instagram ou WhatsApp, ou anotados à mão, o que dificultava o controle da agenda. O sistema é um CRUD de agendamentos com duas áreas.

**Site público (cliente)**

- Mostra os serviços com preço e duração: Volume Brasileiro, Volume Inglês, Fox Eyes, Manutenção e Remoção.
- A cliente escolhe o serviço, vê o valor e seleciona uma data e um horário livre.
- O agendamento é gravado com o status `pendente`.

**Painel administrativo (somente a Larissa)**

- Início com o resumo dos atendimentos.
- Agenda: confirmar, cancelar e editar agendamentos, inclusive criar agendamentos manuais.
- Serviços: cadastrar, alterar e ocultar (desativar) serviços.
- Horários: bloquear e desbloquear dias e horários.

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Front-end | HTML, CSS e JavaScript |
| Back-end | PHP 8.1+ (PDO) |
| Banco de dados | MySQL 8.0 |
| Servidor | Apache (com `.htaccess`) |
| DevOps | Git/GitHub, Docker Compose e GitHub Actions |

## Como rodar

### Opção 1 — Docker (recomendada)

Só precisa do [Docker](https://www.docker.com/) instalado. Na raiz do projeto:

```bash
docker compose up --build
```

Isso sobe dois containers: `web` (PHP 8.2 + Apache) e `db` (MySQL 8). O `database/schema.sql` é importado automaticamente na primeira execução.

- Site: <http://localhost:8080/frontend/>
- Painel: <http://localhost:8080/admin/login.php>

Para parar e apagar o banco local: `docker compose down -v`.

> As senhas do `docker-compose.yml` são apenas para desenvolvimento e teste.

### Opção 2 — Laragon (Windows)

Requisitos: PHP 8.1+ com PDO MySQL e mbstring, MySQL 8.0+ e Apache com `.htaccess` (Laragon ou XAMPP).

1. Clone ou copie o projeto para `C:\laragon\www\` (a URL usa o nome da pasta; abaixo, `Projeto-2026-2-ADS`).
2. No phpMyAdmin, importe/execute `database/schema.sql`. Ele cria o banco `larissa_beauty`, as tabelas, os 5 serviços e a conta administradora.
3. Se o MySQL não for `root` sem senha: copie `backend/config/config.example.php` para `backend/config/config.php` e preencha com os seus dados.
4. Abra o site: `http://localhost/Projeto-2026-2-ADS/frontend/`
5. Abra o painel: `http://localhost/Projeto-2026-2-ADS/admin/login.php`

## Conta administradora inicial

- E-mail: `admin@gmail.com`
- Senha: `123456`

**Troque a senha antes de publicar de verdade.** A senha é guardada com hash (bcrypt) em `database/schema.sql`.

## Estrutura do projeto

```text
.
├── frontend/             site público (index.html, script.js, style.css, img/)
├── backend/
│   ├── api/              APIs PHP (agendamento, serviços, horários, login e admin)
│   ├── helpers/
│   │   └── funcoes.php   regras e funções compartilhadas (sessão, CSRF, validações, horários)
│   └── config/           conexao.php e config.example.php
├── admin/                painel da Larissa (dashboard, agenda, serviços, horários, login)
├── database/
│   └── schema.sql        banco completo (MySQL)
├── scripts/
│   └── smoke-test.sh     teste automático de fumaça (usado pelo CI)
├── .github/workflows/
│   └── ci.yml            GitHub Actions
├── Dockerfile
├── docker-compose.yml
└── DEVOPS.md             detalhes da parte de DevOps
```

## Banco de dados

O arquivo `database/schema.sql` cria o banco `larissa_beauty` com 7 tabelas:

| Tabela | Função |
|---|---|
| `usuarios` | Contas de acesso (a administradora), com senha em hash |
| `servicos` | Serviços com nome, descrição, duração, preço e campo `ativo` |
| `agendamentos` | Agendamentos: cliente, telefone, data, hora, status (`pendente`, `confirmado`, `cancelado`) e preço/duração aplicados |
| `agenda_dias` | Trava por dia, usada para impedir horário duplicado mesmo com pedidos simultâneos |
| `bloqueios_dias` | Dias bloqueados pela Larissa |
| `bloqueios_horarios` | Horários bloqueados em um dia |
| `login_tentativas` | Falhas de login, para a proteção contra força bruta |

`agendamentos` se liga a `servicos` e `usuarios` por chave estrangeira. Ela guarda `preco_aplicado` e `duracao_aplicada`, então mudar o preço de um serviço não altera os agendamentos já feitos. A exclusão é lógica: cancelar um agendamento muda o status, e ocultar um serviço marca `ativo = 0`, preservando o histórico.

## APIs

Ficam em `backend/api/`. As rotas de escrita exigem origem do próprio site e token CSRF; as de administração exigem login.

| Grupo | Arquivos |
|---|---|
| Público | `listar_servicos.php`, `listar_horarios.php` (horários livres de uma data e serviço), `listar_horarios_permitidos.php` (horários fixos), `agendamento.php` |
| Sessão | `login.php`, `logout.php`, `me.php` (sessão atual e token CSRF), `cadastro.php` |
| Admin: agenda | `listar_agendamentos.php`, `atualizar_status.php`, `admin_agendamento_editar.php`, `admin_agendamento_manual.php`, `admin_resumo.php` |
| Admin: serviços | `admin_servicos.php` |
| Admin: bloqueios | `listar_bloqueios.php`, `bloquear_dia.php`, `desbloquear_dia.php`, `bloquear_horario.php`, `desbloquear_horarios.php` |

## Regras e segurança

- Horários fixos de atendimento: 10:00, 12:00, 14:00, 16:00 e 18:00.
- O sistema não permite dois agendamentos ativos (pendente ou confirmado) no mesmo dia e horário.
- Agendamento a partir de amanhã e com até 90 dias de antecedência, no máximo 3 agendamentos ativos por cliente.
- Senhas armazenadas com hash, nunca em texto puro.
- Proteção contra SQL Injection (prepared statements), CSRF e força bruta no login (bloqueio temporário após falhas repetidas).
- Sessão com expiração por inatividade (2 horas) e limite de tamanho para o corpo das requisições.
- Preço e duração do serviço vêm do banco de dados, nunca do navegador.
- `database/`, `backend/helpers/` e `backend/config/` são bloqueados no navegador por `.htaccess`.

## Configurações úteis

**Conexão com o banco.** `backend/config/conexao.php` usa estes valores, nesta ordem de prioridade: `backend/config/config.php` (não vai para o Git), variáveis de ambiente e padrões de desenvolvimento.

| Config | Variável de ambiente | Padrão |
|---|---|---|
| `host` | `DB_HOST` | `localhost` |
| `porta` | `DB_PORT` | `3306` |
| `banco` | `DB_NAME` | `larissa_beauty` |
| `usuario` | `DB_USER` | `root` |
| `senha` | `DB_PASS` | vazio |

**Horários de atendimento.** Hoje são 10:00, 12:00, 14:00, 16:00 e 18:00. Se mudar, altere em:

- `backend/helpers/funcoes.php` (`HORARIOS_FIXOS`);
- `admin/agenda.php` e `admin/horarios.php` (lista fixa no HTML do painel).

Outros limites ficam no topo de `backend/helpers/funcoes.php`: `DIAS_MAX_ANTECEDENCIA`, `MAX_AGENDAMENTOS_ATIVOS`, `SESSAO_INATIVIDADE`, `LIMITE_FALHAS_CONTA` e `LIMITE_FALHAS_IP`.

## DevOps: Docker e GitHub Actions

- **Git e GitHub:** código em um repositório único, com histórico de commits. O `backend/.gitignore` impede o envio de `config/config.php` e `.env`, e o `.gitattributes` padroniza as quebras de linha entre Windows e Linux.
- **Docker:** `Dockerfile` (PHP 8.2 + Apache + `pdo_mysql`) e `docker-compose.yml` (containers `web` e `db`).
- **GitHub Actions:** o `.github/workflows/ci.yml` roda a cada push ou Pull Request na `main`:
  1. `lint-php`: confere a sintaxe de todos os arquivos `.php` (`php -l`);
  2. `docker-test`: sobe o sistema com Docker Compose e roda `scripts/smoke-test.sh`.

O teste de fumaça confere se o site e o login abrem (HTTP 200), se `database/` e `backend/helpers/` estão bloqueados (HTTP 403), se a API lê os serviços do MySQL e se a API de sessão devolve o token CSRF. Para rodar localmente com o sistema no ar:

```bash
bash scripts/smoke-test.sh
```

Mais detalhes em [`DEVOPS.md`](DEVOPS.md).
