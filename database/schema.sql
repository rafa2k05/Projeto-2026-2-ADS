-- =====================================================================
-- Larissa Beauty database schema
-- Estrutura do banco de dados da Larissa Beauty
-- =====================================================================

-- Create the database (utf8mb4 supports accents and emojis).
-- Cria o banco de dados (utf8mb4 aceita acentos e emojis).
CREATE DATABASE IF NOT EXISTS larissa_beauty
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE larissa_beauty;

-- Registered customers. The password column stores the HASH, never the real password.
-- Clientes cadastradas. A coluna senha guarda o HASH, nunca a senha de verdade.
CREATE TABLE IF NOT EXISTS usuarios (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome      VARCHAR(100) NOT NULL,
  email     VARCHAR(150) NOT NULL,
  senha     VARCHAR(255) NOT NULL,  -- 255 leaves room for future, longer hash formats / 255 deixa espaço para formatos de hash maiores no futuro
  -- Account role. Everyone starts as 'cliente'; admins are set manually in the database,
  -- NEVER through the signup form.
  -- Perfil da conta. Todo mundo começa como 'cliente'; admins são definidos manualmente
  -- no banco, NUNCA pelo formulário de cadastro.
  tipo      ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- One account per e-mail (also what makes error 1062 happen on duplicate signup).
  -- Uma conta por e-mail (é isso que gera o erro 1062 em um cadastro duplicado).
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

-- Services (price and duration live HERE; the browser never sends these values).
-- Serviços (preço e duração moram AQUI; o navegador não envia esses valores).
CREATE TABLE IF NOT EXISTS servicos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(100) NOT NULL,
  descricao       VARCHAR(255) NULL,
  duracao_minutos SMALLINT UNSIGNED NOT NULL,
  preco           DECIMAL(10,2) NOT NULL,
  -- 1 = shown to customers, 0 = hidden (keeps history without deleting the row).
  -- 1 = aparece para as clientes, 0 = oculto (mantém o histórico sem apagar a linha).
  ativo           TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_servicos_nome (nome)
) ENGINE=InnoDB;

-- Initial services. INSERT IGNORE skips names that already exist, so it is safe to re-run.
-- Serviços iniciais. O INSERT IGNORE pula nomes que já existem, então é seguro rodar de novo.
INSERT IGNORE INTO servicos (nome, duracao_minutos, preco) VALUES
  ('Volume Brasileiro', 120, 115.00),
  ('Volume Inglês',     120, 125.00),
  ('Fox Eyes',          120, 140.00),
  ('Manutenção',         90,  85.00),
  ('Remoção',            45,  45.00);

-- Bookings. The uq_slot key blocks TWO active bookings on the same day/time,
-- even if two people click at the same millisecond. A cancelled booking frees the slot.
-- Agendamentos. A trava uq_slot impede DOIS agendamentos ativos no mesmo dia/horário,
-- mesmo que duas pessoas cliquem no mesmo milissegundo. Cancelado libera o horário.
CREATE TABLE IF NOT EXISTS agendamentos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  servico_id INT UNSIGNED NOT NULL,
  data       DATE NOT NULL,
  hora       TIME NOT NULL,
  status     ENUM('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  criado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Auto-calculated: 1 while the booking is active, NULL when cancelled.
  -- MySQL allows many NULLs in a UNIQUE key, which is what frees cancelled slots.
  -- Calculada automaticamente: 1 enquanto o agendamento está ativo, NULL quando cancelado.
  -- O MySQL permite vários NULL em uma chave UNIQUE, e é isso que libera os horários cancelados.
  slot_ativo TINYINT GENERATED ALWAYS AS (IF(status = 'cancelado', NULL, 1)) STORED,
  UNIQUE KEY uq_slot (data, hora, slot_ativo),
  -- Speeds up "how many bookings does this customer have" lookups.
  -- Acelera a consulta "quantos agendamentos esta cliente tem".
  KEY idx_agendamentos_usuario (usuario_id),
  -- Foreign keys: a booking must point to a real user and a real service.
  -- Chaves estrangeiras: o agendamento precisa apontar para um usuário e um serviço reais.
  CONSTRAINT fk_agend_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_agend_servico FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB;

-- Failed login log (protection against password guessing).
-- Registro de erros de senha (proteção contra tentativa de adivinhar senha).
CREATE TABLE IF NOT EXISTS login_tentativas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email     VARCHAR(150) NOT NULL,
  ip        VARCHAR(45)  NOT NULL,  -- 45 characters fit IPv6 / 45 caracteres comportam IPv6
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Used by login_bloqueado() to count recent failures per IP quickly.
  -- Usado pelo login_bloqueado() para contar rápido as falhas recentes por IP.
  KEY idx_tentativas (ip, criado_em)
) ENGINE=InnoDB;
