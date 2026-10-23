<?php
declare(strict_types=1);

// Load shared helpers so responder() is always available here.
// Carrega as funções compartilhadas para que responder() sempre exista aqui.
// (Adjust the path if your folder layout is different.)
// (Ajuste o caminho se a estrutura das suas pastas for diferente.)
require_once __DIR__ . '/../helpers/funcoes.php';

// Default settings, meant for local development only.
// Configurações padrão, feitas apenas para desenvolvimento local.
$cfg = [
    'host'    => 'localhost',
    'porta'   => 3306,
    'banco'   => 'larissa_beauty',
    'usuario' => 'root',
    'senha'   => '',
];

$arquivoLocal = __DIR__ . '/config.php';
if (is_file($arquivoLocal)) {
    $override = require $arquivoLocal;

    // Only merge if the file really returned an array,
    // otherwise array_merge() would throw a TypeError.
    // Só mescla se o arquivo realmente retornou um array,
    // senão o array_merge() causaria um erro fatal (TypeError).
    if (is_array($override)) {
        $cfg = array_merge($cfg, $override);
    } else {
        error_log('config.php must return an array. Using default settings.');
    }
}

try {
    // Build the DSN (connection string) from the settings above.
    // Monta o DSN (texto de conexão) com as configurações acima.
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        (int) $cfg['porta'],
        $cfg['banco']
    );

    $pdo = new PDO($dsn, $cfg['usuario'], $cfg['senha'], [
        // Throw exceptions on SQL errors.
        // Lança exceções quando houver erro de SQL.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Rows come back as associative arrays.
        // Os resultados voltam como arrays associativos (['coluna' => valor]).
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Real prepared statements (anti SQL Injection).
        // Prepared statements de verdade (proteção contra SQL Injection).
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Give up after 5s instead of hanging forever.
        // Desiste após 5s em vez de ficar travado para sempre.
        PDO::ATTR_TIMEOUT            => 5,
    ]);
} catch (PDOException $e) {
    // Log technical details privately, never show them to the user.
    // Registra os detalhes técnicos no log, nunca mostra ao usuário.
    error_log('Database connection failed: ' . $e->getMessage());
    responder(500, ['erro' => 'Erro interno. Tente novamente.']);
}

// Remove credentials from the global scope; only $pdo stays available.
// Remove as credenciais do escopo global; só o $pdo continua disponível.
unset($cfg, $override, $dsn);
?>