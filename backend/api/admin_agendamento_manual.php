<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('POST');
exigir_origem_e_csrf();
require_once __DIR__ . '/../config/conexao.php';
$admin=exigir_admin($pdo);
$d=ler_json();

$nome=campo($d,'cliente_nome');
$telefone=telefone_normalizado(campo($d,'cliente_telefone'));
$servicoId=$d['servico_id']??null;
$dataTexto=campo($d,'data');
$hora=campo($d,'hora');

if (!nome_valido($nome)) responder(400,['erro'=>'Informe um nome válido (de 3 a 100 caracteres).']);
if (!telefone_valido($telefone)) responder(400,['erro'=>'Informe um telefone válido com DDD.']);
if ((!is_int($servicoId)&&!(is_string($servicoId)&&ctype_digit($servicoId)))||(int)$servicoId<1) responder(400,['erro'=>'Selecione um serviço válido.']);
$data=data_iso_valida($dataTexto,true);
if (!$data) responder(400,['erro'=>'Informe uma data válida de hoje em diante.']);
if (!hora_valida($hora)) responder(400,['erro'=>'Selecione um horário permitido.']);
$dataSql=$data->format('Y-m-d');

try {
    $st=$pdo->prepare('SELECT id,nome,duracao_minutos,preco FROM servicos WHERE id=:id AND ativo=1 LIMIT 1');
    $st->execute(['id'=>(int)$servicoId]);
    $servico=$st->fetch();
    if (!$servico) responder(400,['erro'=>'Serviço não encontrado ou inativo.']);

    $duracao=(int)$servico['duracao_minutos'];
    $inicio=$hora.':00';
    $fim=horario_fim($hora,$duracao);

    $pdo->beginTransaction();
    bloquear_linha_dia($pdo,$dataSql);

    $st=$pdo->prepare('SELECT 1 FROM bloqueios_dias WHERE data=:data LIMIT 1');
    $st->execute(['data'=>$dataSql]);
    if ($st->fetchColumn()) { $pdo->rollBack(); responder(409,['erro'=>'Este dia está bloqueado.']); }

    $st=$pdo->prepare('SELECT 1 FROM bloqueios_horarios WHERE data=:data AND hora=:hora LIMIT 1');
    $st->execute(['data'=>$dataSql,'hora'=>$inicio]);
    if ($st->fetchColumn()) { $pdo->rollBack(); responder(409,['erro'=>'Este horário está bloqueado.']); }

    $st=$pdo->prepare("SELECT id FROM agendamentos
        WHERE data=:data AND status IN ('pendente','confirmado')
          AND hora < :fim
          AND ADDTIME(hora,SEC_TO_TIME(duracao_aplicada*60)) > :inicio
        LIMIT 1 FOR UPDATE");
    $st->execute(['data'=>$dataSql,'fim'=>$fim,'inicio'=>$inicio]);
    if ($st->fetch()) { $pdo->rollBack(); responder(409,['erro'=>'Já existe um agendamento que conflita com esse horário.']); }

    $st=$pdo->prepare("INSERT INTO agendamentos
        (usuario_id,servico_id,cliente_nome,cliente_telefone,consentimento_whatsapp,data,hora,duracao_aplicada,preco_aplicado,status)
        VALUES (NULL,:servico,:nome,:telefone,0,:data,:hora,:duracao,:preco,'pendente')");
    $st->execute([
        'servico'=>(int)$servicoId,'nome'=>$nome,'telefone'=>$telefone,
        'data'=>$dataSql,'hora'=>$inicio,'duracao'=>$duracao,'preco'=>$servico['preco']
    ]);
    $id=(int)$pdo->lastInsertId();
    $pdo->commit();
    responder(201,['sucesso'=>true,'mensagem'=>'Agendamento manual salvo.','id'=>$id]);
} catch(PDOException $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    error_log('Agendamento manual: '.$e->getMessage());
    responder(500,['erro'=>'Não foi possível salvar o agendamento manual.']);
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    error_log('Agendamento manual: '.$e->getMessage());
    responder(500,['erro'=>'Erro interno ao salvar o agendamento.']);
}
