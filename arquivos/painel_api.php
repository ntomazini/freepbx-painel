<?php
/**
 * ============================================================
 *  FreePBX - Painel de Controle em Tempo Real
 *  Arquivo: painel_api.php
 *  Função:  Consulta AMI e retorna JSON com status do sistema
 * ============================================================
 *  Suporte: PJSIP (padrão FreePBX) + chan_sip (legado)
 *  Nomes:   Lidos do banco FreePBX (tabela users / sip / pjsip)
 * ============================================================
 */

require_once __DIR__ . '/painel_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ── Token de segurança (opcional) ────────────────────────────
if (API_TOKEN !== '') {
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_PAINEL_TOKEN'] ?? '';
    if ($token !== API_TOKEN) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token inválido']);
        exit;
    }
}

// ════════════════════════════════════════════════════════════
//  LEITURA DE NOMES DOS RAMAIS
// ════════════════════════════════════════════════════════════
function getFreePBXDbCredentials(): array {
    // Credenciais manuais têm prioridade (definidas em painel_config.php)
    if (DB_HOST !== '' && DB_USER !== '' && DB_PASS !== '') {
        return ['host' => DB_HOST, 'user' => DB_USER, 'pass' => DB_PASS, 'name' => DB_NAME];
    }
    // Auto-leitura de /etc/freepbx.conf
    $defaults = ['host' => 'localhost', 'user' => 'asteriskuser',
                 'pass' => 'amp109',   'name' => 'asterisk'];
    foreach (['/etc/freepbx.conf', '/etc/schmooze/pbx.conf'] as $cfg) {
        $fc = @file_get_contents($cfg);
        if (!$fc) continue;
        // Formato: $amp_conf['AMPDBHOST'] = 'localhost';
        if (preg_match("/amp_conf\['AMPDBHOST'\]\s*=\s*'([^']+)'/",  $fc, $m)) $defaults['host'] = $m[1];
        if (preg_match("/amp_conf\['AMPDBUSER'\]\s*=\s*'([^']+)'/",  $fc, $m)) $defaults['user'] = $m[1];
        if (preg_match("/amp_conf\['AMPDBPASS'\]\s*=\s*'([^']+)'/",  $fc, $m)) $defaults['pass'] = $m[1];
        if (preg_match("/amp_conf\['AMPDBNAME'\]\s*=\s*'([^']+)'/",  $fc, $m)) $defaults['name'] = $m[1];
        // Limpa possível lixo de markdown no host: [texto](url) → só o hostname real
        $defaults['host'] = preg_replace('/\[([^\]]+)\]\([^\)]*\)/', '$1', $defaults['host']);
        $defaults['host'] = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $defaults['host']);
        if ($defaults['host'] === '') $defaults['host'] = 'localhost';
        break;
    }
    return $defaults;
}

function getExtensionNames(): array {
    $names = [];
    $creds = getFreePBXDbCredentials();

    try {
        $pdo = new PDO(
            "mysql:host={$creds['host']};dbname={$creds['name']};charset=utf8",
            $creds['user'], $creds['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_TIMEOUT => 3]
        );

        // ── Estratégia 1: tabela `userman_users` (nomes reais do FreePBX) ──
        // Guarda o nome completo dos usuários associados a ramais
        $tables = $pdo->query("SHOW TABLES LIKE 'userman_users'")->fetchAll();
        if (!empty($tables)) {
            $stmt = $pdo->query(
                "SELECT u.default_extension, m.displayname
                 FROM userman_users m
                 JOIN users u ON u.extension = m.username
                 WHERE m.displayname IS NOT NULL AND m.displayname != ''
                 LIMIT 500"
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ext  = trim($row['default_extension'] ?? '');
                $name = trim($row['displayname'] ?? '');
                if ($ext !== '' && $name !== '' && $name !== $ext) {
                    $names[$ext] = $name;
                }
            }
        }

        // ── Estratégia 2: tabela `users` do FreePBX ──────────
        // Só usa se o nome for diferente do número do ramal
        if (empty($names)) {
            $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
            if (!empty($tables)) {
                $stmt = $pdo->query(
                    "SELECT extension, name FROM users
                     WHERE extension IS NOT NULL AND name IS NOT NULL AND name != ''
                     LIMIT 500"
                );
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ext  = trim($row['extension']);
                    $name = trim($row['name']);
                    if ($ext !== '' && $name !== '' && $name !== $ext) {
                        $names[$ext] = $name;
                    }
                }
            }
        }

        // ── Estratégia 3: tabela `pjsip` (CallerID configurado no ramal) ──
        if (empty($names)) {
            $tables = $pdo->query("SHOW TABLES LIKE 'pjsip'")->fetchAll();
            if (!empty($tables)) {
                $stmt = $pdo->query(
                    "SELECT id, data FROM pjsip
                     WHERE keyword = 'callerid' AND data IS NOT NULL AND data != ''
                     LIMIT 500"
                );
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ext = trim($row['id']);
                    $cid = trim($row['data']);
                    // Formatos: "Nome <ext>", Nome <ext>, Nome
                    if (preg_match('/^"([^"]+)"/', $cid, $n))      $name = trim($n[1]);
                    elseif (preg_match('/^([^<"]+)</', $cid, $n))  $name = trim($n[1]);
                    else $name = $cid;
                    if ($name !== '' && $name !== $ext) $names[$ext] = $name;
                }
            }
        }

        // ── Estratégia 4: tabela `sip` (chan_sip legado) ─────
        if (empty($names)) {
            $tables = $pdo->query("SHOW TABLES LIKE 'sip'")->fetchAll();
            if (!empty($tables)) {
                $stmt = $pdo->query(
                    "SELECT id, data FROM sip
                     WHERE keyword = 'callerid' AND data IS NOT NULL AND data != ''
                     LIMIT 500"
                );
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ext = trim($row['id']);
                    $cid = trim($row['data']);
                    if (preg_match('/^"([^"]+)"/', $cid, $n))      $name = trim($n[1]);
                    elseif (preg_match('/^([^<"]+)</', $cid, $n))  $name = trim($n[1]);
                    else $name = $cid;
                    if ($name !== '' && $name !== $ext) $names[$ext] = $name;
                }
            }
        }

    } catch (Exception $e) {
        // Banco indisponível — números serão exibidos como fallback
    }

    // ── Estratégia 5: arquivos de configuração do Asterisk ────
    if (empty($names)) {
        $conf_files = [
            '/etc/asterisk/pjsip_additional.conf',
            '/etc/asterisk/pjsip.conf',
            '/etc/asterisk/sip_additional.conf',
            '/etc/asterisk/sip.conf',
        ];
        foreach ($conf_files as $file) {
            $content = @file_get_contents($file);
            if (!$content) continue;
            $current_ext = null;
            foreach (explode("\n", $content) as $line) {
                $line = trim($line);
                if (preg_match('/^\[(\d+)\]/', $line, $m)) {
                    $current_ext = $m[1];
                } elseif ($current_ext && preg_match('/^callerid\s*=\s*(.+)/i', $line, $m)) {
                    $cid = trim($m[1]);
                    if (preg_match('/^"([^"]+)"/', $cid, $n))      $name = trim($n[1]);
                    elseif (preg_match('/^([^<"]+)</', $cid, $n))  $name = trim($n[1]);
                    else $name = $cid;
                    if ($name !== '' && $name !== $current_ext) $names[$current_ext] = $name;
                }
            }
            if (!empty($names)) break;
        }
    }

    return $names;
}

// ── Lê nomes personalizados do arquivo local (prioridade máxima) ──
$nomes_file = __DIR__ . '/painel_nomes.json';
$nomes_local = [];
if (file_exists($nomes_file)) {
    $nj = @file_get_contents($nomes_file);
    if ($nj) $nomes_local = json_decode($nj, true) ?: [];
}

// Carrega nomes e registra diagnóstico de banco
$ext_names   = getExtensionNames();
$db_ok       = false;
$db_msg      = 'not_connected';
try {
    $creds_diag = getFreePBXDbCredentials();
    $pdo_diag   = new PDO(
        "mysql:host={$creds_diag['host']};dbname={$creds_diag['name']};charset=utf8",
        $creds_diag['user'], $creds_diag['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $db_ok  = true;
    $db_msg = "ok ({$creds_diag['host']}/{$creds_diag['name']})";
} catch (Exception $e) {
    $db_msg = 'erro: ' . $e->getMessage();
}

// ════════════════════════════════════════════════════════════
//  CLASSE AMI
// ════════════════════════════════════════════════════════════
class AsteriskAMI {
    public  $socket    = null;
    private $connected = false;

    public function connect($host, $port, $user, $pass, $timeout = 6): bool {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->socket) return false;
        stream_set_timeout($this->socket, $timeout);
        fgets($this->socket, 4096); // Banner
        $this->send("Action: Login\r\nUsername: {$user}\r\nSecret: {$pass}\r\nEvents: off\r\n\r\n");
        $resp = $this->readBlock();
        if (($resp['Response'] ?? '') === 'Success') {
            $this->connected = true;
            return true;
        }
        return false;
    }

    public function send(string $data): void {
        if ($this->socket) fwrite($this->socket, $data);
    }

    public function action(string $action, array $params = []): void {
        $msg = "Action: {$action}\r\n";
        foreach ($params as $k => $v) $msg .= "{$k}: {$v}\r\n";
        $msg .= "\r\n";
        $this->send($msg);
    }

    public function readBlock(): array {
        $result  = [];
        $timeout = time() + AMI_TIMEOUT;
        while (!feof($this->socket) && time() < $timeout) {
            $line = fgets($this->socket, 4096);
            if ($line === false) break;
            $line = rtrim($line);
            if ($line === '') break;
            if (strpos($line, ': ') !== false) {
                [$k, $v] = explode(': ', $line, 2);
                $result[$k] = $v;
            }
        }
        return $result;
    }

    public function readUntil(string $marker): string {
        $buf      = '';
        $deadline = time() + AMI_TIMEOUT + 2;
        while (!feof($this->socket) && time() < $deadline) {
            $line = fgets($this->socket, 4096);
            if ($line === false) break;
            $buf .= $line;
            if (strpos($line, $marker) !== false) break;
        }
        return $buf;
    }

    public function disconnect(): void {
        if ($this->socket) {
            $this->send("Action: Logoff\r\n\r\n");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool { return $this->connected; }
}

// Converte buffer raw em array de eventos
function parseEvents(string $raw): array {
    $events = [];
    $blocks  = preg_split('/\r?\n\r?\n/', trim($raw));
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $item = [];
        foreach (explode("\n", $block) as $line) {
            $line = rtrim($line);
            if (strpos($line, ': ') !== false) {
                [$k, $v] = explode(': ', $line, 2);
                $item[trim($k)] = trim($v);
            }
        }
        if (!empty($item)) $events[] = $item;
    }
    return $events;
}

// ════════════════════════════════════════════════════════════
//  COLETA DE DADOS
// ════════════════════════════════════════════════════════════
$result = [
    'ok'         => false,
    'error'      => '',
    'driver'     => '',
    'extensions' => [],
    'trunks'     => [],
    'channels'   => [],
    'queues'     => [],
    'db_status'  => 'not_connected',  // diagnóstico de conexão com banco
    'names_found'=> 0,                 // quantos nomes foram carregados do banco
    'timestamp'  => date('H:i:s'),
];

$ami = new AsteriskAMI();
if (!$ami->connect(AMI_HOST, AMI_PORT, AMI_USER, AMI_PASS, AMI_TIMEOUT)) {
    $result['error'] = 'Não foi possível conectar ao AMI. '
        . 'Verifique as configurações em painel_config.php e se o usuário AMI está criado no FreePBX.';
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}
$result['ok']          = true;
$result['db_status']   = $db_msg;
$result['names_found'] = count($nomes_local) ?: count($ext_names);

// ── 1. Canais ativos (CoreShowChannels) ──────────────────────
$ami->action('CoreShowChannels', ['ActionID' => 'ch1']);
$raw_ch = $ami->readUntil('CoreShowChannelsComplete');
$ch_events = parseEvents($raw_ch);

$active = [];   // ramal → dados do canal ativo
foreach ($ch_events as $ch) {
    if (!isset($ch['Channel'])) continue;
    // Extrai número do ramal de PJSIP/1001-xxxx, SIP/1001-xxxx, IAX2/1001-xxxx
    if (preg_match('/(?:PJSIP|SIP|IAX2)\/(\w+)-/i', $ch['Channel'], $m)) {
        $ext = $m[1];
        // Mantém o canal com estado mais relevante se já existir
        if (!isset($active[$ext]) || ($ch['ChannelStateDesc'] ?? '') === 'Up') {
            $active[$ext] = [
                'channel'     => $ch['Channel']          ?? '',
                'callerid'    => $ch['CallerIDNum']       ?? '',
                'connectedid' => $ch['ConnectedLineNum']  ?? '',
                'state'       => $ch['ChannelStateDesc']  ?? '',
                'duration'    => $ch['Duration']          ?? '',
                'application' => $ch['Application']       ?? '',
                'context'     => $ch['Context']           ?? '',
            ];
        }
    }
    $result['channels'][] = [
        'channel'     => $ch['Channel']          ?? '',
        'callerid'    => $ch['CallerIDNum']       ?? '',
        'connectedid' => $ch['ConnectedLineNum']  ?? '',
        'state'       => $ch['ChannelStateDesc']  ?? '',
        'duration'    => $ch['Duration']          ?? '',
        'context'     => $ch['Context']           ?? '',
        'app'         => $ch['Application']       ?? '',
    ];
}

// ── 2. Ramais e Troncos ───────────────────────────────────────
$driver = strtolower(SIP_DRIVER);
$loaded = false;

// ── 2a. PJSIP (padrão FreePBX) ───────────────────────────────
if ($driver === 'pjsip' || $driver === 'auto') {
    $ami->action('PJSIPShowEndpoints', ['ActionID' => 'pj1']);
    $raw_pj = $ami->readUntil('EndpointListComplete');
    $pj_events = parseEvents($raw_pj);

    foreach ($pj_events as $p) {
        if (($p['Event'] ?? '') !== 'EndpointList') continue;
        $name = $p['ObjectName'] ?? '';
        if ($name === '') continue;

        $is_trunk    = !preg_match('/^\d+$/', $name);
        $devstate    = strtolower($p['DeviceState'] ?? 'unknown');
        $contacts    = trim($p['Contacts'] ?? '');
        $registered  = ($devstate !== 'unknown' && $devstate !== 'unavailable')
                       || $contacts !== '';
        $in_call     = isset($active[$name]) || $devstate === 'inuse';
        $is_ringing  = ($devstate === 'ringing');

        // Prioridade: nome local → nome do banco → número
        $displayName = $nomes_local[$name]
                    ?? $ext_names[$name]
                    ?? ($active[$name]['callerid'] ?: null)
                    ?? $name;

        // Status detalhado
        if ($is_trunk) {
            $status = $registered ? ($in_call ? 'active' : 'online') : 'offline';
            $result['trunks'][] = [
                'name'        => $name,
                'status'      => $status,
                'callerid'    => $active[$name]['callerid']    ?? '',
                'connectedid' => $active[$name]['connectedid'] ?? '',
                'duration'    => $active[$name]['duration']    ?? '',
                'contacts'    => $contacts,
                'devstate'    => $devstate,
            ];
        } else {
            if (!$registered)     $status = 'unregistered';
            elseif ($in_call)     $status = 'incall';
            elseif ($is_ringing)  $status = 'ringing';
            else                  $status = 'idle';

            $result['extensions'][] = [
                'ext'         => $name,
                'displayName' => $displayName,
                'status'      => $status,
                'channel'     => $active[$name]['channel']     ?? '',
                'callerid'    => $active[$name]['callerid']    ?? '',
                'connectedid' => $active[$name]['connectedid'] ?? '',
                'duration'    => $active[$name]['duration']    ?? '',
                'state'       => $active[$name]['state']       ?? '',
                'devstate'    => $devstate,
            ];
        }
        $loaded = true;
    }
    if ($loaded) $result['driver'] = 'pjsip';
}

// ── 2b. chan_sip (legado / instalações antigas) ───────────────
if (!$loaded && ($driver === 'chansip' || $driver === 'auto')) {
    $ami->action('SIPpeers', ['ActionID' => 'sip1']);
    $raw_sip = $ami->readUntil('PeerlistComplete');
    $sip_events = parseEvents($raw_sip);

    foreach ($sip_events as $p) {
        if (($p['Event'] ?? '') !== 'PeerEntry') continue;
        // ObjectName ou Objectname (case pode variar)
        $name = '';
        foreach ($p as $k => $v) {
            if (strtolower($k) === 'objectname') { $name = $v; break; }
        }
        if ($name === '') continue;

        $is_trunk   = !preg_match('/^\d+$/', $name);
        $status_raw = strtolower($p['Status'] ?? 'unknown');
        $registered = strpos($status_raw, 'ok') !== false
                   || strpos($status_raw, 'reachable') !== false
                   || strpos($status_raw, 'unmonitored') !== false;
        $in_call    = isset($active[$name]);
        // Prioridade: nome local → nome do banco → número (chan_sip)
        $displayName = $nomes_local[$name]
                    ?? $ext_names[$name]
                    ?? ($active[$name]['callerid'] ?: null)
                    ?? $name;

        if ($is_trunk) {
            $result['trunks'][] = [
                'name'        => $name,
                'status'      => $registered ? ($in_call ? 'active' : 'online') : 'offline',
                'callerid'    => $active[$name]['callerid']    ?? '',
                'connectedid' => $active[$name]['connectedid'] ?? '',
                'duration'    => $active[$name]['duration']    ?? '',
                'latency'     => $p['Status'] ?? '',
            ];
        } else {
       