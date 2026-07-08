-- ============================================================
-- Seed inicial — Roh & Sih
-- Rode DEPOIS de schema.sql.
-- O usuário admin NÃO é criado aqui (senha nunca em texto plano no SQL).
-- Crie o primeiro admin em api/instalar.php (usa password_hash no backend).
-- ============================================================

SET NAMES utf8mb4;

-- Grade fixa de turmas (Seg/Ter/Qua/Qui em 19:30 e 20:40)
INSERT INTO turmas (nome, dia_semana, horario) VALUES
  ('Seg 19:30', 'segunda', '19:30:00'),
  ('Seg 20:40', 'segunda', '20:40:00'),
  ('Ter 19:30', 'terca',   '19:30:00'),
  ('Ter 20:40', 'terca',   '20:40:00'),
  ('Qua 19:30', 'quarta',  '19:30:00'),
  ('Qua 20:40', 'quarta',  '20:40:00'),
  ('Qui 19:30', 'quinta',  '19:30:00'),
  ('Qui 20:40', 'quinta',  '20:40:00')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Scripts de mensagem de exemplo (editáveis no painel)
INSERT INTO scripts_mensagem (titulo, categoria, conteudo) VALUES
  ('Primeiro contato',
   'primeiro contato',
   'Oi {nome}! Aqui é da Roh & Sih Dança de Salão 💃 Vi que você se interessou pelas nossas aulas. Você prefere aula focada em {estilo}? Temos turmas de segunda a quinta. Posso te contar como funciona?'),
  ('Aula experimental',
   'conversao',
   'Oi {nome}! Que tal marcar uma aula experimental gratuita? Assim você sente a energia da turma antes de decidir. Tenho vaga essa semana — qual dia fica melhor pra você?'),
  ('Cobrança amigável',
   'cobranca',
   'Oi {nome}, tudo bem? Passando pra lembrar que sua mensalidade vence dia {vencimento}. Qualquer dúvida é só me chamar por aqui 😊'),
  ('Reativação de ex-aluno',
   'reativacao',
   'Oi {nome}! Saudade de te ver na pista 💫 Estamos com turmas novas e um ambiente incrível. Bora voltar a dançar? Posso te mandar os horários?');
