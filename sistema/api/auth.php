<?php
// ============================================================
// Autenticação — login / logout / sessão atual
// GET  ?acao=me       -> usuário logado (ou null)
// GET  ?acao=status   -> se o sistema já foi instalado
// POST ?acao=login    -> { email, senha }
// POST ?acao=logout
// ============================================================
require_once __DIR__ . '/_bootstrap.php';

$acao = query('acao', 'me');

if ($acao === 'status') {
    $total = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    sucesso(['instalado' => $total > 0, 'logado' => usuario_logado() !== null]);
}

if ($acao === 'me') {
    sucesso(['usuario' => usuario_logado()]);
}

if ($acao === 'logout') {
    $_SESSION = [];
    session_destroy();
    sucesso(null, 'Sessão encerrada.');
}

if ($acao === 'login') {
    if (metodo() !== 'POST') {
        erro('Método não permitido.', [], 405);
    }
    $email = strtolower((string) in('email', ''));
    $senha = (string) in('senha', '');

    if (!$email || !$senha) {
        erro_validacao([campo_erro(null, 'CREDENCIAIS', 'Informe e-mail e senha.')]);
    }

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch();

    // Mensagem genérica para não revelar se o e-mail existe.
    if (!$u || !$u['ativo'] || !password_verify($senha, $u['senha_hash'])) {
        erro('E-mail ou senha inválidos.', [campo_erro(null, 'LOGIN_INVALIDO', 'Credenciais incorretas.')], 401);
    }

    // Renova o id de sessão contra fixation.
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'     => (int) $u['id'],
        'nome'   => $u['nome'],
        'email'  => $u['email'],
        'perfil' => $u['perfil'],
    ];

    db()->prepare('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id')
        ->execute([':id' => $u['id']]);

    registrar_log('login', 'usuario', (int) $u['id']);
    sucesso(['usuario' => $_SESSION['usuario']], 'Bem-vindo(a), ' . $u['nome'] . '.');
}

erro('Ação inválida.', [], 400);
