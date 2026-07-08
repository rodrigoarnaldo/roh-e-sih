<?php
// ============================================================
// Inscrições / interessados em evento.
//   GET  ?acao=por_evento&evento_id=
//   GET  ?acao=buscar_contatos&q=
//   GET  ?acao=followup            -> negociações (negociando/reservado) pendentes
//   POST ?acao=criar               -> {evento_id, contato_id, status?, valor?, data_followup?}
//   POST ?acao=atualizar&id=       -> {status?, valor?, data_followup?, observacao?}
//   POST ?acao=excluir&id=
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

const ENUM_INSCRICAO_STATUS = ['negociando', 'reservado', 'pago', 'cancelado', 'sem_interesse'];

$acao = query('acao', 'por_evento');
switch ($acao) {
    case 'por_evento':      porEvento(); break;
    case 'buscar_contatos': buscarContatos(); break;
    case 'followup':        followup(); break;
    case 'criar':           criar(); break;
    case 'atualizar':       atualizar(); break;
    case 'excluir':         excluir(); break;
    default:                erro('Ação inválida.', [], 400);
}

function porEvento() {
    $eventoId = (int) query('evento_id');
    if ($eventoId <= 0) { erro('Evento inválido.', [], 400); }
    $stmt = db()->prepare(
        "SELECT i.id, i.status, i.valor, i.data_followup, i.observacao,
                c.id AS contato_id, c.nome, c.sobrenome, c.whatsapp
         FROM evento_inscricoes i
         JOIN contatos c ON c.id = i.contato_id
         WHERE i.evento_id = :e
         ORDER BY FIELD(i.status,'negociando','reservado','pago','cancelado','sem_interesse'), c.nome"
    );
    $stmt->execute([':e' => $eventoId]);
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

function followup() {
    // Negociações ativas: quem está negociando/reservado. Ordena por data de
    // follow-up (vencidas primeiro; sem data vai ao fim).
    $stmt = db()->query(
        "SELECT i.id, i.status, i.valor, i.data_followup,
                c.id AS contato_id, c.nome, c.sobrenome, c.whatsapp,
                e.id AS evento_id, e.nome AS evento_nome, e.tipo AS evento_tipo
         FROM evento_inscricoes i
         JOIN contatos c ON c.id = i.contato_id
         JOIN eventos  e ON e.id = i.evento_id
         WHERE i.status IN ('negociando','reservado')
         ORDER BY (i.data_followup IS NULL), i.data_followup ASC"
    );
    sucesso($stmt->fetchAll());
}

function criar() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $eventoId  = (int) in('evento_id');
    $contatoId = (int) in('contato_id');
    $status    = validar_enum(in('status'), ENUM_INSCRICAO_STATUS, 'status') ?? 'negociando';
    $dataFup   = validar_data(in('data_followup'), 'data_followup');
    $valor     = valorOuNull(in('valor'));

    if ($eventoId <= 0 || $contatoId <= 0) {
        erro_validacao([campo_erro(null, 'OBRIGATORIO', 'Informe evento e contato.')]);
    }
    $ok = db()->prepare('SELECT (SELECT COUNT(*) FROM eventos WHERE id=:e) te, (SELECT COUNT(*) FROM contatos WHERE id=:c) tc');
    $ok->execute([':e' => $eventoId, ':c' => $contatoId]);
    $r = $ok->fetch();
    if (!$r['te']) { erro('Evento não encontrado.', [], 404); }
    if (!$r['tc']) { erro('Contato não encontrado.', [], 404); }

    try {
        $stmt = db()->prepare(
            'INSERT INTO evento_inscricoes (evento_id, contato_id, status, data_followup, valor)
             VALUES (:e, :c, :s, :d, :v)'
        );
        $stmt->execute([':e' => $eventoId, ':c' => $contatoId, ':s' => $status, ':d' => $dataFup, ':v' => $valor]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            erro('Este contato já está inscrito neste evento.', [campo_erro(null, 'DUPLICADA', 'Inscrição já existe.')], 409);
        }
        throw $e;
    }
    $id = (int) db()->lastInsertId();
    registrar_log('criar', 'evento_inscricao', $id, ['evento_id' => $eventoId, 'contato_id' => $contatoId]);
    sucesso(['id' => $id], 'Interessado adicionado.');
}

function atualizar() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }

    $campos = [];
    $bind = [':id' => $id];
    $status = in('status');
    if ($status !== null) {
        validar_enum($status, ENUM_INSCRICAO_STATUS, 'status', true);
        $campos[] = 'status = :s'; $bind[':s'] = $status;
    }
    if (array_key_exists('data_followup', corpo_json())) {
        $campos[] = 'data_followup = :d'; $bind[':d'] = validar_data(in('data_followup'), 'data_followup');
    }
    if (array_key_exists('valor', corpo_json())) {
        $campos[] = 'valor = :v'; $bind[':v'] = valorOuNull(in('valor'));
    }
    if (array_key_exists('observacao', corpo_json())) {
        $campos[] = 'observacao = :o'; $bind[':o'] = in('observacao');
    }
    if (!$campos) { erro('Nada para atualizar.', [], 400); }

    $stmt = db()->prepare('UPDATE evento_inscricoes SET ' . implode(', ', $campos) . ' WHERE id = :id');
    $stmt->execute($bind);
    if ($stmt->rowCount() === 0) {
        $chk = db()->prepare('SELECT id FROM evento_inscricoes WHERE id = :id');
        $chk->execute([':id' => $id]);
        if (!$chk->fetch()) { erro('Inscrição não encontrada.', [], 404); }
    }
    registrar_log('atualizar', 'evento_inscricao', $id, ['status' => $status]);
    sucesso(null, 'Inscrição atualizada.');
}

function excluir() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }
    $stmt = db()->prepare('DELETE FROM evento_inscricoes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) { erro('Inscrição não encontrada.', [], 404); }
    registrar_log('excluir', 'evento_inscricao', $id);
    sucesso(null, 'Inscrição removida.');
}

function valorOuNull($v) {
    if ($v === null || $v === '') { return null; }
    return (float) str_replace(',', '.', (string) $v);
}
