<?php
declare(strict_types=1);

// Load shared helpers (iniciar_api, exigir_login, ler_json, campo, responder...).
// Carrega as funções compartilhadas (iniciar_api, exigir_login, ler_json, campo, responder...).
require_once __DIR__ . '/../helpers/funcoes.php';

// Only POST is allowed: booking creates data.
// Só aceita POST: o agendamento cria dados.
iniciar_api('POST');

// Security check: request must come from our site and carry a valid CSRF token.
// Verificação de segurança: a requisição precisa vir do nosso site e ter um token CSRF válido.
exigir_origem_e_csrf();

// Open the database connection ($pdo).
// Abre a conexão com o banco de dados ($pdo).
require_once __DIR__ . '/../config/conexao.php';

try {
    // The person booking is ALWAYS the logged-in user from the session.
    // Quem agenda é SEMPRE o usuário logado da sessão.
    $usuario = exigir_login($pdo);

    // Read the JSON body and extract the fields.
    // Lê o corpo JSON e extrai os campos.
    $dados     = ler_json();
    $servicoId = $dados['servico_id'] ?? null;
    $dataTexto = campo($dados, 'data');
    $hora      = campo($dados, 'hora');

    // All fields are required; the service id must be a real integer (not "1" or 1.5).
    // Todos os campos são obrigatórios; o id do serviço precisa ser inteiro de verdade (não "1" nem 1.5).
    if (!is_int($servicoId) || $servicoId < 1 || $dataTexto === '' || $hora === '') {
        responder(400, ['erro' => 'Informe serviço, data e horário.']);
    }

    // The date must be valid and inside the booking period.
    // A data precisa ser válida e estar dentro do período de agendamento.
    $data = data_agendavel($dataTexto);
    if ($data === null) {
        responder(400, ['erro' => 'Data inválida ou fora do período de agendamento.']);
    }
    $dataSql = $data->format('Y-m-d');

    // The time must be one of the allowed fixed times (strict comparison).
    // O horário precisa ser um dos horários fixos permitidos (comparação estrita).
    if (!in_array($hora, HORARIOS_FIXOS, true)) {
        responder(400, ['erro' => 'Horário inválido.']);
    }

    // Service name and price come from the DATABASE, never from what the browser sends.
    // Nome e preço do serviço vêm do BANCO, nunca do que o navegador envia.
    $st = $pdo->prepare('SELECT id, nome, preco FROM servicos WHERE id = :id AND ativo = 1');
    $st->execute(['id' => $servicoId]);
    $servico = $st->fetch();
    if ($servico === false) {
        responder(400, ['erro' => 'Serviço inválido.']);
    }

    // Per-customer limit (prevents someone from "filling up" the schedule).
    // Limite por cliente (evita que alguém "lote" a agenda).
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM agendamentos
          WHERE usuario_id = :u AND status <> 'cancelado' AND data >= CURDATE()"
    );
    $st->execute(['u' => $usuario['id']]);
    if ((int) $st->fetchColumn() >= MAX_AGENDAMENTOS_ATIVOS) {
        responder(409, ['erro' => 'Você já tem o máximo de agendamentos ativos.']);
    }

    // Create the booking with a prepared statement (anti SQL Injection).
    // Cria o agendamento com prepared statement (proteção contra SQL Injection).
    $pdo->prepare(
        'INSERT INTO agendamentos (usuario_id, servico_id, data, hora)
         VALUES (:u, :s, :d, :h)'
    )->execute([
        'u' => $usuario['id'],
        's' => $servico['id'],
        'd' => $dataSql,
        'h' => $hora,
    ]);

    // Grab the new id right after the insert, before any other query runs.
    // Pega o id novo logo após o insert, antes de qualquer outra consulta.
    $agendamentoId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    // The database UNIQUE key guarantees two people never get the same time slot.
    // MySQL error 1062 = duplicate value (slot already taken).
    // A chave UNIQUE do banco garante que duas pessoas nunca fiquem com o mesmo horário.
    // Erro 1062 do MySQL = valor duplicado (horário já ocupado).
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        responder(409, ['erro' => 'Esse horário acabou de ser reservado. Escolha outro.']);
    }

    // Any other database error: log privately, answer with a generic message.
    // Qualquer outro erro de banco: registra no log e responde com mensagem genérica.
    error_log('Booking error: ' . $e->getMessage());
    responder(500, ['erro' => 'Erro no servidor. Tente novamente.']);
}

// Booking created: answer with 201 (Created) and a summary.
// Agendamento criado: responde com 201 (Created) e um resumo.
responder(201, [
    'sucesso'     => true,
    'mensagem'    => 'Agendamento solicitado com sucesso.',
    'agendamento' => [
        'id'      => $agendamentoId,
        'cliente' => $usuario['nome'],
        'servico' => $servico['nome'],
        'preco'   => (float) $servico['preco'],
        'data'    => $dataSql,
        'hora'    => $hora,
        'status'  => 'pendente',
    ],
]);
?>