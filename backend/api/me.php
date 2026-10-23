<?php
declare(strict_types=1);

// Load shared helpers (responder, iniciar_api, usuario_logado, token_csrf...).
// Carrega as funções compartilhadas (responder, iniciar_api, usuario_logado, token_csrf...)
require_once __DIR__ . '/../helpers/funcoes.php';

// Start the API for this endpoint: only GET requests are allowed here.
// Inicia a API deste endpoint: só aceita requisições GET.
iniciar_api('GET');

// Open the database connection ($pdo).
// Abre a conexão com o banco de dados ($pdo).
require_once __DIR__ . '/../config/conexao.php';

// The frontend calls this file on page load to find out if someone is logged in,
// and it also receives the CSRF token it must send in later POST requests.
// O front chama este arquivo ao carregar a página para saber se há alguém logado,
// e já recebe o token CSRF que precisa enviar nos POSTs seguintes.

// Get the logged-in user from the session (null if nobody is logged in).
// Busca o usuário logado na sessão (null se ninguém estiver logado).
$usuario = usuario_logado($pdo);

// Send the JSON response with the login state.
// Envia a resposta JSON com o estado do login.
responder(200, [
    // true if there is a user, false otherwise.
    // true se houver usuário, false caso contrário.
    'logado'  => $usuario !== null,
    
    // Expose only safe fields (never password hashes or other internal data).
    // Expõe só campos seguros (nunca hash de senha ou outros dados internos).
    'usuario' => $usuario === null ? null : [
        'id'    => (int) $usuario['id'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
    ],

    // CSRF token for the frontend to attach to POST requests.
    // Token CSRF para o front anexar nas requisições POST.
    'csrf'    => token_csrf(),
]);
?>