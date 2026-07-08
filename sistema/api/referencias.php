<?php
// ============================================================
// Referências — listas fixas (ENUMs) para popular os selects do frontend
// e a grade de turmas do banco. Fonte única de verdade das opções.
// GET (autenticado)
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

$turmas = db()->query('SELECT id, nome, dia_semana, TIME_FORMAT(horario, "%H:%i") AS horario FROM turmas WHERE ativo = 1 ORDER BY FIELD(dia_semana,"segunda","terca","quarta","quinta"), horario')->fetchAll();

sucesso([
    'uf' => ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'],
    'par_situacao' => [
        ['valor' => 'com_par', 'rotulo' => 'Com par'],
        ['valor' => 'sem_par', 'rotulo' => 'Sem par'],
    ],
    'papel' => [
        ['valor' => 'lider', 'rotulo' => 'Líder'],
        ['valor' => 'seguidora', 'rotulo' => 'Seguidora'],
    ],
    'tipo_contato' => [
        ['valor' => 'nao_aluno', 'rotulo' => 'Não aluno'],
        ['valor' => 'aluno', 'rotulo' => 'Aluno'],
        ['valor' => 'ex_aluno', 'rotulo' => 'Ex-aluno'],
        ['valor' => 'nao_contatar', 'rotulo' => 'Não contatar'],
    ],
    'plano' => [
        ['valor' => 'mensalista', 'rotulo' => 'Mensalista'],
        ['valor' => 'trimestral', 'rotulo' => 'Trimestralidade'],
        ['valor' => 'anual', 'rotulo' => 'Anuidade'],
    ],
    'status_aluno' => [
        ['valor' => 'novo', 'rotulo' => 'Novo'],
        ['valor' => 'fidelizado', 'rotulo' => 'Fidelizado'],
    ],
    'status_nao_aluno' => [
        ['valor' => 'nao_conhece', 'rotulo' => 'Não conhece'],
        ['valor' => 'conhece_nao_quer', 'rotulo' => 'Conhece, não quer'],
        ['valor' => 'conhece_nao_quer_agora', 'rotulo' => 'Conhece, não quer agora'],
        ['valor' => 'conhece_quer', 'rotulo' => 'Conhece, quer'],
    ],
    'status_ex_aluno' => [
        ['valor' => 'quer_voltar', 'rotulo' => 'Quer voltar'],
        ['valor' => 'nao_quer_voltar', 'rotulo' => 'Não quer voltar'],
        ['valor' => 'quer_voltar_nao_agora', 'rotulo' => 'Quer voltar, mas não agora'],
    ],
    'status_nao_contatar' => [
        ['valor' => 'bloqueou', 'rotulo' => 'Bloqueou'],
        ['valor' => 'mal_educado', 'rotulo' => 'Mal educado'],
        ['valor' => 'concorrencia', 'rotulo' => 'Concorrência'],
        ['valor' => 'pediu_para_sair', 'rotulo' => 'Pediu para sair'],
    ],
    'estilos' => [
        ['valor' => 'vaneira', 'rotulo' => 'Vaneira'],
        ['valor' => 'forro', 'rotulo' => 'Forró'],
        ['valor' => 'samba', 'rotulo' => 'Samba'],
        ['valor' => 'sertanejo', 'rotulo' => 'Sertanejo'],
    ],
    'dias' => [
        ['valor' => 'segunda', 'rotulo' => 'Segunda'],
        ['valor' => 'terca', 'rotulo' => 'Terça'],
        ['valor' => 'quarta', 'rotulo' => 'Quarta'],
        ['valor' => 'quinta', 'rotulo' => 'Quinta'],
    ],
    'turmas' => $turmas,
]);
