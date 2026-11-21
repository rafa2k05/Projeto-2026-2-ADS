# Larissa Beauty — agendamento online

Site público de agendamento + painel administrativo (somente a Larissa faz login).

## Estrutura
- `frontend/` — site público (index.html). Carrega serviços e horários pela API e grava o agendamento como `pendente`.
- `backend/api/` — APIs PHP (agendamento, serviços, horários, login/logout/me e as de admin).
- `backend/helpers/funcoes.php` — regras e funções compartilhadas (sessão, CSRF, validações, horários).
- `admin/` — painel da Larissa (início, agenda, serviços, horários/bloqueios).
- `database/schema.sql` — banco completo (MySQL).

## Como rodar (Laragon)
1. Coloque a pasta `larissa-backend` em `C:\laragon\www\`.
2. No phpMyAdmin, importe/execute `database/schema.sql` (cria o banco `larissa_beauty`, as tabelas, os 5 serviços e a conta admin).
3. Se o MySQL não for `root` sem senha: copie `backend/config/config.example.php` para `backend/config/config.php` e preencha.
4. Site: `http://localhost/larissa-backend/frontend/`
5. Painel: `http://localhost/larissa-backend/admin/login.php`

## Conta administradora inicial
- E-mail: `admin@gmail.com` — Senha: `123456`
- Troque a senha antes de publicar de verdade.

## Onde mudar os horários
Hoje são 10:00, 12:00, 14:00, 16:00 e 18:00. Se mudar, altere em:
- `backend/helpers/funcoes.php` (`HORARIOS_FIXOS`)
- `admin/agenda.php` e `admin/horarios.php` (lista fixa no HTML do painel)

## Teste rápido antes de apresentar
1. Agendar pelo site → aparece como `pendente` na Agenda do painel.
2. Confirmar/cancelar no painel → status muda.
3. Bloquear um dia/horário no painel → some das opções do site.
4. Tentar agendar o mesmo horário duas vezes → o sistema recusa.
