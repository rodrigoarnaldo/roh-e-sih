-- ============================================================
-- Roh & Sih — Sistema de CRM e Secretaria (Escola de Dança de Salão)
-- Banco: MySQL / MariaDB
-- Stack: PHP procedural puro. Toda regra crítica validada no backend.
-- Nomes de colunas de controle em português (criado_em, atualizado_em...).
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ------------------------------------------------------------
-- Usuários do painel (secretaria / administração)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  perfil        ENUM('admin','secretaria') NOT NULL DEFAULT 'secretaria',
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login_em DATETIME NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Turmas fixas (grade da escola)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS turmas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(60) NOT NULL,          -- ex.: "Seg 19:30"
  dia_semana    ENUM('segunda','terca','quarta','quinta') NOT NULL,
  horario       TIME NOT NULL,                 -- 19:30 / 20:40
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_turma_dia_hora (dia_semana, horario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Contatos (núcleo do CRM). Aluno, ex-aluno, não-aluno, não-contatar.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contatos (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  whatsapp           VARCHAR(20) NOT NULL,            -- somente dígitos, com DDI/DDD
  nome               VARCHAR(80) NOT NULL,
  sobrenome          VARCHAR(120) NULL,
  cidade             VARCHAR(120) NULL,
  uf                 CHAR(2) NULL,
  cpf                VARCHAR(14) NULL,                -- armazenar só dígitos
  data_nascimento    DATE NULL,

  par_situacao       ENUM('com_par','sem_par') NULL,
  par_contato_id     INT UNSIGNED NULL,              -- vínculo com outro cadastro (par)
  papel              ENUM('lider','seguidora') NULL,
  origem             VARCHAR(120) NULL,              -- origem do contato (indicação, instagram, etc.)

  -- Classificação principal do contato
  tipo_contato       ENUM('nao_aluno','aluno','ex_aluno','nao_contatar') NOT NULL DEFAULT 'nao_aluno',

  -- Campos de aluno
  plano              ENUM('mensalista','trimestral','anual') NULL,
  status_aluno       ENUM('novo','fidelizado') NULL,

  -- Status por tipo (validado no backend conforme tipo_contato)
  status_nao_aluno   ENUM('nao_conhece','conhece_nao_quer','conhece_nao_quer_agora','conhece_quer') NULL,
  status_ex_aluno    ENUM('quer_voltar','nao_quer_voltar','quer_voltar_nao_agora') NULL,
  status_nao_contatar ENUM('bloqueou','mal_educado','concorrencia','pediu_para_sair') NULL,

  -- Datas de relacionamento / follow-up
  data_matricula        DATE NULL,
  data_primeiro_contato DATE NULL,
  data_ultimo_contato   DATE NULL,
  data_proximo_contato  DATE NULL,
  data_pausa            DATE NULL,

  observacoes        TEXT NULL,
  criado_por         INT UNSIGNED NULL,
  criado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contatos_tipo (tipo_contato),
  KEY idx_contatos_whatsapp (whatsapp),
  KEY idx_contatos_proximo_contato (data_proximo_contato),
  KEY idx_contatos_par (par_contato_id),
  CONSTRAINT fk_contatos_par FOREIGN KEY (par_contato_id) REFERENCES contatos (id) ON DELETE SET NULL,
  CONSTRAINT fk_contatos_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estilos de interesse (N:N) — vaneira, forro, samba, sertanejo
CREATE TABLE IF NOT EXISTS contato_estilos (
  contato_id  INT UNSIGNED NOT NULL,
  estilo      ENUM('vaneira','forro','samba','sertanejo') NOT NULL,
  PRIMARY KEY (contato_id, estilo),
  CONSTRAINT fk_estilos_contato FOREIGN KEY (contato_id) REFERENCES contatos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Disponibilidade de dias (N:N) — segunda, terca, quarta, quinta
CREATE TABLE IF NOT EXISTS contato_disponibilidade (
  contato_id  INT UNSIGNED NOT NULL,
  dia         ENUM('segunda','terca','quarta','quinta') NOT NULL,
  PRIMARY KEY (contato_id, dia),
  CONSTRAINT fk_disp_contato FOREIGN KEY (contato_id) REFERENCES contatos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Matrícula em turma (aluno participa de uma ou mais turmas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matriculas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contato_id    INT UNSIGNED NOT NULL,
  turma_id      INT UNSIGNED NOT NULL,
  data_matricula DATE NULL,
  status        ENUM('ativa','pausada','cancelada') NOT NULL DEFAULT 'ativa',
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_matricula (contato_id, turma_id),
  CONSTRAINT fk_matricula_contato FOREIGN KEY (contato_id) REFERENCES contatos (id) ON DELETE CASCADE,
  CONSTRAINT fk_matricula_turma FOREIGN KEY (turma_id) REFERENCES turmas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Presença / frequência por aula
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS presencas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contato_id    INT UNSIGNED NOT NULL,
  turma_id      INT UNSIGNED NOT NULL,
  data_aula     DATE NOT NULL,
  status        ENUM('presente','falta','justificado') NOT NULL DEFAULT 'presente',
  observacao    VARCHAR(255) NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_presenca (contato_id, turma_id, data_aula),
  KEY idx_presenca_data (data_aula),
  CONSTRAINT fk_presenca_contato FOREIGN KEY (contato_id) REFERENCES contatos (id) ON DELETE CASCADE,
  CONSTRAINT fk_presenca_turma FOREIGN KEY (turma_id) REFERENCES turmas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pagamentos (mensalidade / plano) e próxima cobrança
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagamentos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contato_id      INT UNSIGNED NOT NULL,
  referencia      VARCHAR(40) NULL,           -- ex.: "2026-07" (competência)
  valor           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  dia_vencimento  TINYINT UNSIGNED NULL,      -- dia do mês (1-31)
  data_vencimento DATE NULL,
  data_pagamento  DATE NULL,
  proxima_cobranca DATE NULL,
  status          ENUM('pendente','pago','atrasado','cancelado') NOT NULL DEFAULT 'pendente',
  observacao      VARCHAR(255) NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pagamento_contato (contato_id),
  KEY idx_pagamento_status (status),
  KEY idx_pagamento_vencimento (data_vencimento),
  CONSTRAINT fk_pagamento_contato FOREIGN KEY (contato_id) REFERENCES contatos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Eventos: baile, curso intensivo, workshop, turma regular
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo          ENUM('baile','curso_intensivo','workshop','turma_regular') NOT NULL,
  nome          VARCHAR(160) NOT NULL,
  data_evento   DATETIME NULL,
  local         VARCHAR(200) NULL,
  valor         DECIMAL(10,2) NULL,
  vagas         INT UNSIGNED NULL,
  descricao     TEXT NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_evento_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscrições em evento: negociando, reservado, inscrito, cancelado
CREATE TABLE IF NOT EXISTS evento_inscricoes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  evento_id     INT UNSIGNED NOT NULL,
  contato_id    INT UNSIGNED NOT NULL,
  status        ENUM('negociando','reservado','inscrito','cancelado') NOT NULL DEFAULT 'negociando',
  valor         DECIMAL(10,2) NULL,
  observacao    VARCHAR(255) NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inscricao (evento_id, contato_id),
  CONSTRAINT fk_inscricao_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
  CONSTRAINT fk_inscricao_contato FOREIGN KEY (contato_id) REFERENCES contatos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Scripts de mensagem / vendas (copiar e colar no WhatsApp)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scripts_mensagem (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo        VARCHAR(160) NOT NULL,
  categoria     VARCHAR(80) NULL,            -- ex.: "primeiro contato", "cobrança", "reativação"
  conteudo      TEXT NOT NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Indicações premiadas (quem indicou -> indicado)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS indicacoes (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  indicador_contato_id  INT UNSIGNED NOT NULL,
  indicado_contato_id   INT UNSIGNED NULL,
  indicado_nome         VARCHAR(160) NULL,     -- caso ainda não seja cadastro
  status                ENUM('pendente','convertido','premiado','cancelado') NOT NULL DEFAULT 'pendente',
  premio                VARCHAR(160) NULL,
  premiado_em           DATE NULL,
  observacao            VARCHAR(255) NULL,
  criado_em             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_indicacao_indicador (indicador_contato_id),
  CONSTRAINT fk_indicacao_indicador FOREIGN KEY (indicador_contato_id) REFERENCES contatos (id) ON DELETE CASCADE,
  CONSTRAINT fk_indicacao_indicado FOREIGN KEY (indicado_contato_id) REFERENCES contatos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Avaliações (critérios 0-10) + vídeo da prova
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS avaliacoes (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contato_id        INT UNSIGNED NOT NULL,
  data_avaliacao    DATE NOT NULL,
  nota_corpo        DECIMAL(4,2) NULL,
  nota_musica       DECIMAL(4,2) NULL,
  nota_espaco       DECIMAL(4,2) NULL,
  nota_comunicacao  DECIMAL(4,2) NULL,
  nota_artistico    DECIMAL(4,2) NULL,
  nota_repertorio   DECIMAL(4,2) NULL,
  nota_media        DECIMAL(4,2) NULL,         -- calculada no backend
  feedback          TEXT NULL,
  proximos_passos   TEXT NULL,
  video_arquivo     VARCHAR(255) NULL,         -- nome do arquivo em storage/videos
  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_avaliacao_contato (contato_id),
  CONSTRAINT fk_avaliacao_contato FOREIGN KEY (contato_id) REFERENCES contatos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Log de auditoria simples
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS logs_auditoria (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id    INT UNSIGNED NULL,
  acao          VARCHAR(60) NOT NULL,          -- criar, atualizar, excluir, login
  entidade_tipo VARCHAR(40) NOT NULL,
  entidade_id   INT UNSIGNED NULL,
  contexto      TEXT NULL,
  data_hora     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_entidade (entidade_tipo, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
