<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('POST');
exigir_origem_e_csrf();
require_once __DIR__ . '/../config/conexao.php';
$admin = exigir_admin($pdo);
$d=ler_json(); $data=data_iso_valida(campo($d,'data'),true); $hora=campo($d,'hora'); $motivo=campo($d,'motivo');
if(!$data || !hora_valida($hora)) responder(400,['erro'=>'Informe uma data e um horário válido.']);
$dataSql=$data->format('Y-m-d'); $inicio=$hora.':00'; $fim=horario_fim($hora,60);
try {
 $pdo->beginTransaction(); bloquear_linha_dia($pdo,$dataSql);
 $st=$pdo->prepare('SELECT 1 FROM bloqueios_dias WHERE data=:data');$st->execute(['data'=>$dataSql]);
 if($st->fetchColumn()){ $pdo->rollBack(); responder(409,['erro'=>'O dia inteiro já está bloqueado.']); }
 $st=$pdo->prepare("SELECT id FROM agendamentos WHERE data=:data AND status IN ('pendente','confirmado') AND hora < :fim AND ADDTIME(hora,SEC_TO_TIME(duracao_aplicada*60)) > :inicio LIMIT 1 FOR UPDATE");
 $st->execute(['data'=>$dataSql,'fim'=>$fim,'inicio'=>$inicio]);
 if($st->fetch()){ $pdo->rollBack(); responder(409,['erro'=>'Já existe agendamento neste período. Resolva ou cancele a reserva antes de bloquear.']); }
 $st=$pdo->prepare('INSERT INTO bloqueios_horarios (data,hora,motivo,admin_id) VALUES (:data,:hora,:motivo,:admin)');
 $st->execute(['data'=>$dataSql,'hora'=>$inicio,'motivo'=>$motivo!==''?$motivo:null,'admin'=>$admin['id']]);
 $pdo->commit(); responder(201,['sucesso'=>true,'mensagem'=>'Horário bloqueado.']);
} catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((int)($e->errorInfo[1]??0)===1062)responder(409,['erro'=>'Este horário já está bloqueado.']);error_log($e->getMessage());responder(500,['erro'=>'Não foi possível bloquear o horário.']);}
?>