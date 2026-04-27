<?php
/**
 * ============================================================
 *  FreePBX - Painel de Controle
 *  Arquivo: painel_acao.php
 *  Função:  Executa ações via AMI (hangup, transfer, spy, etc.)
 * ============================================================
 *  Compatível com PJSIP e chan_sip
 * ============================================================
 */

require_once __DIR__ . '/painel_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── Token de segurança ────────────────────────────────────────
if (API_TOKEN !== '') {
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    if ($token !== API_TOKEN) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token inválido']);
        exit;
    }
}

$acao    = $_POST['acao']    ?? '';
$ramal   = $_POST['ramal']   ?? '';   // ramal alvo (número)
$destino = $_POST['destino'] ?? '';   // ramal destino (para transfer/originate)
$canal   = $_POST['canal']   ?? '';   // canal específico (ex: PJSIP/1001-000001)
$meu_ramal = $_POST['meu_ramal'] ?? ''; // ramal do operador (para spy, barge, originate)
$fila    = $_POST['fila']    ?? '';   // nome da fila (para queue_pause)
$motivo  = $_POST['motivo']  ?? '';   // motivo da pausa

// ── Detecta driver SIP ─────────────────────────────────────────
// Determina prefixo do canal com base no driver configurado
function getSipPrefix(string $ext): string {
    $driver = strtolower(SIP_DRIVER);
    if ($driver === 'chansip') return "SIP/{$ext}";
    if ($driver === 'pjsip')   return "PJSIP/{$ext}";
    // auto: tenta determinar pelo canal ativo ou usa PJSIP como padrão
    return "PJSIP/{$ext}";
}

// ── Conecta AMI ───────────────────────────────────────────────
$sock = @fsockopen(AMI_HOST, AMI_PORT, $errno, $errstr, AMI_TIMEOUT);
if (!$sock) {
    echo json_encode(['ok' => false, 'error' => "AMI indisponível: {$errstr}"]);
    exit;
}
stream_set_timeout($sock, AMI_TIMEOUT);
fgets($sock, 4096); // banner

// Login
fwrite($sock, "Action: Login\r\nUsername: " . AMI_USER . "\r\nSecret: " . AMI_PASS . "\r\nEvents: off\r\n\r\n");
$resp = '';
$t = time() + 5;
while (!feof($sock) && time() < $t) {
    $line = fgets($sock, 4096);
    if ($line === false || rtrim($line) === '') break;
    $resp .= $line;
}
if (strpos($resp, 'Response: Success') === false) {
    fclose($sock);
    echo json_encode(['ok' => false, 'error' => 'Falha no login AMI']);
    exit;
}

// ── Funções auxiliares ────────────────────────────────────────
function ami_send($sock, string $data): void {
    fwrite($sock, $data);
}

function ami_read($sock, int $timeout = 4): string {
    $buf = '';
    $t   = time() + $timeout;
    while (!feof($sock) && time() < $t) {
        $line = fgets($sock, 4096);
        if ($line === false || rtrim($line) === '') break;
        $buf .= $line;
    }
    return $buf;
}

// Busca canal ativo de um ramal via CoreShowChannels
function findActiveChannel($sock, string $ext): string {
    fwrite($sock, "Action: CoreShowChannels\r\nActionID: find_{$ext}\r\n\r\n");
    $buf = '';
    $t   = time() + AMI_TIMEOUT;
    while (!feof($sock) && time() < $t) {
        $line = fgets($sock, 4096);
        if ($line === false) break;
        $buf .= $line;
        if (strpos($line, 'CoreShowChannelsComplete') !== false) break;
    }
    // Procura canal do ramal
    foreach (preg_split('/\r?\n\r?\n/', $buf) as $block) {
        if (strpos($block, "Channel:") === false) continue;
        if (preg_match('/Channel:\s*((?:PJSIP|SIP|IAX2)\/' . preg_quote($ext, '/') . '-\S+)/i', $block, $m)) {
            return trim($m[1]);
        }
    }
    return '';
}

// ── Executar ação ─────────────────────────────────────────────
$ok  = false;
$msg = 'Ação desconhecida';

// Se não temos canal explícito, busca automaticamente
if ($canal === '' && $ramal !== '' && $acao !== 'originate' && $acao !== 'queue_pause') {
    $canal = findActiveChannel($sock, $ramal);
}

switch ($acao) {

    // ── Desligar chamada ─────────────────────────────────────
    case 'hangup':
        if ($canal === '') {
            $msg = 'Canal não encontrado para o ramal ' . $ramal;
            break;
        }
        ami_send($sock, "Action: Hangup\r\nChannel: {$canal}\r\nCause: 16\r\n\r\n");
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $msg = $ok ? "Chamada encerrada no ramal {$ramal}" : "Erro ao desligar: {$r}";
        break;

    // ── Transferir chamada ────────────────────────────────────
    case 'transfer':
        if ($canal === '') { $msg = 'Canal não encontrado'; break; }
        if ($destino === '') { $msg = 'Destino não informado'; break; }
        $prefix = getSipPrefix($destino);
        ami_send($sock, "Action: Redirect\r\nChannel: {$canal}\r\nExten: {$destino}\r\nContext: from-internal\r\nPriority: 1\r\n\r\n");
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $msg = $ok ? "Transferido para {$destino}" : "Erro na transferência: {$r}";
        break;

    // ── Click-to-call (originar chamada) ─────────────────────
    case 'originate':
        // Faz o ramal do operador ligar para o ramal destino
        if ($meu_ramal === '') { $msg = 'Informe seu ramal em "Meu Ramal"'; break; }
        if ($destino === '')   { $msg = 'Destino não informado'; break; }
        $prefix = getSipPrefix($meu_ramal);
        ami_send($sock,
            "Action: Originate\r\n" .
            "Channel: {$prefix}\r\n" .
            "Application: Dial\r\n" .
            "Data: " . getSipPrefix($destino) . ",30,tT\r\n" .
            "CallerID: Painel <{$meu_ramal}>\r\n" .
            "Timeout: 30000\r\n" .
            "Async: true\r\n\r\n"
        );
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $msg = $ok ? "Chamada originada: {$meu_ramal} → {$destino}" : "Erro: {$r}";
        break;

    // ── Espionar silenciosamente ──────────────────────────────
    case 'spy':
        if ($canal === '') { $msg = 'Canal não encontrado'; break; }
        if ($meu_ramal === '') { $msg = 'Informe seu ramal em "Meu Ramal"'; break; }
        $prefix = getSipPrefix($meu_ramal);
        ami_send($sock,
            "Action: Originate\r\n" .
            "Channel: {$prefix}\r\n" .
            "Application: ChanSpy\r\n" .
            "Data: {$canal},q\r\n" .   // q = silencioso
            "CallerID: Spy <{$meu_ramal}>\r\n" .
            "Async: true\r\n\r\n"
        );
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $msg = $ok ? "Espionando ramal {$ramal}" : "Erro spy: {$r}";
        break;

    // ── Sussurrar para o agente ───────────────────────────────
    case 'whisper':
        if ($canal === '') { $msg = 'Canal não encontrado'; break; }
        if ($meu_ramal === '') { $msg = 'Informe seu ramal'; break; }
        $prefix = getSipPrefix($meu_ramal);
        ami_send($sock,
            "Action: Originate\r\n" .
            "Channel: {$prefix}\r\n" .
            "Application: ChanSpy\r\n" .
            "Data: {$canal},qw\r\n" .  // qw = whisper
            "CallerID: Whisper <{$meu_ramal}>\r\n" .
            "Async: true\r\n\r\n"
        );
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $msg = $ok ? "Sussurrando para ramal {$ramal}" : "Erro whisper: {$r}";
        break;

    // ── Entrar na chamada (barge) ─────────────────────────────
    case 'barge':
        if ($canal === '') { $msg = 'Canal não encontrado'; break; }
        if ($meu_ramal === '') { $msg = 'Informe seu ramal'; break; }
        $prefix = getSipPrefix($meu_ramal);
        ami_send($sock,
            "Action: Originate\r\n" .
            "Channel: {$prefix}\r\n" .
            "Application: ChanSpy\r\n" .
            "Data: {$canal},qBq\r\n" . // Bq = barge (todos ouvem)
            "CallerID: Barge <{$meu_ramal}>\r\n" .
            "Async: true\r\n\r\n"
        );
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $msg = $ok ? "Entrando na chamada do ramal {$ramal}" : "Erro barge: {$r}";
        break;

    // ── Estacionar chamada ────────────────────────────────────
    case 'park':
        if ($canal === '') { $msg = 'Canal não encontrado'; break; }
        ami_send($sock,
            "Action: Park\r\n" .
            "Channel: {$canal}\r\n" .
            "TimeoutChannel: {$canal}\r\n" .
            "Timeout: 60\r\n\r\n"
        );
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $msg = $ok ? "Chamada estacionada do ramal {$ramal}" : "Erro park: {$r}";
        break;

    // ── Pausar / retomar agente na fila ──────────────────────
    case 'queue_pause':
        if ($ramal === '') { $msg = 'Ramal não informado'; break; }
        $paused   = ($_POST['paused'] ?? '1') === '1' ? '1' : '0';
        $interface = getSipPrefix($ramal);
        $cmd = "Action: QueuePause\r\nInterface: {$interface}\r\nPaused: {$paused}\r\n";
        if ($fila !== '')  $cmd .= "Queue: {$fila}\r\n";
        if ($motivo !== '') $cmd .= "Reason: {$motivo}\r\n";
        $cmd .= "\r\n";
        ami_send($sock, $cmd);
        $r = ami_read($sock);
        $ok  = strpos($r, 'Response: Success') !== false;
        $acao_str = $paused === '1' ? 'pausado' : 'retomado';
        $msg = $ok ? "Agente {$ramal} {$acao_str}" : "Erro queue_pause: {$r}";
        break;
}

// Logoff AMI
fwrite($sock, "Action: Logoff\r\n\r\n");
fclose($sock);

echo json_encode(['ok' => $ok, 'msg' => $msg, 'acao' => $acao], JSON_UNESCAPED_UNICODE);
