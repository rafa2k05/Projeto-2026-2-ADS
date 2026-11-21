<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('GET');

require_once __DIR__ . '/../config/conexao.php';
$admin = exigir_admin($pdo);
try {
 $dias=$pdo->query('SELECT id,data,motivo,criado_em FROM bloqueios_dias ORDER BY data')->fetchAll();
 $horas=$pdo->query("SELECT id,data,TIME_FORMAT(hora,'%H:%i') hora,motivo,criado_em FROM bloqueios_horarios ORDER BY data,hora")->fetchAll();
 responder(200,['dias'=>$dias,'horarios'=>$horas]);
} catch(PDOException $e){error_log($e->getMessage());responder(500,['erro'=>'Não foi possível listar os bloqueios.']);}
?>