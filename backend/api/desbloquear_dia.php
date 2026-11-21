<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('POST');
exigir_origem_e_csrf();
require_once __DIR__ . '/../config/conexao.php';
$admin = exigir_admin($pdo);
$d=ler_json(); $data=data_iso_valida(campo($d,'data'),true); if(!$data) responder(400,['erro'=>'Data inválida.']);
$dataSql=$data->format('Y-m-d');
try { $pdo->beginTransaction(); bloquear_linha_dia($pdo,$dataSql); $st=$pdo->prepare('DELETE FROM bloqueios_dias WHERE data=:data'); $st->execute(['data'=>$dataSql]); $pdo->commit(); responder(200,['sucesso'=>true,'mensagem'=>'Bloqueio do dia removido.']); }
catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());responder(500,['erro'=>'Não foi possível remover o bloqueio.']);}
?>