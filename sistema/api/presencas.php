<?php
// ============================================================
// Presença / frequência por aula.
//   GET  ?acao=chamada&turma_id=&data=YYYY-MM-DD
//        -> alunos ativos da turma + status já lançado na data (se houver)
//   POST ?acao=salvar   -> { turma_id, data, presencas:[{contato_id, status}] }
//   GET  ?acao=frequencia&contato_id=  -> resumo de frequência do aluno
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

const ENUM_STATUS_PRESENCA = ['presente', 'falta', 'justificado'];

$acao = query('acao', 'chamada');
switch ($acao) {
    case 'chamada':    chamada(); break;
    case 'salvar':     salvar(); break;
    case 'frequencia': frequencia(); break;
    default:           erro('Ação inválida.', [], 400);
}

function chamada() {
    $turmaId = (int) query('turma_id');
    $data = validar_data(query('data'), 'data') ?? date('Y-m-d');
    if ($turmaId <= 0) { erro('Turma inválida.', [], 400); }

    $turma = db()->prepare("SELECT id, nome, TIME_FORMAT(horario,'%H:%i') horario FROM turmas WHERE id = :t");
    $turma->execute([':t' => $turmaId]);
    $t = $turma->fetch();
    if (!$t) { erro('Turma não encontrada.', [], 404); }

    $stmt = db()->prepare(
        "SELECT c.id AS contato_id, c.nome, c.sobrenome, p.status
         FROM matriculas m
         JOIN contatos c ON c.id = m.contato_id
         LEFT JOIN presencas p
                ON p.contato_id = c.id AND p.turma_id = m.turma_id AND p.data_aula = :data
         WHERE m.turma_id = :t AND m.status = 'ativa'
         ORDER BY c.nome"
    );
    $stmt->execute([':t' => $turmaId, ':data' => $data]);

    sucesso([
        'turma'  => $t,
        'data'   => $data,
        'alunos' => $stmt->fetchAll(),
    ]);
}

function salvar() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $turmaId = (int) in('turma_id');
    $data = validar_data(in('data'), 'data');
    $presencas = corpo_json()['presencas'] ?? null;

    if ($turmaId <= 0 || !$data) {
        erro_validacao([campo_erro(null, 'OBRIGATORIO', 'Informe turma e data.')]);
    }
    if (!is_array($presencas) || count($presencas) === 0) {
        erro_validacao([campo_erro('presencas', 'VAZIO', 'Nenhuma presença para salvar.')]);
    }

    $pdo = db();
    $upsert = $pdo->prepare(
        'INSERT INTO presencas (contato_id, turma_id, data_aula, status)
         VALUES (:c, :t, :d, :s)
         ON DUPLICATE KEY UPDATE status = VALUES(status)'
    );

    $salvos = 0;
    $pdo->beginTransaction();
    try {
        foreach ($presencas as $p) {
            if (!isset($p['contato_id'], $p['status'])) { continue; }
            $c = (int) $p['contato_id'];
            $s = $p['status'];
            if ($c <= 0 || !in_array($s, ENUM_STATUS_PRESENCA, true)) { continue; }
            $upsert->execute([':c' => $c, ':t' => $turmaId, ':d' => $data, ':s' => $s]);
            $salvos++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        erro('Não foi possível salvar a chamada.', [campo_erro(null, 'ERRO_SALVAR', APP_DEBUG ? $e->getMessage() : 'Erro interno.')], 500);
    }

    registrar_log('salvar', 'presenca', $turmaId, ['data' => $data, 'salvos' => $salvos]);
    sucesso(['salvos' => $salvos], "Chamada salva ($salvos aluno(s)).");
}

function frequencia() {
    $contatoId = (int) query('contato_id');
    if ($contatoId <= 0) { erro('Contato inválido.', [], 400); }

    $stmt = db()->prepare('SELECT status, COUNT(*) AS n FROM presencas WHERE contato_id = :c GROUP BY status');
    $stmt->execute([':c' => $contatoId]);
    $cont = ['presente' => 0, 'falta' => 0, 'justificado' => 0];
    foreach ($stmt->fetchAll() as $r) { $cont[$r['status']] = (int) $r['n']; }
    $total = array_sum($cont);
    $percentual = $total > 0 ? round(($cont['presente'] / $total) * 100, 1) : null;

    sucesso([
        'total_aulas' => $total,
        'presencas'   => $cont['presente'],
        'faltas'      => $cont['falta'],
        'justificados' => $cont['justificado'],
        'percentual_presenca' => $percentual,
    ]);
}
