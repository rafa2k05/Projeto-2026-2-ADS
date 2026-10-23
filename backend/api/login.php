<?php
declare(strict_types=1);

// Load shared helpers (iniciar_api, ler_json, campo, responder, login_bloqueado...).
// Carrega as funções compartilhadas (iniciar_api, ler_json, campo, responder, login_bloqueado...).
require_once __DIR__ . '/../helpers/funcoes.php';

// Only POST is allowed (the method check is done inside iniciar_api).
// Só aceita POST (a checagem do método é feita dentro do iniciar_api).
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

// E-mail is normalized to lowercase; the password is NOT trimmed/changed (false).
// O e-mail é normalizado para minúsculas; a senha NÃO é alterada nem tem espaços cortados (false).
$email = mb_strtolower(campo($dados, 'email'), 'UTF-8');
$senha = campo($dados, 'senha', false);

// Both fields are required.
// Os dois campos são obrigatórios.
if ($email === '' || $senha === '') {
    responder(400, ['erro' => 'Preencha e-mail e senha.']);
}

// Reject malformed e-mails early, before touching the database.
// Rejeita e-mails mal formados logo no início, antes de acessar o banco.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, ['erro' => 'E-mail inválido.']);
}

// Brute-force protection: too many recent failures for this e-mail/IP means a temporary block.
// Proteção contra força bruta: muitas falhas recentes para este e-mail/IP geram um bloqueio temporário.
$ip = ip_cliente();
if (login_bloqueado($pdo, $email, $ip)) {
    header('Retry-After: 900'); // tells the client to wait 900s (15 min) / avisa o cliente para esperar 900s (15 min)
    responder(429, ['erro' => 'Muitas tentativas. Aguarde 15 minutos e tente de novo.']);
}

try {
    // Look the user up with a prepared statement (anti SQL Injection).
    // Busca o usuário com prepared statement (proteção contra SQL Injection).
    $st = $pdo->prepare('SELECT id, senha FROM usuarios WHERE email = :email LIMIT 1');
    $st->execute(['email' => $email]);
    $usuario = $st->fetch();

    if ($usuario === false) {
        // User not found: spend the SAME time as a real check, so response time
        // does not reveal which e-mails exist (user enumeration).
        // Usuário não existe: gasta o MESMO tempo de uma verificação real, para que o tempo
        // de resposta não revele quais e-mails existem (enumeração de usuários).
        password_hash($senha, PASSWORD_DEFAULT);
        $ok = false;
    } else {
        // bcrypt only reads the first 72 bytes, so longer passwords are refused.
        // O bcrypt só lê os primeiros 72 bytes, então senhas maiores são recusadas.
        $ok = strlen($senha) <= 72 && password_verify($senha, $usuario['senha']);
    }

    if (!$ok) {
        // Count the failure and answer with the same message in both cases.
        // Conta a falha e responde com a mesma mensagem nos dois casos.
        registrar_falha_login($pdo, $email, $ip);
        responder(401, ['erro' => 'E-mail ou senha incorretos.']);
    }

    // Successful login: clear the failure counter for this e-mail/IP.
    // Login correto: zera o contador de falhas deste e-mail/IP.
    limpar_falhas_login($pdo, $email, $ip);

    // If PHP now uses a stronger hash algorithm, upgrade this user's stored hash.
    // Se o PHP passou a usar um algoritmo de hash mais forte, atualiza o hash guardado deste usuário.
    if (password_needs_rehash($usuario['senha'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id')
            ->execute(['senha' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $usuario['id']]);
    }

    // New session ID on every login (anti session fixation).
    // Novo ID de sessão a cada login (proteção contra session fixation).
    session_regenerate_id(true);

    // Store ONLY the user id; the other data is loaded fresh from the database when needed.
    // Guarda SÓ o id do usuário; os outros dados são buscados no banco quando necessário.
    $_SESSION['usuario_id'] = (int) $usuario['id'];

    // Load the user data to send back to the frontend.
    // Carrega os dados do usuário para devolver ao frontend.
    $logado = usuario_logado($pdo);

    responder(200, [
        'sucesso'  => true,
        'mensagem' => 'Login realizado com sucesso.',
        'usuario'  => [
            'id'    => (int) $logado['id'],
            'nome'  => $logado['nome'],
            'email' => $logado['email'],
        ],
    ]);
} catch (PDOException $e) {
    // Log technical details privately and answer with a generic message.
    // Registra os detalhes técnicos no log e responde com uma mensagem genérica.
    error_log('Login error: ' . $e->getMessage());
    responder(500, ['erro' => 'Erro no servidor. Tente novamente.']);
}
?>