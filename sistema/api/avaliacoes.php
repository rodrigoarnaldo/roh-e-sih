<?php
// ============================================================
// Provas / Avaliações — avaliação prática de dança por aluno.
//   GET  ?acao=listar[&q=]              -> lista de avaliações (+ nome do aluno)
//   GET  ?acao=obter&id=               -> uma avaliação completa
//   GET  ?acao=por_contato&contato_id= -> histórico de um aluno
//   GET  ?acao=buscar_contatos&q=      -> contatos para avaliar (busca)
//   GET  ?acao=video&id=               -> streaming do vídeo da prova (Range)
//   POST ?acao=criar                   -> {contato_id, data_avaliacao?, nota_*..., feedback?, proximos_passos?}
//   POST ?acao=atualizar&id=
//   POST ?acao=upload_video&id=        -> multipart, campo "video"
//   POST ?acao=excluir&id=
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

// Critérios (colunas de nota 0-10). Fonte da média calculada no backend.
const CRITERIOS_PROVA = [
    'nota_corpo', 'nota_musica', 'nota_espaco',
    'nota_comunicacao', 'nota_artistico', 'nota_repertorio',
];
const VIDEO_EXTENSOES = ['mp4', 'm4v', 'mov', 'webm', 'ogg', 'ogv', 'mkv', 'avi'];

$acao = query('acao', 'listar');
switch ($acao) {
    case 'listar':          listar(); break;
    case 'obter':           obter(); break;
    case 'por_contato':     porContato(); break;
    case 'buscar_contatos': buscarContatos(); break;
    case 'video':           streamVideo(); break;
    case 'criar':           salvar(false); break;
    case 'atualizar':       salvar(true); break;
    case 'upload_video':    uploadVideo(); break;
    case 'excluir':         excluir(); break;
    default:                erro('Ação inválida.', [], 400);
}

// ------------------------------------------------------------
// Leitura
// ------------------------------------------------------------
function listar() {
    $q = query('q');
    $sql = "SELECT a.id, a.contato_id, a.data_avaliacao, a.nota_media,
                   (a.video_arquivo IS NOT NULL AND a.video_arquivo <> '') AS tem_video,
                   c.nome, c.sobrenome
            FROM avaliacoes a
            JOIN contatos c ON c.id = a.contato_id";
    $params = [];
    if ($q !== null && mb_strlen($q) >= 2) {
        $sql .= " WHERE CONCAT_WS(' ', c.nome, c.sobrenome, c.whatsapp) LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY a.data_avaliacao DESC, a.id DESC LIMIT 300";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    sucesso($stmt->fetchAll());
}

function obter() {
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }
    $stmt = db()->prepare(
        "SELECT a.*, c.nome, c.sobrenome, c.whatsapp
         FROM avaliacoes a JOIN contatos c ON c.id = a.contato_id
         WHERE a.id = :id"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) { erro('Avaliação não encontrada.', [], 404); }
    sucesso($row);
}

function porContato() {
    $contatoId = (int) query('contato_id');
    if ($contatoId <= 0) { erro('Contato inválido.', [], 400); }
    $stmt = db()->prepare(
        "SELECT id, data_avaliacao, nota_media,
                (video_arquivo IS NOT NULL AND video_arquivo <> '') AS tem_video
         FROM avaliacoes WHERE contato_id = :c
         ORDER BY data_avaliacao DESC, id DESC"
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

// ------------------------------------------------------------
// Escrita
// ------------------------------------------------------------

// Converte "8", "8.5" ou "8,5" em decimal 0-10 (ou null).
function parseNota($v, $campo) {
    if ($v === null || $v === '') { return null; }
    $v = str_replace(',', '.', (string) $v);
    if (!is_numeric($v)) {
        erro_validacao([campo_erro($campo, 'NOTA_INVALIDA', 'Nota inválida (use 0 a 10).')]);
    }
    $n = round((float) $v, 2);
    if ($n < 0 || $n > 10) {
        erro_validacao([campo_erro($campo, 'NOTA_FORA', 'A nota deve ficar entre 0 e 10.')]);
    }
    return $n;
}

function salvar($editar) {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }

    $id = $editar ? (int) query('id') : 0;
    if ($editar && $id <= 0) { erro('ID inválido.', [], 400); }

    $contatoId = (int) in('contato_id');
    if ($contatoId <= 0) {
        erro_validacao([campo_erro('contato_id', 'OBRIGATORIO', 'Selecione o aluno avaliado.')]);
    }
    $existe = db()->prepare('SELECT id FROM contatos WHERE id = :c');
    $existe->execute([':c' => $contatoId]);
    if (!$existe->fetch()) { erro('Contato não encontrado.', [], 404); }

    $data = validar_data(in('data_avaliacao'), 'data_avaliacao') ?? date('Y-m-d');

    // Notas + média (média das notas preenchidas).
    $notas = [];
    $soma = 0.0; $qtd = 0;
    foreach (CRITERIOS_PROVA as $c) {
        $n = parseNota(in($c), $c);
        $notas[$c] = $n;
        if ($n !== null) { $soma += $n; $qtd++; }
    }
    $media = $qtd > 0 ? round($soma / $qtd, 2) : null;

    $campos = array_merge([
        'contato_id'      => $contatoId,
        'data_avaliacao'  => $data,
        'nota_media'      => $media,
        'feedback'        => in('feedback'),
        'proximos_passos' => in('proximos_passos'),
    ], $notas);

    if ($editar) {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($campos)));
        $stmt = db()->prepare("UPDATE avaliacoes SET $sets WHERE id = :id");
        $bind = [':id' => $id];
        foreach ($campos as $k => $v) { $bind[":$k"] = $v; }
        $stmt->execute($bind);

        if ($stmt->rowCount() === 0) {
            $chk = db()->prepare('SELECT id FROM avaliacoes WHERE id = :id');
            $chk->execute([':id' => $id]);
            if (!$chk->fetch()) { erro('Avaliação não encontrada.', [], 404); }
        }
        registrar_log('atualizar', 'avaliacao', $id, ['contato_id' => $contatoId]);
        sucesso(['id' => $id, 'nota_media' => $media], 'Prova atualizada.');
    }

    $colunas = implode(', ', array_keys($campos));
    $marcadores = implode(', ', array_map(fn($k) => ":$k", array_keys($campos)));
    $stmt = db()->prepare("INSERT INTO avaliacoes ($colunas) VALUES ($marcadores)");
    $bind = [];
    foreach ($campos as $k => $v) { $bind[":$k"] = $v; }
    $stmt->execute($bind);

    $novoId = (int) db()->lastInsertId();
    registrar_log('criar', 'avaliacao', $novoId, ['contato_id' => $contatoId]);
    sucesso(['id' => $novoId, 'nota_media' => $media], 'Prova registrada.');
}

function uploadVideo() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }

    $stmt = db()->prepare('SELECT video_arquivo FROM avaliacoes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $av = $stmt->fetch();
    if (!$av) { erro('Avaliação não encontrada.', [], 404); }

    if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
        erro_validacao([campo_erro('video', 'SEM_ARQUIVO', 'Selecione um arquivo de vídeo.')]);
    }
    $f = $_FILES['video'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $grande = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        $msg = $grande ? 'Arquivo maior que o limite permitido (200 MB).' : 'Falha no upload do arquivo.';
        erro_validacao([campo_erro('video', 'UPLOAD_FALHOU', $msg)]);
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, VIDEO_EXTENSOES, true)) {
        erro_validacao([campo_erro('video', 'EXT_INVALIDA', 'Formato não suportado. Use mp4, mov, webm, mkv ou avi.')]);
    }
    if (function_exists('finfo_open') && is_readable($f['tmp_name'])) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
        if ($mime && strpos($mime, 'video/') !== 0 && $mime !== 'application/octet-stream') {
            erro_validacao([campo_erro('video', 'MIME_INVALIDO', 'O arquivo não parece ser um vídeo.')]);
        }
    }

    $dir = STORAGE_DIR . '/videos';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $nome = 'av' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nome)) {
        erro('Não foi possível salvar o vídeo.', [], 500);
    }
    // Remove o vídeo anterior, se havia.
    if (!empty($av['video_arquivo'])) {
        $antigo = $dir . '/' . basename($av['video_arquivo']);
        if (is_file($antigo)) { @unlink($antigo); }
    }
    db()->prepare('UPDATE avaliacoes SET video_arquivo = :v WHERE id = :id')
        ->execute([':v' => $nome, ':id' => $id]);
    registrar_log('upload_video', 'avaliacao', $id);
    sucesso(['video_arquivo' => $nome], 'Vídeo enviado.');
}

function excluir() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }

    $stmt = db()->prepare('SELECT video_arquivo FROM avaliacoes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $av = $stmt->fetch();
    if (!$av) { erro('Avaliação não encontrada.', [], 404); }

    db()->prepare('DELETE FROM avaliacoes WHERE id = :id')->execute([':id' => $id]);
    if (!empty($av['video_arquivo'])) {
        $arq = STORAGE_DIR . '/videos/' . basename($av['video_arquivo']);
        if (is_file($arq)) { @unlink($arq); }
    }
    registrar_log('excluir', 'avaliacao', $id);
    sucesso(null, 'Prova removida.');
}

// ------------------------------------------------------------
// Streaming do vídeo (fora do docroot) com suporte a Range/seek.
// ------------------------------------------------------------
function streamVideo() {
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }
    $stmt = db()->prepare('SELECT video_arquivo FROM avaliacoes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row || empty($row['video_arquivo'])) { erro('Vídeo não encontrado.', [], 404); }

    $caminho = STORAGE_DIR . '/videos/' . basename($row['video_arquivo']);
    if (!is_file($caminho)) { erro('Arquivo de vídeo ausente.', [], 404); }

    // A partir daqui a resposta é binária, não o envelope JSON.
    while (ob_get_level() > 0) { ob_end_clean(); }
    $tamanho = filesize($caminho);
    $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
    $mimes = [
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime',
        'webm' => 'video/webm', 'ogg' => 'video/ogg', 'ogv' => 'video/ogg',
        'mkv' => 'video/x-matroska', 'avi' => 'video/x-msvideo',
    ];
    header_remove('Content-Type');
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Accept-Ranges: bytes');

    $inicio = 0; $fim = $tamanho - 1;
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') { $inicio = (int) $m[1]; }
        if ($m[2] !== '') { $fim = (int) $m[2]; }
        if ($inicio > $fim || $inicio >= $tamanho) {
            http_response_code(416);
            header("Content-Range: bytes */$tamanho");
            exit;
        }
        $fim = min($fim, $tamanho - 1);
        http_response_code(206);
        header("Content-Range: bytes $inicio-$fim/$tamanho");
    }
    header('Content-Length: ' . ($fim - $inicio + 1));

    $fp = fopen($caminho, 'rb');
    fseek($fp, $inicio);
    $restante = $fim - $inicio + 1;
    while ($restante > 0 && !feof($fp)) {
        $bloco = ($restante > 8192) ? 8192 : $restante;
        echo fread($fp, $bloco);
        flush();
        $restante -= $bloco;
    }
    fclose($fp);
    exit;
}
