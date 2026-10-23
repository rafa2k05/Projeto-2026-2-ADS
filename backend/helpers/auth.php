<?php
function iniciarSessao() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function exigirLogin() {
    iniciarSessao();
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        header("Content-Type: application/json");
        echo json_encode(["erro" => "Não autenticado."]);
        exit;
    }
}

function exigirAdmin() {
    exigirLogin();
    if (($_SESSION['tipo'] ?? '') !== 'admin') {
        http_response_code(403);
        header("Content-Type: application/json");
        echo json_encode(["erro" => "Acesso negado."]);
        exit;
    }
}