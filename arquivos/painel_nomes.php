<?php
/**
 * ============================================================
 *  FreePBX - Painel de Controle
 *  Arquivo: painel_nomes.php
 *  Função:  Salva e lê os nomes personalizados dos ramais
 *           (substitui o campo Display Name do FreePBX,
 *            que exige só alfanumérico)
 * ============================================================
 */

require_once __DIR__ . '/painel_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Token de segurança (mesma proteção da API)
if (API_TOKEN !== '') {
    $token = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_PAINEL_TOKEN'] ?? '';
    if ($token !== API_TOKEN) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token inválido']);
        exit;
    }
}

$NOMES_FILE = __DIR__ . '/painel_nomes.json';

// ── GET: retorna nomes salvos ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($NOMES_FILE)) {
        echo json_encode((object)[]);   // objeto vazio {}
        exit;
    }
    $json = file_get_contents($NOMES_FILE);
    $data = json_decode($json, true);
    if (!is_array($data)) $data = [];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST: salva nomes ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!is_array($data)) {
        echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
        exit;
    }

    // Sanitiza: só guarda entradas com ramal numérico e nome não-vazio
    $clean = [];
    foreach ($data as $ext => $name) {
        $ext  = preg_replace('/[^0-9]/', '', (string)$ext);
        $name = trim((string)$name);
        if ($ext !== '' && $name !== '') {
            $clean[$ext] = $name;
        }
    }

    $ok = file_put_contents($NOMES_FILE, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if ($ok !== false) {
        echo json_encode(['ok' => true, 'saved' => count($clean)]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Não foi possível salvar o arquivo. Verifique permissões em ' . $NOMES_FILE]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método não permitido']);
