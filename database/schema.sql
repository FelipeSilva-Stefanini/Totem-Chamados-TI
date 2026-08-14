-- ============================================================
-- Sistema de Chamados TI - Grupo Boticário (Fábrica)
-- Banco: chamados_ti
-- Rodar no phpMyAdmin (aba SQL) ou via linha de comando do MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS chamados_ti
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE chamados_ti;

-- ------------------------------------------------------------
-- Agentes de suporte (login da Área de Suporte)
-- ------------------------------------------------------------
CREATE TABLE agentes_suporte (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE COMMENT 'formato primeiro.ultimo, ex: felipe.leite',
  nome_completo VARCHAR(150) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL COMMENT 'hash bcrypt (password_hash do PHP), nunca senha em texto puro',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Chamados abertos pelos usuários no totem (tablet)
-- ------------------------------------------------------------
CREATE TABLE chamados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero_chamado VARCHAR(20) NOT NULL UNIQUE COMMENT 'ex: CAM00001, gerado pelo backend',
  nome_solicitante VARCHAR(150) NOT NULL,
  setor VARCHAR(100) NOT NULL,
  login_solicitante VARCHAR(50) NOT NULL,
  email_solicitante VARCHAR(150) NOT NULL COMMENT 'validação leve de formato, sem restrição de domínio',
  tipo ENUM('Duvida','Incidente','Requisicao') NOT NULL,
  descricao VARCHAR(500) NOT NULL,
  solucao TEXT NULL COMMENT 'o que foi feito para resolver, preenchido ao marcar como Resolvido',
  prioridade_usuario ENUM('Baixa','Media','Alta','Critica') NOT NULL COMMENT 'informada pelo usuário na abertura',
  prioridade_suporte ENUM('Baixa','Media','Alta','Critica') NULL COMMENT 'definida pelo suporte após análise',
  numero_servicenow VARCHAR(50) NULL COMMENT 'número do chamado espelhado no ServiceNow, preenchido ao concluir',
  status ENUM('Aberto','Em tratativa','Resolvido','Concluido') NOT NULL DEFAULT 'Aberto',
  agente_atribuido_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (agente_atribuido_id) REFERENCES agentes_suporte(id),
  INDEX idx_status (status),
  INDEX idx_agente (agente_atribuido_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Trilha de auditoria: um registro por evento relevante
-- (abertura, atribuição, transferência, mudança de status,
--  alteração de prioridade)
-- ------------------------------------------------------------
CREATE TABLE chamados_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chamado_id INT NOT NULL,
  agente_id INT NULL COMMENT 'nulo quando o evento é a abertura pelo próprio usuário',
  tipo_evento ENUM('Abertura','Atribuicao','Transferencia','Mudanca_Status','Alteracao_Prioridade') NOT NULL,
  valor_anterior VARCHAR(255) NULL,
  valor_novo VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (chamado_id) REFERENCES chamados(id),
  FOREIGN KEY (agente_id) REFERENCES agentes_suporte(id),
  INDEX idx_chamado (chamado_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Controle de tentativas de login (proteção contra força bruta)
-- Um registro por tentativa falha. Registros antigos podem ser
-- limpos periodicamente sem prejuízo.
-- ------------------------------------------------------------
CREATE TABLE login_tentativas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  tentativa_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username_data (username, tentativa_em)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed inicial dos 3 agentes.
-- IMPORTANTE: os hashes abaixo são placeholders. Vou gerar um
-- script PHP separado (gerar_senha.php) pra criar o hash real
-- de cada senha antes de vocês usarem em produção.
-- ------------------------------------------------------------
INSERT INTO agentes_suporte (username, nome_completo, senha_hash) VALUES
('felipe.leite', 'Felipe Leite', 'SUBSTITUIR_PELO_HASH_REAL'),
('agente.dois', 'Nome do segundo agente', 'SUBSTITUIR_PELO_HASH_REAL'),
('agente.tres', 'Nome do terceiro agente', 'SUBSTITUIR_PELO_HASH_REAL');
