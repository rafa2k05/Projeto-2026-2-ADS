<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('POST');
exigir_origem_e_csrf();
require_once __DIR__ . '/../config/conexao.php';
$admin = exigir_admin($pdo);
$d=ler_json(); $dataTexto=campo($d,'data'); $motivo=campo($d,'motivo');
$data=data_iso_valida($dataTexto,true); if(!$data) responder(400,['erro'=>'Informe uma data válida (hoje ou futura).']);
$dataSql=$data->format('Y-m-d');
try {
 $pdo->beginTransaction(); bloquear_linha_dia($pdo,$dataSql);
 $st=$pdo->prepare("SELECT id FROM agendamentos WHERE data=:data AND status IN ('pendente','confirmado') LIMIT 1 FOR UPDATE");
 $st->execute(['data'=>$dataSql]);
 if($st->fetch()){ $pdo->rollBack(); responder(409,['erro'=>'Já existe agendamento nesta data. Resolva ou cancele a reserva antes de bloquear o dia.']); }
 $st=$pdo->prepare('INSERT INTO bloqueios_dias (data,motivo,admin_id) VALUES (:data,:motivo,:admin)');
 $st->execute(['data'=>$dataSql,'motivo'=>$motivo!==''?$motivo:null,'admin'=>$admin['id']]);
 $pdo->commit(); responder(201,['sucesso'=>true,'mensagem'=>'Dia bloqueado.']);
} catch(PDOException $e){ if($pdo->inTransaction())$pdo->rollBack(); if((int)($e->errorInfo[1]??0)===1062)responder(409,['erro'=>'Este dia já está bloqueado.']); error_log($e->getMessage()); responder(500,['erro'=>'Não foi possível bloquear o dia.']); }
?>