<?php
declare(strict_types=1);

// Load shared helpers (iniciar_api, ler_json, campo, validators, responder...).
// Carrega as funções compartilhadas (iniciar_api, ler_json, campo, validadores, responder...).
require_once __DIR__ . '/../helpers/funcoes.php';

// Only POST is allowed: registering creates data.
// Só aceita POST: o cadastro cria dados.
iniciar_api('POST');

// Security check: request must come from our site and carry a valid CSRF token.
// Verificação de segurança: a requisição precisa vir do nosso site e ter um token CSRF válido.
exigir_origem_e_csrf();

// Open the database connection ($pdo).
// Abre a conexão com o banco de dados ($pdo).
require_once __DIR__ . '/../config/conexao.php';

// Read the JSON body and extract the fields.
// Lê o corpo JSON e extrai os campos.
$dados = ler_json();

// Name and e-mail are trimmed; e-mail goes to lowercase.
// Nome e e-mail têm os espaços cortados; o e-mail vai para minúsculas.
$nome  = campo($dados, 'nome');
$email = mb_strtolower(campo($dados, 'email'), 'UTF-8');

// Passwords are NOT trimmed (spaces may be part of the password).
// As senhas NÃO são alteradas (espaços podem fazer parte da senha).
$senha     = campo($dados, 'senha', false);
$confirmar = campo($dados, 'confirmarSenha', false);

// All fields are required.
// Todos os campos são obrigatórios.
if ($nome === '' || $email === '' || $senha === '' || $confirmar === '') {
    responder(400, ['erro' => 'Preencha todos os campos.']);
}

// Validate each field with the shared helpers (same rules everywhere in the app).
// Valida cada campo com os helpers compartilhados (mesmas regras em todo o app).
if (!nome_valido($nome)) {
    responder(400, ['erro' => 'Nome inválido (use de 3 a 100 letras).']);
}
if (!email_valido($email)) {
    responder(400, ['erro' => 'E-mail inválido.']);
}
if (!senha_valida($senha)) {
    responder(400, ['erro' => 'A senha deve ter de 8 a 72 caracteres.']);
}

// Password and confirmation must match.
// A senha e a confirmação precisam ser iguais.
if ($senha !== $confirmar) {
    responder(400, ['erro' => 'As senhas não coincidem.']);
}

// Never store the plain password: store only its hash.
// Nunca guarda a senha pura: guarda apenas o hash dela.
$hash = password_hash($senha, PASSWORD_DEFAULT);

try {
    // Insert the new user with a prepared statement (anti SQL Injection).
    // Insere o novo usuário com prepared statement (proteção contra SQL Injection).
    $pdo->prepare('INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)')
        ->execute(['nome' => $nome, 'email' => $email, 'senha' => $hash]);
} catch (PDOException $e) {
    // MySQL error 1062 = duplicate value (the e-mail already exists).
    // Erro 1062 do MySQL = valor duplicado (o e-mail já existe).
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        responder(409, ['erro' => 'Este e-mail já está cadastrado.']);
    }

    // Any other database error: log privately, answer with a generic message.
    // Qualquer outro erro de banco: registra no log e responde com mensagem genérica.
    error_log('Register error: ' . $e->getMessage());
    responder(500, ['erro' => 'Erro no servidor. Tente novamente.']);
}

// User created: answer with 201 (Created).
// Usuário criado: responde com 201 (Created).
responder(201, ['sucesso' => true, 'mensagem' => 'Cadastro realizado com sucesso.']);
?>