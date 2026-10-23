<?php
declare(strict_types=1);

// Load shared helpers (iniciar_api, exigir_origem_e_csrf, encerrar_sessao, responder).
// Carrega as funções compartilhadas (iniciar_api, exigir_origem_e_csrf, encerrar_sessao, responder).
require_once __DIR__ . '/../helpers/funcoes.php';

// Only POST is allowed: logout changes state, so it must never run via GET
// (a GET could be triggered by an <img> tag or a link on another site).
// Só aceita POST: o logout muda o estado, então nunca deve rodar via GET
// (um GET poderia ser disparado por uma tag <img> ou link em outro site).
iniciar_api('POST');

// Security check: the request must come from our own site (Origin)
// and carry a valid CSRF token, so other sites can't log the user out.
// Verificação de segurança: a requisição precisa vir do nosso próprio site (Origin)
// e trazer um token CSRF válido, para que outros sites não deslogem o usuário.
exigir_origem_e_csrf();

// Destroy the session data, the session cookie and the session itself.
// Destrói os dados da sessão, o cookie da sessão e a própria sessão.
encerrar_sessao();

// Send the JSON response confirming the logout.
// Envia a resposta JSON confirmando o logout.
responder(200, ['sucesso' => true, 'mensagem' => 'Você saiu da conta.']);
?>