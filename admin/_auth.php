<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/helpers/funcoes.php';
require_once __DIR__ . '/../backend/config/conexao.php';
iniciar_sessao();
$admin = usuario_logado($pdo);
if (!$admin) { header('Location: login.php'); exit; }
$st=$pdo->prepare("SELECT tipo FROM usuarios WHERE id=?");
$st->execute([$admin['id']]);
if ($st->fetchColumn() !== 'admin') { http_response_code(403); exit('Acesso restrito à administradora.'); }
