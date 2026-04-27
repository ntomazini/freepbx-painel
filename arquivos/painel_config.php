<?php
/**
 * ============================================================
 *  FreePBX - Painel de Controle em Tempo Real
 *  Arquivo: painel_config.php
 *  ⚠️ EDITE ESTE ARQUIVO com os dados do seu servidor
 * ============================================================
 */

// ── AMI (Asterisk Manager Interface) ────────────────────────
// Crie o usuário AMI em: FreePBX Admin → Asterisk Manager Users
define('AMI_HOST',    '127.0.0.1');
define('AMI_PORT',    5038);
define('AMI_USER',    'painel');        // usuário criado no FreePBX GUI
define('AMI_PASS',    'SuaSenhaAqui'); // senha criada no FreePBX GUI
define('AMI_TIMEOUT', 6);

// ── Banco de dados FreePBX ───────────────────────────────────
// Credenciais do banco de dados do FreePBX.
// Se deixar DB_HOST vazio, tenta ler automaticamente de /etc/freepbx.conf
// Preencha manualmente caso a leitura automática falhe:
define('DB_HOST', 'localhost');      // host do MySQL
define('DB_USER', 'freepbxuser');   // usuário do banco
define('DB_PASS', '');              // ⚠️ preencha com a senha do freepbxuser
define('DB_NAME', 'asterisk');      // nome do banco

// ── Painel ───────────────────────────────────────────────────
define('REFRESH_MS',    3000);   // intervalo de atualização (ms)
define('PAINEL_TITULO', 'FreePBX — Painel de Controle');
define('PAINEL_VERSAO', '1.0');

// ── Canal SIP principal ──────────────────────────────────────
// 'pjsip'   = FreePBX padrão (Chan_PJSIP)
// 'chansip' = instalações antigas com chan_sip
// 'auto'    = detecta automaticamente (tenta PJSIP primeiro)
define('SIP_DRIVER', 'auto');

// ── Segurança ────────────────────────────────────────────────
// Defina um token para proteger a API (deixe vazio para desativar)
define('API_TOKEN', '');

// ── WebSocket para Softphone (JsSIP) ─────────────────────────
// Deixe vazio para usar o mesmo host da página automaticamente
define('WS_HOST', '');   // ex: '192.168.1.100'
define('WS_PORT', 8088);
define('WS_PATH', '/ws');
