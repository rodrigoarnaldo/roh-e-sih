<?php
// ============================================================
// Instalação — cria o PRIMEIRO usuário admin.
// Só funciona enquanto NÃO existir nenhum usuário (proteção anti-abuso).
// POST { nome, email, senha }
// ============================================================
require_once __DIR__ . '/_bootstrap.php';

if (metodo() !== 'POST') {
    erro('Método não permitido.', [], 405);
}

$total = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
if ($total > 0) {
    erro('Instalação já concluída. Já existe usuário cadastrado.', [campo_erro(null, 'JA_INSTALADO', 'Use a tela de login.')], 409);
}

$nome  = in('nome');
$email = in('email');
$senha = in('senha');

$errs = [];
if (!$nome)  { $errs[] = campo_erro('nome', 'OBRIGATORIO', 'Informe o nome.'); }
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errs[] = campo_erro('email', 'EMAIL_INVALIDO', 'Informe um e-mail válido.');
}
if (!$senha || strlen($senha) < 6) {
    $errs[] = campo_erro('senha', 'SENHA_CURTA', 'A senha deve ter ao menos 6 caracteres.');
}
if ($errs) {
    erro_validacao($errs);
}

$stmt = db()->prepare(
    'INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo)
     VALUES (:nome, :email, :hash, :perfil, 1)'
);
$stmt->execute([
    ':nome'   => $nome,
    ':email'  => strtolower($email),
    ':hash'   => password_hash($senha, PASSWORD_DEFAULT),
    ':perfil' => 'admin',
]);

registrar_log('instalar', 'usuario', (int) db()->lastInsertId());
sucesso(null, 'Administrador criado com sucesso. Faça login.');
