<?php
declare(strict_types=1);

/*
 * funcoes.php — "caixa de ferramentas" de segurança usada por TODOS os endpoints.
 * Fica em helpers/ (bloqueada por .htaccess: ninguém acessa direto pelo navegador).
 */

// ===== Regras do negócio (ajuste com a Larissa) =====
const HORARIOS_FIXOS         = ['10:00', '12:00', '14:00', '16:00', '18:00'];
const DIAS_FECHADOS          = [];     // 0 = domingo ... 6 = sábado (ex.: [0] fecha aos domingos)
const DIAS_MAX_ANTECEDENCIA  = 90;     // não aceita agendar além de 90 dias
const MAX_AGENDAMENTOS_ATIVOS = 3;     // por cliente
const TAMANHO_MAX_BODY       = 10240;  // 10 KB — barra requisições gigantes
const SESSAO_INATIVIDADE     = 7200;   // 2 horas sem usar = sessão expira
const LIMITE_FALHAS_CONTA    = 5;      // erros de senha por e-mail+IP em 15 min
const LIMITE_FALHAS_IP       = 20;     // erros de senha por IP em 15 min

date_default_timezone_set('America/Sao_Paulo');


// ===== Resposta padrão (sempre JSON, sempre encerra o script) =====
function responder(int $codigo, array $dados): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


// ===== Preparação de TODO endpoint =====
function iniciar_api(string $metodo): void
{
    // Erros NUNCA aparecem na tela (vazam caminhos e detalhes); vão pro log do Apache.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    // Qualquer erro inesperado vira uma resposta genérica, sem detalhes técnicos.
    set_exception_handler(function (Throwable $e): void {
        error_log('Erro não tratado: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
        responder(500, ['erro' => 'Erro interno. Tente novamente.']);
    });

    // Cabeçalhos de segurança
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');                       // não guarda resposta em cache
    header('X-Content-Type-Options: nosniff');               // navegador não "adivinha" o tipo
    header('X-Frame-Options: DENY');                         // ninguém põe sua API em iframe
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

    // Só o método esperado passa (GET ou POST)
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $metodo) {
        header('Allow: ' . $metodo);
        responder(405, ['erro' => 'Método não permitido.']);
    }

    iniciar_sessao();
}


// ===== Sessão segura =====
function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');     // não aceita ID de sessão inventado
    ini_set('session.use_only_cookies', '1');    // ID nunca vai na URL
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) SESSAO_INATIVIDADE);

    session_name('LFB_SESSAO');
    session_set_cookie_params([
        'lifetime' => 0,          // morre quando fecha o navegador
        'path'     => '/',
        'secure'   => $https,     // só por HTTPS quando o site estiver em HTTPS
        'httponly' => true,       // JavaScript NÃO consegue ler o cookie (anti-XSS)
        'samesite' => 'Lax',      // não vai em requisições de outros sites (anti-CSRF)
    ]);
    session_start();

    $agora = time();
    if (isset($_SESSION['ultima_atividade']) && ($agora - (int) $_SESSION['ultima_atividade']) > SESSAO_INATIVIDADE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['ultima_atividade'] = $agora;
}

function encerrar_sessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}


// ===== Leitura segura do corpo da requisição =====
function ler_json(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($tipo, 'application/json') !== 0) {
        responder(415, ['erro' => 'Envie os dados como JSON.']);
    }

    $bruto = file_get_contents('php://input', false, null, 0, TAMANHO_MAX_BODY + 1);
    if ($bruto === false || strlen($bruto) > TAMANHO_MAX_BODY) {
        responder(413, ['erro' => 'Requisição muito grande.']);
    }

    try {
        $dados = json_decode($bruto, true, 4, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        responder(400, ['erro' => 'JSON inválido.']);
    }

    if (!is_array($dados)) {
        responder(400, ['erro' => 'JSON inválido.']);
    }
    return $dados;
}

// Pega um campo como TEXTO (se vier array/número/objeto, vira vazio). Senha: use $trim = false.
function campo(array $dados, string $chave, bool $trim = true): string
{
    $valor = $dados[$chave] ?? '';
    if (!is_string($valor)) {
        return '';
    }
    return $trim ? trim($valor) : $valor;
}


// ===== CSRF (impede que outro site faça o navegador do cliente agir sem querer) =====
function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function exigir_origem_e_csrf(): void
{
    // 1) Se o navegador mandou Origin, tem que ser o MESMO site.
    $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origem !== '') {
        $host  = (string) parse_url($origem, PHP_URL_HOST);
        $porta = parse_url($origem, PHP_URL_PORT);
        $origemHost = strtolower($host) . ($porta ? ':' . $porta : '');
        if ($origemHost !== strtolower($_SERVER['HTTP_HOST'] ?? '')) {
            responder(403, ['erro' => 'Origem não permitida.']);
        }
    }

    // 2) O token tem que bater com o da sessão.
    $enviado  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $esperado = $_SESSION['csrf'] ?? '';
    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        responder(403, ['erro' => 'Sessão expirada. Recarregue a página.']);
    }
}


// ===== Validações =====
function nome_valido(string $nome): bool
{
    $tam = mb_strlen($nome, 'UTF-8');
    return $tam >= 3 && $tam <= 100
        && preg_match("/^[\p{L}\p{M}][\p{L}\p{M}' .-]*$/u", $nome) === 1;
}

function email_valido(string $email): bool
{
    return strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// bcrypt só usa os primeiros 72 bytes da senha, por isso o teto de 72.
function senha_valida(string $senha): bool
{
    $bytes = strlen($senha);
    return $bytes >= 8 && $bytes <= 72;
}

// Data "Y-m-d" real, a partir de amanhã, até DIAS_MAX_ANTECEDENCIA, em dia que a Larissa atende.
function data_agendavel(string $texto): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
    $erros = DateTimeImmutable::getLastErrors();
    if ($d === false || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))) {
        return null;
    }
    if ($d->format('Y-m-d') !== $texto) {     // pega 2026-02-31 e similares
        return null;
    }

    $hoje = new DateTimeImmutable('today');
    if ($d <= $hoje || $d > $hoje->modify('+' . DIAS_MAX_ANTECEDENCIA . ' days')) {
        return null;
    }
    if (in_array((int) $d->format('w'), DIAS_FECHADOS, true)) {
        return null;
    }
    return $d;
}


// ===== Proteção contra tentativa de adivinhar senha (força bruta) =====
function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_bloqueado(PDO $pdo, string $email, string $ip): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS total, COALESCE(SUM(email = :email), 0) AS da_conta
           FROM login_tentativas
          WHERE ip = :ip AND criado_em > (NOW() - INTERVAL 15 MINUTE)'
    );
    $st->execute(['email' => $email, 'ip' => $ip]);
    $r = $st->fetch();
    return (int) $r['da_conta'] >= LIMITE_FALHAS_CONTA || (int) $r['total'] >= LIMITE_FALHAS_IP;
}

function registrar_falha_login(PDO $pdo, string $email, string $ip): void
{
    $pdo->prepare('INSERT INTO login_tentativas (email, ip) VALUES (:email, :ip)')
        ->execute(['email' => $email, 'ip' => $ip]);
    // faxina: apaga registros com mais de 1 dia
    $pdo->exec('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)');
}

function limpar_falhas_login(PDO $pdo, string $email, string $ip): void
{
    $pdo->prepare('DELETE FROM login_tentativas WHERE email = :email AND ip = :ip')
        ->execute(['email' => $email, 'ip' => $ip]);
}


// ===== Usuário logado (o ID vem SEMPRE da sessão, nunca do que o cliente manda) =====
function usuario_logado(PDO $pdo): ?array
{
    $id = $_SESSION['usuario_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    $st = $pdo->prepare('SELECT id, nome, email FROM usuarios WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $u = $st->fetch();
    if ($u === false) {          // usuário foi apagado: derruba a sessão
        encerrar_sessao();
        return null;
    }
    return $u;
}

function exigir_login(PDO $pdo): array
{
    $u = usuario_logado($pdo);
    if ($u === null) {
        responder(401, ['erro' => 'Faça login para continuar.']);
    }
    return $u;
}
