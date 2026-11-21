<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('GET');
require_once __DIR__ . '/../config/conexao.php';
$dataParam = $_GET['data'] ?? '';
$serviceParam = $_GET['servico_id'] ?? '';
$data = is_string($dataParam) ? data_agendavel($dataParam) : null;
if (!$data) responder(400,['erro'=>'Data inválida ou fora do período de agendamento.']);
if (!is_string($serviceParam) || !ctype_digit($serviceParam) || (int)$serviceParam < 1) responder(400,['erro'=>'Selecione um serviço válido.']);
$dataSql=$data->format('Y-m-d');
try {
    $st=$pdo->prepare('SELECT duracao_minutos FROM servicos WHERE id=:id AND ativo=1');
    $st->execute(['id'=>(int)$serviceParam]);
    $duracao=$st->fetchColumn();
    if ($duracao===false) responder(404,['erro'=>'Serviço não encontrado.']);
    $st=$pdo->prepare('SELECT 1 FROM bloqueios_dias WHERE data=:data');
    $st->execute(['data'=>$dataSql]);
    if ($st->fetchColumn()) responder(200,['data'=>$dataSql,'horarios'=>[],'dia_bloqueado'=>true]);

    $st=$pdo->prepare("SELECT TIME_FORMAT(hora,'%H:%i') hora, duracao_aplicada FROM agendamentos
        WHERE data=:data AND status IN ('pendente','confirmado')");
    $st->execute(['data'=>$dataSql]);
    $reservas=$st->fetchAll();
    $st=$pdo->prepare("SELECT TIME_FORMAT(hora,'%H:%i') FROM bloqueios_horarios WHERE data=:data");
    $st->execute(['data'=>$dataSql]);
    $bloqueados=$st->fetchAll(PDO::FETCH_COLUMN);

    $livres=[]; $indisponiveis=[];
    foreach (HORARIOS_FIXOS as $slot) {
        $inicio=$slot.':00';
        $fim=horario_fim($slot,(int)$duracao);
        if (in_array($slot,$bloqueados,true)) { $indisponiveis[]=['hora'=>$slot,'tipo'=>'bloqueado']; continue; }
        $conflito=false;
        foreach ($reservas as $r) {
            $rInicio=$r['hora'].':00';
            $rFim=horario_fim(substr($r['hora'],0,5),(int)$r['duracao_aplicada']);
            if (intervalos_se_sobrepoem($inicio,$fim,$rInicio,$rFim)) { $conflito=true; break; }
        }
        if ($conflito) $indisponiveis[]=['hora'=>$slot,'tipo'=>'reservado'];
        else $livres[]=$slot;
    }
    responder(200,['data'=>$dataSql,'horarios'=>$livres,'indisponiveis'=>$indisponiveis,'dia_bloqueado'=>false]);
} catch (PDOException $e) {
    error_log('Erro ao listar horários: '.$e->getMessage());
    responder(500,['erro'=>'Não foi possível consultar os horários.']);
}
?>