<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('GET');
require_once __DIR__ . '/../config/conexao.php';
$usuario=usuario_logado($pdo);
if ($usuario !== null) {
    $st=$pdo->prepare('SELECT tipo FROM usuarios WHERE id=:id LIMIT 1');
    $st->execute(['id'=>$usuario['id']]);
    $usuario['tipo']=$st->fetchColumn() ?: 'cliente';
}
responder(200,[
    'logado'=>$usuario!==null,
    'usuario'=>$usuario===null?null:[
        'id'=>(int)$usuario['id'],
        'nome'=>$usuario['nome'],
        'email'=>$usuario['email'],
        'tipo'=>$usuario['tipo']
    ],
    'csrf'=>token_csrf()
]);
