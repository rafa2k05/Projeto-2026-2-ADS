-- =====================================================================
CREATE DATABASE IF NOT EXISTS larissa_beauty
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE larissa_beauty;

-- Clientes cadastradas. A senha guarda o HASH, nunca a senha de verdade.
CREATE TABLE IF NOT EXISTS usuarios (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome      VARCHAR(100) NOT NULL,
  email     VARCHAR(150) NOT NULL,
  senha     VARCHAR(255) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

-- Serviços (preço e duração moram AQUI, o navegador não manda esses valores).
CREATE TABLE IF NOT EXISTS servicos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(100) NOT NULL,
  descricao       VARCHAR(255) NULL,
  duracao_minutos SMALLINT UNSIGNED NOT NULL,
  preco           DECIMAL(10,2) NOT NULL,
  ativo           TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_servicos_nome (nome)
) ENGINE=InnoDB;

INSERT IGNORE INTO servicos (nome, duracao_minutos, preco) VALUES
  ('Volume Brasileiro', 120, 115.00),
  ('Volume Inglês',     120, 125.00),
  ('Fox Eyes',          120, 140.00),
  ('Manutenção',         90,  85.00),
  ('Remoção',            45,  45.00);

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
  slot_ativo TINYINT GENERATED ALWAYS AS (IF(status = 'cancelado', NULL, 1)) STORED,
  UNIQUE KEY uq_slot (data, hora, slot_ativo),
  KEY idx_agendamentos_usuario (usuario_id),
  CONSTRAINT fk_agend_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_agend_servico FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB;

-- Registro de erros de senha (proteção contra tentativa de adivinhar senha).
CREATE TABLE IF NOT EXISTS login_tentativas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email     VARCHAR(150) NOT NULL,
  ip        VARCHAR(45)  NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tentativas (ip, criado_em)
) ENGINE=InnoDB;
