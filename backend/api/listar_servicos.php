<?php
declare(strict_types=1);

// Load shared helpers (iniciar_api, responder).
// Carrega as funções compartilhadas (iniciar_api, responder).
require_once __DIR__ . '/../helpers/funcoes.php';

// Only GET is allowed: this endpoint just reads data.
// Só aceita GET: este endpoint apenas lê dados.
iniciar_api('GET');

// Open the database connection ($pdo).
// Abre a conexão com o banco de dados ($pdo).
require_once __DIR__ . '/../config/conexao.php';

try {
    // Fetch only active services. No user input is used here, so a plain query() is safe.
    // Busca só os serviços ativos. Nenhum dado do usuário é usado aqui, então query() simples é seguro.
    $servicos = $pdo->query(
        'SELECT id, nome, duracao_minutos, preco FROM servicos WHERE ativo = 1 ORDER BY id'
    )->fetchAll();

    // Cast each row to the correct types, so the JSON has numbers instead of strings.
    // Converte cada linha para os tipos corretos, para o JSON ter números em vez de textos.
    $servicos = array_map(static fn (array $s): array => [
        'id'              => (int) $s['id'],
        'nome'            => $s['nome'],
        'duracao_minutos' => (int) $s['duracao_minutos'],
        'preco'           => (float) $s['preco'],
    ], $servicos);

    // Send the list as JSON.
    // Envia a lista em JSON.
    responder(200, ['servicos' => $servicos]);
} catch (PDOException $e) {
    // Log technical details privately and answer with a generic message.
    // Registra os detalhes técnicos no log e responde com uma mensagem genérica.
    error_log('List services error: ' . $e->getMessage());
    responder(500, ['erro' => 'Erro no servidor. Tente novamente.']);
}
?>