<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('GET');
require_once __DIR__ . '/../config/conexao.php';

$usuario = usuario_logado($pdo);

responder(200, [
    'logado'  => $usuario !== null,
    'usuario' => $usuario === null ? null : [
        'id'    => (int) $usuario['id'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
    ],
    'csrf'    => token_csrf(),
]);
