-- =========================================================
-- LARISSA BEAUTY - BANCO DE DADOS
-- Instalação segura / atualização sem apagar dados existentes
-- MySQL 8+ ou MariaDB compatível
-- =========================================================
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS larissa_beauty
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

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

SET @existe_tipo = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'tipo'
);
SET @sql_tipo = IF(
  @existe_tipo = 0,
  "ALTER TABLE usuarios ADD COLUMN tipo ENUM('cliente','admin') NOT NULL DEFAULT 'cliente' AFTER senha",
  "SELECT 'Coluna usuarios.tipo já existe' AS informacao"
);
PREPARE stmt_tipo FROM @sql_tipo;
EXECUTE stmt_tipo;
DEALLOCATE PREPARE stmt_tipo;

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
INSERT IGNORE INTO servicos (nome, descricao, duracao_minutos, preco, ativo) VALUES
 ('Volume Brasileiro', 'Alongamento de cílios - Volume Brasileiro', 120, 115.00, 1),
 ('Volume Inglês', 'Alongamento de cílios - Volume Inglês', 120, 125.00, 1),
 ('Fox Eyes', 'Alongamento de cílios - Fox Eyes', 120, 140.00, 1),
 ('Manutenção', 'Manutenção de alongamento de cílios', 90, 85.00, 1),
 ('Remoção', 'Remoção de alongamento de cílios', 45, 45.00, 1);


-- Bookings. The uq_slot key blocks TWO active bookings on the same day/time,
-- even if two people click at the same millisecond. A cancelled booking frees the slot.
-- Agendamentos. A trava uq_slot impede DOIS agendamentos ativos no mesmo dia/horário,
-- mesmo que duas pessoas cliquem no mesmo milissegundo. Cancelado libera o horário.

CREATE TABLE IF NOT EXISTS agenda_dias (
  data DATE NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bloqueios_dias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data DATE NOT NULL,
  motivo VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bloqueio_dia (data),
  CONSTRAINT fk_bloqueio_dia_admin
    FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bloqueios_horarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data DATE NOT NULL,
  hora TIME NOT NULL,
  motivo VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bloqueio_horario (data, hora),
  CONSTRAINT fk_bloqueio_horario_admin
    FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agendamentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  servico_id INT UNSIGNED NOT NULL,
  cliente_nome VARCHAR(100) NOT NULL,
  cliente_telefone VARCHAR(20) NOT NULL,
  consentimento_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
  data DATE NOT NULL,
  hora TIME NOT NULL,
  duracao_aplicada SMALLINT UNSIGNED NOT NULL,
  preco_aplicado DECIMAL(10,2) NOT NULL,
  status ENUM('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_agendamentos_usuario (usuario_id),
  KEY idx_agendamentos_data_status (data, status),
  KEY idx_agendamentos_telefone (cliente_telefone),
  CONSTRAINT fk_agend_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_agend_servico
    FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_tentativas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(150) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tentativas (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuarios (nome, email, senha, tipo)
VALUES (
  'Administradora',
  'admin@gmail.com',
  '$2y$12$KhODj.X4tipBnCnlY2FWwerW3FqvTQwInLUimE6gZo1C5V3nKlmwC',
  'admin'
)
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  senha = VALUES(senha),
  tipo = 'admin';

SELECT id, nome, email, tipo
FROM usuarios
WHERE email = 'admin@gmail.com';

SELECT id, nome, duracao_minutos, preco, ativo
FROM servicos
ORDER BY id;

SELECT
  a.id,
  a.cliente_nome,
  a.cliente_telefone,
  a.data,
  TIME_FORMAT(a.hora, '%H:%i') AS hora,
  s.nome AS servico,
  a.preco_aplicado,
  a.duracao_aplicada,
  a.status,
  a.criado_em
FROM agendamentos a
INNER JOIN servicos s ON s.id = a.servico_id
ORDER BY a.data DESC, a.hora DESC
LIMIT 50;
