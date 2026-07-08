<?php
// ============================================================
// Eventos — curso intensivo, baile/festa, workshop, turma regular.
//   GET  ?acao=listar            -> eventos + contadores de inscrição
//   GET  ?acao=obter&id=
//   POST ?acao=criar
//   POST ?acao=atualizar&id=
//   POST ?acao=excluir&id=
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

const ENUM_EVENTO_TIPO = ['curso_intensivo', 'baile', 'workshop', 'turma_regular'];

$acao = query('acao', 'listar');
switch ($acao) {
    case 'listar':    listar(); break;
    case 'obter':     obter(); break;
    case 'criar':     salvar(null); break;
    case 'atualizar': salvar((int) query('id')); break;
    case 'excluir':   excluir(); break;
    default:          erro('Ação inválida.', [], 400);
}

function listar() {
    $sql = "SELECT e.*,
                   COUNT(i.id) AS total_inscricoes,
                   COALESCE(SUM(i.status = 'pago'), 0) AS pagos,
                   COALESCE(SUM(i.status IN ('negociando','reservado')), 0) AS em_negociacao
            FROM eventos e
            LEFT JOIN evento_inscricoes i ON i.evento_id = e.id
            GROUP BY e.id
            ORDER BY (e.data_evento IS NULL), e.data_evento DESC, e.id DESC";
    sucesso(db()->query($sql)->fetchAll());
}

function obter() {
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }
    $stmt = db()->prepare('SELECT * FROM eventos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $e = $stmt->fetch();
    if (!$e) { erro('Evento não encontrado.', [], 404); }
    sucesso($e);
}

function salvar($id) {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $editar = $id !== null;
    if ($editar && $id <= 0) { erro('ID inválido.', [], 400); }

    $tipo = validar_enum(in('tipo'), ENUM_EVENTO_TIPO, 'tipo', true);
    $nome = in('nome');
    if (!$nome) { erro_validacao([campo_erro('nome', 'OBRIGATORIO', 'Informe o nome do evento.')]); }

    // data_evento: aceita "YYYY-MM-DD HH:MM" ou "YYYY-MM-DDTHH:MM" (do input datetime-local)
    $dataEvento = in('data_evento');
    if ($dataEvento !== null) {
        $dataEvento = str_replace('T', ' ', $dataEvento);
        if (strlen($dataEvento) === 16) { $dataEvento .= ':00'; }
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $dataEvento);
        if (!$d) { erro_validacao([campo_erro('data_evento', 'DATA_INVALIDA', 'Data/hora inválida.')]); }
    }

    $valor = in('valor');
    $valor = ($valor === null || $valor === '') ? null : (float) str_replace(',', '.', $valor);
    $vagas = in('vagas');
    $vagas = ($vagas === null || $vagas === '') ? null : (int) $vagas;

    $campos = [
        'tipo'        => $tipo,
        'nome'        => $nome,
        'data_evento' => $dataEvento,
        'local'       => in('local'),
        'valor'       => $valor,
        'vagas'       => $vagas,
        'descricao'   => in('descricao'),
        'ativo'       => (int) (in('ativo', 1) ? 1 : 0),
    ];

    if ($editar) {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($campos)));
        $stmt = db()->prepare("UPDATE eventos SET $sets WHERE id = :id");
        foreach ($campos as $k => $v) { $stmt->bindValue(":$k", $v); }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            $chk = db()->prepare('SELECT id FROM eventos WHERE id = :id');
            $chk->execute([':id' => $id]);
            if (!$chk->fetch()) { erro('Evento não encontrado.', [], 404); }
        }
        $eid = $id;
    } else {
        $cols = implode(', ', array_keys($campos));
        $ph   = ':' . implode(', :', array_keys($campos));
        $stmt = db()->prepare("INSERT INTO eventos ($cols) VALUES ($ph)");
        foreach ($campos as $k => $v) { $stmt->bindValue(":$k", $v); }
        $stmt->execute();
        $eid = (int) db()->lastInsertId();
    }

    registrar_log($editar ? 'atualizar' : 'criar', 'evento', $eid);
    sucesso(['id' => $eid], $editar ? 'Evento atualizado.' : 'Evento criado.');
}

function excluir() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }
    $stmt = db()->prepare('DELETE FROM eventos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) { erro('Evento não encontrado.', [], 404); }
    registrar_log('excluir', 'evento', $id);
    sucesso(null, 'Evento excluído.');
}
