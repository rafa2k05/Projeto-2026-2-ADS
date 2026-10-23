<?php
declare(strict_types=1);

/*
 * funcoes.php: security "toolbox" used by ALL endpoints.
 * funcoes.php: "caixa de ferramentas" de segurança usada por TODOS os endpoints.
 *
 * Lives in helpers/ (blocked by .htaccess: nobody can open it directly in the browser).
 * Fica em helpers/ (bloqueada por .htaccess: ninguém acessa direto pelo navegador).
 */

// ===== Business rules / Regras do negócio (adjust with Larissa / ajuste com a Larissa) =====

// Fixed time slots offered to customers.
// Horários fixos oferecidos aos clientes.
const HORARIOS_FIXOS         = ['10:00', '12:00', '14:00', '16:00', '18:00'];

// Closed weekdays: 0 = Sunday ... 6 = Saturday (e.g. [0] closes on Sundays).
// Dias fechados: 0 = domingo ... 6 = sábado (ex.: [0] fecha aos domingos).
const DIAS_FECHADOS          = [];

// How far ahead a customer can book (in days).
// Com quantos dias de antecedência o cliente pode agendar.
const DIAS_MAX_ANTECEDENCIA  = 90;

// Maximum active bookings per customer.
// Máximo de agendamentos ativos por cliente.
const MAX_AGENDAMENTOS_ATIVOS = 3;

// Maximum request body size in bytes (10 KB): blocks huge requests.
// Tamanho máximo do corpo da requisição em bytes (10 KB): barra requisições gigantes.
const TAMANHO_MAX_BODY       = 10240;

// Seconds of inactivity before the session expires (2 hours).
// Segundos de inatividade até a sessão expirar (2 horas).
const SESSAO_INATIVIDADE     = 7200;

// Failed password attempts allowed per e-mail+IP in 15 minutes.
// Erros de senha permitidos por e-mail+IP em 15 minutos.
const LIMITE_FALHAS_CONTA    = 5;

// Failed password attempts allowed per IP in 15 minutes.
// Erros de senha permitidos por IP em 15 minutos.
const LIMITE_FALHAS_IP       = 20;

// All dates/times use the Brazilian timezone.
// Todas as datas/horas usam o fuso horário do Brasil.
date_default_timezone_set('America/Sao_Paulo');


// ===== Standard response / Resposta padrão (always JSON, always ends the script) =====

// Sends a JSON response with the given HTTP status and stops the script.
// Envia uma resposta JSON com o status HTTP informado e encerra o script.
// ("never" tells PHP this function never returns; needs PHP 8.1+)
// ("never" avisa ao PHP que esta função nunca retorna; exige PHP 8.1+)
function responder(int $codigo, array $dados): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


// ===== Setup for EVERY endpoint / Preparação de TODO endpoint =====

function iniciar_api(string $metodo): void
{
    // Errors NEVER show on screen (they leak paths and details); they go to the Apache log.
    // Erros NUNCA aparecem na tela (vazam caminhos e detalhes); vão para o log do Apache.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    // Any unexpected error becomes a generic response, without technical details.
    // Qualquer erro inesperado vira uma resposta genérica, sem detalhes técnicos.
    set_exception_handler(function (Throwable $e): void {
        error_log('Erro não tratado: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
        responder(500, ['erro' => 'Erro interno. Tente novamente.']);
    });

    // Security headers.
    // Cabeçalhos de segurança.

    // Hide the PHP version from attackers.
    // Esconde a versão do PHP de possíveis atacantes.
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
    // Do not cache responses (they may contain user-specific data).
    // Não guarda a resposta em cache (ela pode conter dados do usuário).
    header('Cache-Control: no-store');
    // The browser must not "guess" the content type.
    // O navegador não pode "adivinhar" o tipo do conteúdo.
    header('X-Content-Type-Options: nosniff');
    // Nobody can embed your API in an iframe.
    // Ninguém consegue colocar sua API dentro de um iframe.
    header('X-Frame-Options: DENY');
    // Do not send the previous page address to other sites.
    // Não envia o endereço da página anterior para outros sites.
    header('Referrer-Policy: no-referrer');
    // Strictest content policy: this API only returns data, nothing executable.
    // Política de conteúdo mais restrita: esta API só devolve dados, nada executável.
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

    // Only the expected method passes (GET or POST).
    // Só o método esperado passa (GET ou POST).
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $metodo) {
        header('Allow: ' . $metodo);
        responder(405, ['erro' => 'Método não permitido.']);
    }

    // Start the secure session.
    // Inicia a sessão segura.
    iniciar_sessao();
}


// ===== Secure session / Sessão segura =====

function iniciar_sessao(): void
{
    // Do nothing if a session is already running.
    // Não faz nada se a sessão já estiver ativa.
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Detect whether the site is being served over HTTPS.
    // Detecta se o site está sendo acessado por HTTPS.
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    // Reject session IDs that the server did not create.
    // Recusa IDs de sessão que o servidor não criou.
    ini_set('session.use_strict_mode', '1');
    // Session ID only travels in cookies, never in the URL.
    // O ID da sessão só viaja em cookies, nunca na URL.
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // Server-side lifetime of idle session data.
    // Tempo de vida, no servidor, dos dados de uma sessão parada.
    ini_set('session.gc_maxlifetime', (string) SESSAO_INATIVIDADE);

    // Custom cookie name (does not reveal the default "PHPSESSID").
    // Nome de cookie personalizado (não revela o padrão "PHPSESSID").
    session_name('LFB_SESSAO');
    session_set_cookie_params([
        // Cookie dies when the browser closes.
        // O cookie morre quando o navegador fecha.
        'lifetime' => 0,
        'path'     => '/',
        // Only sent over HTTPS when the site runs on HTTPS.
        // Só é enviado por HTTPS quando o site estiver em HTTPS.
        'secure'   => $https,
        // JavaScript cannot read the cookie (anti-XSS).
        // O JavaScript NÃO consegue ler o cookie (proteção contra XSS).
        'httponly' => true,
        // Not sent on requests from other sites (anti-CSRF).
        // Não vai em requisições vindas de outros sites (proteção contra CSRF).
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout: if the user was inactive for too long, wipe the session
    // and issue a new session ID.
    // Expiração por inatividade: se o usuário ficou parado tempo demais, apaga a sessão
    // e gera um novo ID de sessão.
    $agora = time();
    if (isset($_SESSION['ultima_atividade']) && ($agora - (int) $_SESSION['ultima_atividade']) > SESSAO_INATIVIDADE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    // Record the time of this activity.
    // Registra o horário desta atividade.
    $_SESSION['ultima_atividade'] = $agora;
}

// Fully ends the session: data, browser cookie and server-side session.
// Encerra a sessão por completo: dados, cookie no navegador e sessão no servidor.
function encerrar_sessao(): void
{
    // Wipe all session data.
    // Apaga todos os dados da sessão.
    $_SESSION = [];

    // Delete the cookie using the same parameters it was created with.
    // Apaga o cookie usando os mesmos parâmetros com que foi criado.
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

    // Destroy the session on the server.
    // Destrói a sessão no servidor.
    session_destroy();
}


// ===== Safe request body reading / Leitura segura do corpo da requisição =====

// Reads and validates the JSON body; stops with an error if anything is wrong.
// Lê e valida o corpo JSON; encerra com erro se algo estiver errado.
function ler_json(): array
{
    // Only accept requests that declare themselves as JSON.
    // Só aceita requisições que se declaram como JSON.
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($tipo, 'application/json') !== 0) {
        responder(415, ['erro' => 'Envie os dados como JSON.']);
    }

    // Read at most the size limit + 1 byte; if it passes the limit, it is too big.
    // Lê no máximo o limite + 1 byte; se passar do limite, é grande demais.
    $bruto = file_get_contents('php://input', false, null, 0, TAMANHO_MAX_BODY + 1);
    if ($bruto === false || strlen($bruto) > TAMANHO_MAX_BODY) {
        responder(413, ['erro' => 'Requisição muito grande.']);
    }

    // Decode with a depth limit of 4 and throw an exception on invalid JSON.
    // Decodifica com limite de profundidade 4 e lança exceção se o JSON for inválido.
    try {
        $dados = json_decode($bruto, true, 4, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        responder(400, ['erro' => 'JSON inválido.']);
    }

    // The JSON must be an object/array, not a lone number or string.
    // O JSON precisa ser um objeto/array, não um número ou texto solto.
    if (!is_array($dados)) {
        responder(400, ['erro' => 'JSON inválido.']);
    }
    return $dados;
}

// Gets a field as TEXT (arrays/numbers/objects become empty). Passwords: use $trim = false.
// Pega um campo como TEXTO (array/número/objeto vira vazio). Senhas: use $trim = false.
function campo(array $dados, string $chave, bool $trim = true): string
{
    $valor = $dados[$chave] ?? '';
    if (!is_string($valor)) {
        return '';
    }
    return $trim ? trim($valor) : $valor;
}


// ===== CSRF (stops another site from making the customer's browser act unknowingly) =====
// ===== CSRF (impede que outro site faça o navegador do cliente agir sem querer) =====

// Returns the session's CSRF token, creating a random one if it does not exist yet.
// Devolve o token CSRF da sessão, criando um aleatório se ainda não existir.
function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Blocks the request unless it comes from our own site AND carries the right CSRF token.
// Bloqueia a requisição, a menos que venha do nosso próprio site E traga o token CSRF correto.
function exigir_origem_e_csrf(): void
{
    // 1) If the browser sent Origin, it must be the SAME site.
    // 1) Se o navegador enviou o Origin, ele precisa ser o MESMO site.
    $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origem !== '') {
        $host  = (string) parse_url($origem, PHP_URL_HOST);
        $porta = parse_url($origem, PHP_URL_PORT);
        $origemHost = strtolower($host) . ($porta ? ':' . $porta : '');
        if ($origemHost !== strtolower($_SERVER['HTTP_HOST'] ?? '')) {
            responder(403, ['erro' => 'Origem não permitida.']);
        }
    }

    // 2) The token sent in the header must match the one stored in the session.
    // hash_equals compares in constant time (prevents timing attacks).
    // 2) O token enviado no cabeçalho precisa bater com o guardado na sessão.
    // hash_equals compara em tempo constante (evita ataques por tempo de resposta).
    $enviado  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $esperado = $_SESSION['csrf'] ?? '';
    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        responder(403, ['erro' => 'Sessão expirada. Recarregue a página.']);
    }
}


// ===== Validations / Validações =====

// Name: 3 to 100 characters, starting with a letter, only letters, spaces, apostrophes, dots and hyphens.
// Nome: de 3 a 100 caracteres, começando com letra, só letras, espaços, apóstrofos, pontos e hífens.
function nome_valido(string $nome): bool
{
    $tam = mb_strlen($nome, 'UTF-8');
    return $tam >= 3 && $tam <= 100
        && preg_match("/^[\p{L}\p{M}][\p{L}\p{M}' .-]*$/u", $nome) === 1;
}

// E-mail: up to 150 characters and a valid format.
// E-mail: até 150 caracteres e formato válido.
function email_valido(string $email): bool
{
    return strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// bcrypt only uses the first 72 bytes of the password, hence the 72 limit.
// bcrypt só usa os primeiros 72 bytes da senha, por isso o teto de 72.
function senha_valida(string $senha): bool
{
    $bytes = strlen($senha);
    return $bytes >= 8 && $bytes <= 72;
}

// Real "Y-m-d" date, from tomorrow on, up to DIAS_MAX_ANTECEDENCIA, on a day Larissa works.
// Returns a DateTimeImmutable, or null if the date is not bookable.
// Data "Y-m-d" real, a partir de amanhã, até DIAS_MAX_ANTECEDENCIA, em dia que a Larissa atende.
// Devolve um DateTimeImmutable, ou null se a data não puder ser agendada.
function data_agendavel(string $texto): ?DateTimeImmutable
{
    // Parse strictly; "!" resets the time to 00:00 so comparisons are by day.
    // Interpreta de forma estrita; "!" zera a hora para 00:00, assim a comparação é por dia.
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
    $erros = DateTimeImmutable::getLastErrors();
    if ($d === false || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))) {
        return null;
    }

    // Catches impossible dates such as 2026-02-31 (PHP would silently shift them).
    // Pega datas impossíveis como 2026-02-31 (o PHP as deslocaria em silêncio).
    if ($d->format('Y-m-d') !== $texto) {
        return null;
    }

    // Must be after today and within the maximum advance period.
    // Precisa ser depois de hoje e dentro do período máximo de antecedência.
    $hoje = new DateTimeImmutable('today');
    if ($d <= $hoje || $d > $hoje->modify('+' . DIAS_MAX_ANTECEDENCIA . ' days')) {
        return null;
    }

    // Must not fall on a closed weekday.
    // Não pode cair em um dia da semana fechado.
    if (in_array((int) $d->format('w'), DIAS_FECHADOS, true)) {
        return null;
    }
    return $d;
}


// ===== Brute-force protection / Proteção contra tentativa de adivinhar senha (força bruta) =====

// Client IP. REMOTE_ADDR is used on purpose: headers like X-Forwarded-For can be forged.
// IP do cliente. REMOTE_ADDR é usado de propósito: cabeçalhos como X-Forwarded-For podem ser forjados.
function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// Checks whether this e-mail/IP is temporarily blocked because of too many recent failures.
// Verifica se este e-mail/IP está bloqueado temporariamente por excesso de falhas recentes.
function login_bloqueado(PDO $pdo, string $email, string $ip): bool
{
    // Count failures from this IP in the last 15 minutes: total, and those for this e-mail.
    // Conta as falhas deste IP nos últimos 15 minutos: o total e as deste e-mail.
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS total, COALESCE(SUM(email = :email), 0) AS da_conta
           FROM login_tentativas
          WHERE ip = :ip AND criado_em > (NOW() - INTERVAL 15 MINUTE)'
    );
    $st->execute(['email' => $email, 'ip' => $ip]);
    $r = $st->fetch();

    // Blocked if either limit (per account or per IP) was reached.
    // Bloqueado se qualquer limite (por conta ou por IP) foi atingido.
    return (int) $r['da_conta'] >= LIMITE_FALHAS_CONTA || (int) $r['total'] >= LIMITE_FALHAS_IP;
}

// Records one failed login attempt.
// Registra uma tentativa de login que falhou.
function registrar_falha_login(PDO $pdo, string $email, string $ip): void
{
    $pdo->prepare('INSERT INTO login_tentativas (email, ip) VALUES (:email, :ip)')
        ->execute(['email' => $email, 'ip' => $ip]);

    // Cleanup: delete records older than 1 day.
    // Faxina: apaga registros com mais de 1 dia.
    $pdo->exec('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)');
}

// After a successful login, clears the failures of this e-mail/IP.
// Após um login correto, apaga as falhas deste e-mail/IP.
function limpar_falhas_login(PDO $pdo, string $email, string $ip): void
{
    $pdo->prepare('DELETE FROM login_tentativas WHERE email = :email AND ip = :ip')
        ->execute(['email' => $email, 'ip' => $ip]);
}


// ===== Logged-in user (the ID ALWAYS comes from the session, never from the client) =====
// ===== Usuário logado (o ID vem SEMPRE da sessão, nunca do que o cliente manda) =====

// Returns the logged-in user's data, or null if nobody is logged in.
// Devolve os dados do usuário logado, ou null se ninguém estiver logado.
function usuario_logado(PDO $pdo): ?array
{
    // The session must hold a real integer id.
    // A sessão precisa ter um id inteiro de verdade.
    $id = $_SESSION['usuario_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    // Load fresh data from the database (never trust stale session data).
    // Carrega dados atualizados do banco (nunca confia em dados velhos da sessão).
    $st = $pdo->prepare('SELECT id, nome, email FROM usuarios WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $u = $st->fetch();

    // User was deleted: drop the session.
    // O usuário foi apagado: derruba a sessão.
    if ($u === false) {
        encerrar_sessao();
        return null;
    }
    return $u;
}

// Same as above, but stops with 401 if nobody is logged in.
// Igual à anterior, mas encerra com 401 se ninguém estiver logado.
function exigir_login(PDO $pdo): array
{
    $u = usuario_logado($pdo);
    if ($u === null) {
        responder(401, ['erro' => 'Faça login para continuar.']);
    }
    return $u;
}

// Same as exigir_login(), but also requires the user to be an admin.
// Igual ao exigir_login(), mas exige também que o usuário seja admin.
function exigir_admin(PDO $pdo): array
{
    // First, the user must be logged in (stops with 401 otherwise).
    // Primeiro, o usuário precisa estar logado (encerra com 401 caso contrário).
    $u = exigir_login($pdo);

    // Read the role from the DATABASE, never from the session.
    // Lê o perfil (tipo) do BANCO, nunca da sessão.
    $st = $pdo->prepare('SELECT tipo FROM usuarios WHERE id = :id LIMIT 1');
    $st->execute(['id' => $u['id']]);

    // Anything other than 'admin' is denied (403 = authenticated but not allowed).
    // Qualquer coisa diferente de 'admin' é negada (403 = autenticado, mas sem permissão).
    if ($st->fetchColumn() !== 'admin') {
        responder(403, ['erro' => 'Acesso negado.']);
    }

    return $u;
}
?>