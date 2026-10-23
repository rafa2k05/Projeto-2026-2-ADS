<?php
session_start();
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["erro" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/../config/conexao.php";

$dados = json_decode(file_get_contents("php://input"), true);

$email = strtolower(trim($dados['email'] ?? ''));
$senha = $dados['senha'] ?? '';

if ($email === '' || $senha === '') {
    http_response_code(400);
    echo json_encode(["erro" => "Preencha e-mail e senha."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["erro" => "E-mail inválido."]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([":email" => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        http_response_code(401);
        echo json_encode(["erro" => "E-mail ou senha incorretos."]);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id']    = $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['tipo']          = $usuario['tipo'];

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Login realizado com sucesso.",
        "usuario" => [
            "id" => $usuario['id'],
            "nome" => $usuario['nome'],
            "email" => $usuario['email'],
            "tipo" => $usuario['tipo']
        ]
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Erro no servidor. Tente novamente."]);
}