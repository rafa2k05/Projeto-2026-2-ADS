<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('POST');
exigir_origem_e_csrf();
require_once __DIR__ . '/../config/conexao.php';
$admin=exigir_admin($pdo);
$d=ler_json();

// ALTERAR agendamento (o "U" do CRUD da agenda).
// Muda: cliente, telefone, serviço, data e hora. O status NÃO muda aqui (isso é do atualizar_status.php).
$id=$d['agendamento_id']??null;
$nome=campo($d,'cliente_nome');
$telefone=telefone_normalizado(campo($d,'cliente_telefone'));
$servicoId=$d['servico_id']??null;
$dataTexto=campo($d,'data');
$hora=campo($d,'hora');

if ((!is_int($id)&&!(is_string($id)&&ctype_digit($id)))||(int)$id<1) responder(400,['erro'=>'Agendamento inválido.']);
$id=(int)$id;
if (!nome_valido($nome)) responder(400,['erro'=>'Informe um nome válido (de 3 a 100 caracteres).']);
if (!telefone_valido($telefone)) responder(400,['erro'=>'Informe um telefone válido com DDD.']);
if ((!is_int($servicoId)&&!(is_string($servicoId)&&ctype_digit($servicoId)))||(int)$servicoId<1) responder(400,['erro'=>'Selecione um serviço válido.']);
$servicoId=(int)$servicoId;
if (!hora_valida($hora)) responder(400,['erro'=>'Selecione um horário permitido.']);

try {
    $st=$pdo->prepare('SELECT data,hora,servico_id,status,duracao_aplicada,preco_aplicado FROM agendamentos WHERE id=:id');
    $st->execute(['id'=>$id]);
    $atual=$st->fetch();
    if (!$atual) responder(404,['erro'=>'Agendamento não encontrado.']);
    if ($atual['status']==='cancelado') responder(409,['erro'=>'Agendamento cancelado não pode ser alterado.']);

    // Se a data não mudou, aceita como está (permite corrigir nome/telefone de um atendimento de dias atrás).
    // Se mudou, a nova data precisa ser de hoje em diante.
    if ($dataTexto===$atual['data']) {
        $dataSql=$atual['data'];
    } else {
        $data=data_iso_valida($dataTexto,true);
        if (!$data) responder(400,['erro'=>'Informe uma data válida de hoje em diante.']);
        $dataSql=$data->format('Y-m-d');
    }

    // Mesmo serviço: mantém preço e duração que foram aplicados na época (histórico).
    // Serviço diferente: pega preço e duração atuais dele (precisa estar ativo).
    if ($servicoId===(int)$atual['servico_id']) {
        $duracao=(int)$atual['duracao_aplicada'];
        $preco=$atual['preco_aplicado'];
    } else {
        $st=$pdo->prepare('SELECT duracao_minutos,preco FROM servicos WHERE id=:id AND ativo=1 LIMIT 1');
        $st->execute(['id'=>$servicoId]);
        $servico=$st->fetch();
        if (!$servico) responder(400,['erro'=>'Serviço não encontrado ou inativo.']);
        $duracao=(int)$servico['duracao_minutos'];
        $preco=$servico['preco'];
    }

    $inicio=$hora.':00';
    $fim=horario_fim($hora,$duracao);

    $pdo->beginTransaction();
    // Trava o dia antigo e o novo (sempre em ordem, para duas pessoas não se travarem uma à outra).
    $dias=array_unique([$atual['data'],$dataSql]);
    sort($dias);
    foreach ($dias as $dia) bloquear_linha_dia($pdo,$dia);

    // Relê já com o dia travado: se outra pessoa mexeu nesse meio tempo, não sobrescreve.
    $st=$pdo->prepare('SELECT data,status FROM agendamentos WHERE id=:id FOR UPDATE');
    $st->execute(['id'=>$id]);
    $conferir=$st->fetch();
    if (!$conferir||$conferir['data']!==$atual['data']||$conferir['status']==='cancelado') {
        $pdo->rollBack();
        responder(409,['erro'=>'Este agendamento foi alterado por outra pessoa. Recarregue a página.']);
    }

    // Só confere bloqueio e conflito se a data, o horário ou a duração mudaram.
    // Assim dá para corrigir só o nome/telefone mesmo que o horário tenha sido bloqueado depois.
    $mudouAgenda=$dataSql!==$atual['data']||$inicio!==$atual['hora']||$duracao!==(int)$atual['duracao_aplicada'];
    if ($mudouAgenda) {
        $st=$pdo->prepare('SELECT 1 FROM bloqueios_dias WHERE data=:data LIMIT 1');
        $st->execute(['data'=>$dataSql]);
        if ($st->fetchColumn()) { $pdo->rollBack(); responder(409,['erro'=>'Este dia está bloqueado.']); }

        $st=$pdo->prepare('SELECT 1 FROM bloqueios_horarios WHERE data=:data AND hora=:hora LIMIT 1');
        $st->execute(['data'=>$dataSql,'hora'=>$inicio]);
        if ($st->fetchColumn()) { $pdo->rollBack(); responder(409,['erro'=>'Este horário está bloqueado.']); }

        // "id <> :id": o próprio agendamento não conta como conflito com ele mesmo.
        $st=$pdo->prepare("SELECT id FROM agendamentos
            WHERE id<>:id AND data=:data AND status IN ('pendente','confirmado')
              AND hora < :fim
              AND ADDTIME(hora,SEC_TO_TIME(duracao_aplicada*60)) > :inicio
            LIMIT 1 FOR UPDATE");
        $st->execute(['id'=>$id,'data'=>$dataSql,'fim'=>$fim,'inicio'=>$inicio]);
        if ($st->fetch()) { $pdo->rollBack(); responder(409,['erro'=>'Já existe um agendamento que conflita com esse horário.']); }
    }

    $st=$pdo->prepare("UPDATE agendamentos
        SET servico_id=:servico,cliente_nome=:nome,cliente_telefone=:telefone,
            data=:data,hora=:hora,duracao_aplicada=:duracao,preco_aplicado=:preco
        WHERE id=:id");
    $st->execute([
        'servico'=>$servicoId,'nome'=>$nome,'telefone'=>$telefone,
        'data'=>$dataSql,'hora'=>$inicio,'duracao'=>$duracao,'preco'=>$preco,'id'=>$id
    ]);
    $pdo->commit();
    responder(200,['sucesso'=>true,'mensagem'=>'Agendamento alterado.']);
} catch(PDOException $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    error_log('Agendamento editar: '.$e->getMessage());
    responder(500,['erro'=>'Não foi possível alterar o agendamento.']);
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    error_log('Agendamento editar: '.$e->getMessage());
    responder(500,['erro'=>'Erro interno ao alterar o agendamento.']);
}
