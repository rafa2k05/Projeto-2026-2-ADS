<?php
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["erro" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/../config/conexao.php";

$dados = json_decode(file_get_contents("php://input"), true);

$nome           = trim($dados['nome'] ?? '');
$email          = strtolower(trim($dados['email'] ?? ''));
$senha          = $dados['senha'] ?? '';
$confirmarSenha = $dados['confirmarSenha'] ?? '';

if ($nome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
    http_response_code(400);
    echo json_encode(["erro" => "Preencha todos os campos."]);
    exit;
}

if (mb_strlen($nome) > 100) {
    http_response_code(400);
    echo json_encode(["erro" => "Nome muito longo."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["erro" => "E-mail inválido."]);
    exit;
}

if ($senha !== $confirmarSenha) {
    http_response_code(400);
    echo json_encode(["erro" => "As senhas não coincidem."]);
    exit;
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)");
    $stmt->execute([
        ":nome"  => $nome,
        ":email" => $email,
        ":senha" => $senhaHash
    ]);

    http_response_code(201);
    echo json_encode(["sucesso" => true, "mensagem" => "Cadastro realizado com sucesso."]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo