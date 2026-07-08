-- ============================================================
-- Migration 001 — Ajusta status de inscrições de evento + follow-up.
-- Rode UMA VEZ no banco de produção (phpMyAdmin) após atualizar o código.
-- Seguro em base sem dados de inscrição (módulo novo).
-- ============================================================

ALTER TABLE evento_inscricoes
  MODIFY status ENUM('negociando','reservado','pago','cancelado','sem_interesse')
    NOT NULL DEFAULT 'negociando';

ALTER TABLE evento_inscricoes
  ADD COLUMN data_followup DATE NULL AFTER status;
