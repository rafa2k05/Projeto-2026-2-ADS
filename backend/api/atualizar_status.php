<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('POST');
exigir_origem_e_csrf();
require_once __DIR__ . '/../config/conexao.php';
$admin = exigir_admin($pdo);
$d=ler_json(); $id=$d['agendamento_id']??null; $status=campo($d,'status');
if((!is_int($id)&&!(is_string($id)&&ctype_digit($id)))||(int)$id<1||!in_array($status,['confirmado','cancelado'],true)) responder(400,['erro'=>'Dados inválidos.']);
try {
 $st=$pdo->prepare('SELECT data FROM agendamentos WHERE id=:id');$st->execute(['id'=>(int)$id]);$data=$st->fetchColumn();if(!$data)responder(404,['erro'=>'Agendamento não encontrado.']);
 $pdo->beginTransaction(); bloquear_linha_dia($pdo,$data);
 $st=$pdo->prepare("UPDATE agendamentos SET status=:status WHERE id=:id AND status<>'cancelado'");$st->execute(['status'=>$status,'id'=>(int)$id]);
 if($st->rowCount()===0){$pdo->rollBack();responder(409,['erro'=>'O agendamento já está cancelado ou não foi alterado.']);}
 $pdo->commit();responder(200,['sucesso'=>true,'mensagem'=>$status==='cancelado'?'Agendamento cancelado.':'Agendamento confirmado.']);
} catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());responder(500,['erro'=>'Não foi possível atualizar o agendamento.']);}
?>