<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('GET');
require_once __DIR__ . '/../config/conexao.php';
$admin=exigir_admin($pdo);
try {
    $hoje=(new DateTimeImmutable('today'))->format('Y-m-d');
    $fim=(new DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d');
    $st=$pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE data=:hoje AND status IN ('pendente','confirmado')");
    $st->execute(['hoje'=>$hoje]); $hojeCount=(int)$st->fetchColumn();
    $pend=(int)$pdo->query("SELECT COUNT(*) FROM agendamentos WHERE status='pendente' AND data>=CURDATE()")->fetchColumn();
    $st=$pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE data BETWEEN :hoje AND :fim AND status IN ('pendente','confirmado')");
    $st->execute(['hoje'=>$hoje,'fim'=>$fim]); $prox=(int)$st->fetchColumn();
    $st=$pdo->prepare("SELECT a.id,a.cliente_nome,a.cliente_telefone,TIME_FORMAT(a.hora,'%H:%i') hora,a.status,s.nome servico
        FROM agendamentos a INNER JOIN servicos s ON s.id=a.servico_id
        WHERE a.data=:hoje ORDER BY a.hora");
    $st->execute(['hoje'=>$hoje]);
    responder(200,['nome'=>$admin['nome'],'hoje'=>$hojeCount,'pendentes'=>$pend,'proximos'=>$prox,'lista'=>$st->fetchAll()]);
} catch(PDOException $e) {
    error_log('Admin resumo: '.$e->getMessage());
    responder(500,['erro'=>'Não foi possível carregar o resumo da agenda.']);
}
