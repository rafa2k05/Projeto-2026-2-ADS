<?php
declare(strict_types=1);

// Load shared helpers (iniciar_api, data_agendavel, responder, HORARIOS_FIXOS).
// Carrega as funções compartilhadas (iniciar_api, data_agendavel, responder, HORARIOS_FIXOS).
require_once __DIR__ . '/../helpers/funcoes.php';

// Only GET is allowed: this endpoint just reads data.
// Só aceita GET: este endpoint apenas lê dados.
iniciar_api('GET');

// Open the database connection ($pdo).
// Abre a conexão com o banco de dados ($pdo).
require_once __DIR__ . '/../config/conexao.php';

// Usage: listar_horarios.php?data=2026-11-05
// Uso: listar_horarios.php?data=2026-11-05
$texto = $_GET['data'] ?? '';

// Validate the date: it must be a string, a real date and inside the booking period.
// Valida a data: precisa ser texto, uma data real e estar dentro do período de agendamento.
// (is_string blocks tricks like ?data[]=x, which would arrive as an array)
// (is_string bloqueia truques como ?data[]=x, que chegaria como array)
$data = is_string($texto) ? data_agendavel($texto) : null;
if ($data === null) {
    responder(400, ['erro' => 'Data inválida ou fora do período de agendamento.']);
}

// Keep the formatted date in a variable, so we format it only once.
// Guarda a data formatada em uma variável, para formatar só uma vez.
$dataSql = $data->format('Y-m-d');

try {
    // Get the times already taken on that day (cancelled bookings free the slot).
    // Busca os horários já ocupados nesse dia (agendamentos cancelados liberam o horário).
    $st = $pdo->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') FROM agendamentos
          WHERE data = :data AND status <> 'cancelado'"
    );
    $st->execute(['data' => $dataSql]);

    // FETCH_COLUMN returns a flat list like ['09:00', '14:30'].
    // FETCH_COLUMN devolve uma lista simples, como ['09:00', '14:30'].
    $ocupados = $st->fetchAll(PDO::FETCH_COLUMN);

    // Free times = all fixed times minus the taken ones.
    // array_values reindexes the result so the JSON is a list, not an object.
    // Horários livres = todos os horários fixos menos os ocupados.
    // array_values reindexa o resultado para o JSON ser uma lista, e não um objeto.
    $livres = array_values(array_diff(HORARIOS_FIXOS, $ocupados));

    // Send the date and the free times as JSON.
    // Envia a data e os horários livres em JSON.
    responder(200, ['data' => $dataSql, 'horarios' => $livres]);
} catch (PDOException $e) {
    // Log technical details privately and answer with a generic message.
    // Registra os detalhes técnicos no log e responde com uma mensagem genérica.
    error_log('List times error: ' . $e->getMessage());
    responder(500, ['erro' => 'Erro no servidor. Tente novamente.']);
}
?>