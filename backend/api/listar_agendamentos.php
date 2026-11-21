<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('GET');

require_once __DIR__ . '/../config/conexao.php';
$admin = exigir_admin($pdo);
$data=isset($_GET['data']) && is_string($_GET['data']) ? $_GET['data'] : '';
try {
 $sql="SELECT a.id,a.cliente_nome,a.cliente_telefone,a.data,TIME_FORMAT(a.hora,'%H:%i') hora,a.duracao_aplicada,a.preco_aplicado,a.status,s.nome servico,a.criado_em FROM agendamentos a JOIN servicos s ON s.id=a.servico_id";
 if($data!==''){ if(!data_iso_valida($data,true)) responder(400,['erro'=>'Data inválida.']); $st=$pdo->prepare($sql.' WHERE a.data=:data ORDER BY a.data,a.hora');$st->execute(['data'=>$data]);$rows=$st->fetchAll(); }
 else { $rows=$pdo->query($sql.' ORDER BY a.data DESC,a.hora DESC')->fetchAll(); }
 responder(200,['agendamentos'=>$rows]);
} catch(PDOException $e){error_log($e->getMessage());responder(500,['erro'=>'Não foi possível listar os agendamentos.']);}
?>