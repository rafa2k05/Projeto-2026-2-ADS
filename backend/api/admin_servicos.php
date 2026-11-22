<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET','POST','PUT','PATCH','DELETE'], true)) {
    header('Allow: GET, POST, PUT, PATCH, DELETE');
    iniciar_api('GET'); // responderá 405
}
iniciar_api($metodo);
require_once __DIR__ . '/../config/conexao.php';

// Valida nome, preço e duração (usado ao CADASTRAR e ao ALTERAR um serviço).
// Devolve os dados limpos, ou null se algo estiver inválido.
function dados_servico_validos(array $d): ?array
{
    $nome    = campo($d, 'nome');
    $preco   = $d['preco'] ?? null;
    $duracao = $d['duracao_minutos'] ?? null;
    if ($nome === '' || mb_strlen($nome, 'UTF-8') > 100 ||
        !is_numeric($preco) || (float)$preco < 0 || (float)$preco > 99999999.99 ||
        (!is_int($duracao) && !(is_string($duracao) && ctype_digit($duracao))) ||
        (int)$duracao < 1 || (int)$duracao > 1440) {
        return null;
    }
    return ['nome' => $nome, 'preco' => round((float)$preco, 2), 'duracao' => (int)$duracao];
}
$admin = exigir_admin($pdo);

if ($metodo === 'GET') {
    $rows = $pdo->query('SELECT id,nome,duracao_minutos,preco,ativo FROM servicos ORDER BY nome')->fetchAll();
    responder(200, ['servicos' => array_map(static fn($s) => [
        'id'=>(int)$s['id'], 'nome'=>$s['nome'],
        'duracao_minutos'=>(int)$s['duracao_minutos'],
        'preco'=>(float)$s['preco'], 'ativo'=>(int)$s['ativo']
    ], $rows)]);
}

exigir_origem_e_csrf();
$d = ler_json();

if ($metodo === 'POST') {
    $v = dados_servico_validos($d);
    if ($v === null) {
        responder(400,['erro'=>'Informe nome, preço e duração válidos.']);
    }
    try {
        $st=$pdo->prepare('INSERT INTO servicos (nome,duracao_minutos,preco,ativo) VALUES (:nome,:duracao,:preco,1)');
        $st->execute(['nome'=>$v['nome'],'duracao'=>$v['duracao'],'preco'=>$v['preco']]);
        responder(201,['sucesso'=>true,'mensagem'=>'Serviço cadastrado.','id'=>(int)$pdo->lastInsertId()]);
    } catch(PDOException $e) {
        if ((int)($e->errorInfo[1]??0)===1062) responder(409,['erro'=>'Já existe um serviço com esse nome.']);
        error_log('Admin serviços POST: '.$e->getMessage());
        responder(500,['erro'=>'Não foi possível cadastrar o serviço.']);
    }
}

$id = $d['id'] ?? null;
if ((!is_int($id) && !(is_string($id) && ctype_digit($id))) || (int)$id < 1) {
    responder(400,['erro'=>'Serviço inválido.']);
}
$id=(int)$id;

try {
    if ($metodo === 'PUT') {
        // ALTERAR serviço: nome, preço e duração.
        // Agendamentos já feitos NÃO mudam: eles guardam preco_aplicado e duracao_aplicada.
        $v = dados_servico_validos($d);
        if ($v === null) responder(400,['erro'=>'Informe nome, preço e duração válidos.']);
        $st=$pdo->prepare('SELECT 1 FROM servicos WHERE id=:id');
        $st->execute(['id'=>$id]);
        if (!$st->fetchColumn()) responder(404,['erro'=>'Serviço não encontrado.']);
        $st=$pdo->prepare('UPDATE servicos SET nome=:nome, duracao_minutos=:duracao, preco=:preco WHERE id=:id');
        $st->execute(['nome'=>$v['nome'],'duracao'=>$v['duracao'],'preco'=>$v['preco'],'id'=>$id]);
        responder(200,['sucesso'=>true,'mensagem'=>'Serviço alterado.']);
    }
    if ($metodo === 'PATCH') {
        $ativo=$d['ativo']??null;
        if (!in_array($ativo,[0,1,'0','1',false,true],true)) responder(400,['erro'=>'Estado do serviço inválido.']);
        $st=$pdo->prepare('UPDATE servicos SET ativo=:ativo WHERE id=:id');
        $st->execute(['ativo'=>(int)$ativo,'id'=>$id]);
        responder(200,['sucesso'=>true,'mensagem'=>(int)$ativo===1?'Serviço ativado.':'Serviço desativado.']);
    }
    if ($metodo === 'DELETE') {
        // Não apaga o serviço: mantém o histórico dos agendamentos.
        $st=$pdo->prepare('UPDATE servicos SET ativo=0 WHERE id=:id');
        $st->execute(['id'=>$id]);
        responder(200,['sucesso'=>true,'mensagem'=>'Serviço desativado.']);
    }
} catch(PDOException $e) {
    if ((int)($e->errorInfo[1]??0)===1062) responder(409,['erro'=>'Já existe um serviço com esse nome.']);
    error_log('Admin serviços update: '.$e->getMessage());
    responder(500,['erro'=>'Não foi possível atualizar o serviço.']);
}
