<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('POST');
exigir_origem_e_csrf();
require_once __DIR__ . '/../config/conexao.php';

$dados = ler_json();
$servicoId = $dados['servico_id'] ?? null;
$dataTexto = campo($dados, 'data');
$hora = campo($dados, 'hora');
$nome = campo($dados, 'nome');
$telefone = telefone_normalizado(campo($dados, 'telefone'));
$consentimento = ($dados['consentimento_whatsapp'] ?? false) === true;

if ((!is_int($servicoId) && !(is_string($servicoId) && ctype_digit($servicoId))) || (int)$servicoId < 1) {
    responder(400, ['erro' => 'Selecione um serviço válido.']);
}
$servicoId = (int)$servicoId;
if (!nome_valido($nome)) responder(400, ['erro' => 'Informe um nome válido (de 3 a 100 caracteres).']);
if (!telefone_valido($telefone)) responder(400, ['erro' => 'Informe um WhatsApp válido com DDD.']);
if (!$consentimento) responder(400, ['erro' => 'É necessário autorizar o contato pelo WhatsApp para este agendamento.']);
$data = data_agendavel($dataTexto);
if ($data === null) responder(400, ['erro' => 'Data inválida ou fora do período de agendamento.']);
if (!hora_valida($hora)) responder(400, ['erro' => 'Horário inválido.']);
$dataSql = $data->format('Y-m-d');

try {
    $st = $pdo->prepare('SELECT id,nome,duracao_minutos,preco FROM servicos WHERE id=:id AND ativo=1 LIMIT 1');
    $st->execute(['id'=>$servicoId]);
    $servico = $st->fetch();
    if (!$servico) responder(400, ['erro'=>'Serviço indisponível. Atualize a página e escolha outro serviço.']);

    $duracao = (int)$servico['duracao_minutos'];
    $inicio = $hora . ':00';
    $fim = horario_fim($hora, $duracao);
    $usuario = usuario_logado($pdo);

    $pdo->beginTransaction();
    bloquear_linha_dia($pdo, $dataSql);

    $st = $pdo->prepare('SELECT 1 FROM bloqueios_dias WHERE data=:data LIMIT 1');
    $st->execute(['data'=>$dataSql]);
    if ($st->fetchColumn()) {
        $pdo->rollBack();
        responder(409, ['erro'=>'Esta data está indisponível. Escolha outro dia.']);
    }

    $st = $pdo->prepare('SELECT 1 FROM bloqueios_horarios WHERE data=:data AND hora=:hora LIMIT 1');
    $st->execute(['data'=>$dataSql,'hora'=>$inicio]);
    if ($st->fetchColumn()) {
        $pdo->rollBack();
        responder(409, ['erro'=>'Este horário está bloqueado. Escolha outro.']);
    }

    // Verifica sobreposição de intervalos, usando a duração salva no agendamento.
    $st = $pdo->prepare("SELECT id FROM agendamentos
        WHERE data=:data AND status IN ('pendente','confirmado')
          AND hora < :fim
          AND ADDTIME(hora, SEC_TO_TIME(duracao_aplicada * 60)) > :inicio
        LIMIT 1 FOR UPDATE");
    $st->execute(['data'=>$dataSql,'fim'=>$fim,'inicio'=>$inicio]);
    if ($st->fetch()) {
        $pdo->rollBack();
        responder(409, ['erro'=>'Esse horário acabou de ser reservado. Escolha outro.']);
    }

    // Limite de três reservas ativas futuras por telefone.
    $st = $pdo->prepare("SELECT COUNT(*) FROM agendamentos
        WHERE cliente_telefone=:telefone AND status IN ('pendente','confirmado') AND data>=CURDATE()");
    $st->execute(['telefone'=>$telefone]);
    if ((int)$st->fetchColumn() >= MAX_AGENDAMENTOS_ATIVOS) {
        $pdo->rollBack();
        responder(409, ['erro'=>'Este WhatsApp já possui o limite de agendamentos ativos.']);
    }

    $st = $pdo->prepare("INSERT INTO agendamentos
        (usuario_id,servico_id,cliente_nome,cliente_telefone,consentimento_whatsapp,data,hora,duracao_aplicada,preco_aplicado,status)
        VALUES (:usuario,:servico,:nome,:telefone,1,:data,:hora,:duracao,:preco,'pendente')");
    $st->execute([
        'usuario'=>$usuario['id'] ?? null,
        'servico'=>$servicoId,
        'nome'=>$nome,
        'telefone'=>$telefone,
        'data'=>$dataSql,
        'hora'=>$inicio,
        'duracao'=>$duracao,
        'preco'=>$servico['preco'],
    ]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();

    responder(201, ['sucesso'=>true,'mensagem'=>'Agendamento registrado como pendente.','agendamento'=>[
        'id'=>$id,'cliente'=>$nome,'telefone'=>$telefone,'servico'=>$servico['nome'],
        'preco'=>(float)$servico['preco'],'duracao_minutos'=>$duracao,
        'data'=>$dataSql,'hora'=>substr($inicio,0,5),'status'=>'pendente'
    ]]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ((int)($e->errorInfo[1] ?? 0) === 1062) responder(409,['erro'=>'Esse horário acabou de ser reservado. Escolha outro.']);
    error_log('Erro ao agendar: '.$e->getMessage());
    responder(500,['erro'=>'Não foi possível registrar o agendamento. Tente novamente.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro ao agendar: '.$e->getMessage());
    responder(500,['erro'=>'Erro interno ao registrar o agendamento.']);
}
?>