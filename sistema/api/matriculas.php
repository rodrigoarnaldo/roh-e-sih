<?php
// ============================================================
// Matrículas — vincula contatos (alunos) às turmas.
//   GET  ?acao=turmas_resumo           -> turmas + nº de matriculados ativos
//   GET  ?acao=por_turma&turma_id=     -> alunos matriculados na turma
//   GET  ?acao=por_contato&contato_id= -> turmas de um contato
//   GET  ?acao=buscar_contatos&q=      -> contatos para adicionar (busca)
//   POST ?acao=criar                   -> {contato_id, turma_id, data_matricula?, status?}
//   POST ?acao=atualizar_status&id=    -> {status: ativa|pausada|cancelada}
//   POST ?acao=excluir&id=
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

const ENUM_STATUS_MATRICULA = ['ativa', 'pausada', 'cancelada'];

$acao = query('acao', 'turmas_resumo');
switch ($acao) {
    case 'turmas_resumo':   turmasResumo(); break;
    case 'por_turma':       porTurma(); break;
    case 'por_contato':     porContato(); break;
    case 'buscar_contatos': buscarContatos(); break;
    case 'criar':           criar(); break;
    case 'atualizar_status': atualizarStatus(); break;
    case 'excluir':         excluir(); break;
    default:                erro('Ação inválida.', [], 400);
}

function turmasResumo() {
    $sql = "SELECT t.id, t.nome, t.dia_semana,
                   TIME_FORMAT(t.horario, '%H:%i') AS horario,
                   COALESCE(SUM(m.status = 'ativa'), 0) AS ativos,
                   COUNT(m.id) AS total
            FROM turmas t
            LEFT JOIN matriculas m ON m.turma_id = t.id
            WHERE t.ativo = 1
            GROUP BY t.id, t.nome, t.dia_semana, t.horario
            ORDER BY FIELD(t.dia_semana,'segunda','terca','quarta','quinta'), t.horario";
    sucesso(db()->query($sql)->fetchAll());
}

function porTurma() {
    $turmaId = (int) query('turma_id');
    if ($turmaId <= 0) { erro('Turma inválida.', [], 400); }
    $stmt = db()->prepare(
        "SELECT m.id AS matricula_id, m.status, m.data_matricula,
                c.id AS contato_id, c.nome, c.sobrenome, c.whatsapp
         FROM matriculas m
         JOIN contatos c ON c.id = m.contato_id
         WHERE m.turma_id = :t
         ORDER BY (m.status = 'ativa') DESC, c.nome"
    );
    $stmt->execute([':t' => $turmaId]);
    sucesso($stmt->fetchAll());
}

function porContato() {
    $contatoId = (int) query('contato_id');
    if ($contatoId <= 0) { erro('Contato inválido.', [], 400); }
    $stmt = db()->prepare(
        "SELECT m.id AS matricula_id, m.status, m.data_matricula,
                t.id AS turma_id, t.nome AS turma_nome
         FROM matriculas m
         JOIN turmas t ON t.id = m.turma_id
         WHERE m.contato_id = :c
         ORDER BY FIELD(t.dia_semana,'segunda','terca','quarta','quinta'), t.horario"
    );
    $stmt->execute([':c' => $contatoId]);
    sucesso($stmt->fetchAll());
}

function buscarContatos() {
    $q = query('q');
    if ($q === null || mb_strlen($q) < 2) { sucesso([]); }
    $stmt = db()->prepare(
        "SELECT id, nome, sobrenome, whatsapp, tipo_contato
         FROM contatos
         WHERE CONCAT_WS(' ', nome, sobrenome, whatsapp) LIKE :q
         ORDER BY nome LIMIT 15"
    );
    $stmt->execute([':q' => '%' . $q . '%']);
    sucesso($stmt->fetchAll());
}

function criar() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $contatoId = (int) in('contato_id');
    $turmaId   = (int) in('turma_id');
    $status    = validar_enum(in('status'), ENUM_STATUS_MATRICULA, 'status') ?? 'ativa';
    $dataMat   = validar_data(in('data_matricula'), 'data_matricula');

    if ($contatoId <= 0 || $turmaId <= 0) {
        erro_validacao([campo_erro(null, 'OBRIGATORIO', 'Informe contato e turma.')]);
    }
    // Verifica existência
    $ok = db()->prepare('SELECT (SELECT COUNT(*) FROM contatos WHERE id=:c) AS tc, (SELECT COUNT(*) FROM turmas WHERE id=:t) AS tt');
    $ok->execute([':c' => $contatoId, ':t' => $turmaId]);
    $r = $ok->fetch();
    if (!$r['tc']) { erro('Contato não encontrado.', [], 404); }
    if (!$r['tt']) { erro('Turma não encontrada.', [], 404); }

    try {
        $stmt = db()->prepare(
            'INSERT INTO matriculas (contato_id, turma_id, data_matricula, status)
             VALUES (:c, :t, :d, :s)'
        );
        $stmt->execute([':c' => $contatoId, ':t' => $turmaId, ':d' => $dataMat, ':s' => $status]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // duplicada (unique contato+turma)
            erro('Este contato já está matriculado nesta turma.', [campo_erro(null, 'DUPLICADA', 'Matrícula já existe.')], 409);
        }
        throw $e;
    }
    $id = (int) db()->lastInsertId();
    registrar_log('criar', 'matricula', $id, ['contato_id' => $contatoId, 'turma_id' => $turmaId]);
    sucesso(['id' => $id], 'Matrícula criada.');
}

function atualizarStatus() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    $status = validar_enum(in('status'), ENUM_STATUS_MATRICULA, 'status', true);
    if ($id <= 0) { erro('ID inválido.', [], 400); }

    $stmt = db()->prepare('UPDATE matriculas SET status = :s WHERE id = :id');
    $stmt->execute([':s' => $status, ':id' => $id]);
    if ($stmt->rowCount() === 0) {
        $chk = db()->prepare('SELECT id FROM matriculas WHERE id = :id');
        $chk->execute([':id' => $id]);
        if (!$chk->fetch()) { erro('Matrícula não encontrada.', [], 404); }
    }
    registrar_log('atualizar', 'matricula', $id, ['status' => $status]);
    sucesso(null, 'Status atualizado.');
}

function excluir() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }
    $stmt = db()->prepare('DELETE FROM matriculas WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) { erro('Matrícula não encontrada.', [], 404); }
    registrar_log('excluir', 'matricula', $id);
    sucesso(null, 'Matrícula removida.');
}
