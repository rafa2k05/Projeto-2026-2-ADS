<?php
$host = "localhost";
$dbname = "uninove2026";
$usuario = "root";
$senha = "";
 
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["erro" => "Erro ao conectar com o banco de dados."]);
    exit;
}