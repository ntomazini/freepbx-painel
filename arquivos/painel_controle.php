<?php
/**
 * ============================================================
 *  FreePBX 5 - Painel de Controle em Tempo Real
 *  Arquivo: /var/www/html/painel_controle.php
 *  Acesso:  http://SEU_SERVIDOR/painel_controle.php
 * ============================================================
 */
require_once __DIR__ . '/painel_config.php';
if(!defined('REFRESH_MS')) define('REFRESH_MS',3000);  // intervalo de atualização em ms

// ── Licença ──────────────────────────────────────────────────


// ────────────────────────────────────────────────────────────
session_start();
// Proteção básica: requer sessão FreePBX ativa
// Descomente para forçar login:
// if (empty($_SESSION['AMP_user_id'])) { header('Location: /index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FreePBX — Painel de Controle em Tempo Real</title>
<style>
/* ── Reset ── */
*, *:before, *:after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: "Segoe UI", "Noto Sans", Arial, sans-serif; font-size: 13px; background: #f1f8f0; color: #212121; overflow-x: hidden; }

/* ── Paleta ── */
:root {
  --green:      #43a047;
  --green-d:    #2e7d32;
  --green-l:    #66bb6a;
  --bg-dark:    #f1f8f0;
  --bg-panel:   #ffffff;
  --bg-card:    #e8f5e9;
  --border:     rgba(0,0,0,0.10);
  /* Status de ramal */
  --idle:       #e65100;   /* laranja — registrado/disponível */
  --idle-text:  #ffffff;
  --incall:     #b71c1c;   /* vermelho escuro — em chamada */
  --incall-text:#ffcdd2;
  --ringing:    #f9a825;   /* amarelo — tocando */
  --ring-text:  #212121;
  --unreg:      #424242;   /* cinza — não registrado */
  --unreg-text: #9e9e9e;
  --onhold:     #6a1b9a;   /* roxo — em espera */
  --online:     #1565c0;   /* azul — tronco online */
  --trunk-act:  #00695c;   /* verde-teal — tronco com chamada */
  --offline:    #37474f;   /* cinza escuro — offline */
}

/* ── Top bar ── */
.topbar {
  height: 54px;
  background: linear-gradient(90deg, #2e7d32 0%, #43a047 100%);
  display: flex; align-items: center; padding: 0 20px; gap: 14px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.4);
  position: sticky; top: 0; z-index: 100;
}
.topbar .logo { font-size: 18px; font-weight: 800; color: #fff; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
.topbar .logo span { font-size: 11px; font-weight: 400; color: #c8e6c9; }
.topbar .title { color: #fff; font-size: 14px; font-weight: 600; }
.topbar .spacer { flex: 1; }
.topbar .clock { color: #c8e6c9; font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: 2px; }
.topbar .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #4caf50; box-shadow: 0 0 6px #4caf50; }
.topbar .status-dot.offline { background: #f44336; box-shadow: 0 0 6px #f44336; }
.topbar .status-label { font-size: 11px; color: #c8e6c9; }
.topbar .refresh-btn {
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
  color: #fff; padding: 5px 12px; border-radius: 4px; font-size: 11px;
  cursor: pointer; transition: background 0.2s;
}
.topbar .refresh-btn:hover { background: rgba(255,255,255,0.25); }

/* ── Layout principal ── */
.main { padding: 16px; display: flex; flex-direction: column; gap: 16px; }

/* ── Section card ── */
.section {
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.section-head {
  display: flex; align-items: center; gap: 10px; padding: 10px 16px;
  background: rgba(67,160,71,0.12);
  border-bottom: 1px solid var(--border);
}
.section-head .sh-title { font-size: 13px; font-weight: 700; color: #2e7d32; text-transform: uppercase; letter-spacing: 0.8px; }
.section-head .sh-count {
  background: var(--green); color: #fff; border-radius: 12px;
  padding: 1px 8px; font-size: 11px; font-weight: 700;
}
.section-head .sh-filter { margin-left: auto; display: flex; gap: 8px; align-items: center; }
.filter-btn {
  padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border);
  font-size: 11px; cursor: pointer; background: transparent; color: #555;
  transition: all 0.2s;
}
.filter-btn.active, .filter-btn:hover { background: var(--green); color: #fff; border-color: var(--green); }
.section-body { padding: 14px; }

/* ── LEGEND ── */
.legend { display: flex; gap: 14px; flex-wrap: wrap; padding: 8px 16px; border-bottom: 1px solid var(--border); }
.leg-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #555; }
.leg-dot { width: 12px; height: 12px; border-radius: 3px; }

/* ── GRID DE RAMAIS ── */
.ext-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
  gap: 4px;
}

/* ── TILE DE RAMAL — estilo FreePBX 4 retangular ── */
.ext-tile {
  border-radius: 4px;
  padding: 5px 8px;
  cursor: default;
  border: 1px solid rgba(0,0,0,0.22);
  transition: transform 0.1s, box-shadow 0.1s;
  min-height: 34px;
  display: flex; flex-direction: column; justify-content: center;
}
.ext-tile:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(0,0,0,0.15); }

/* Status colors */
.ext-tile.idle        { background: var(--idle);   border-color: #bf360c; }
.ext-tile.incall      { background: var(--idle);   border-color: #bf360c; }
.ext-tile.ringing     { background: var(--ringing); border-color: #f57f17; animation: pulse-ring 0.8s infinite alternate; }
.ext-tile.unregistered{ background: #fffde7; border-color: #e0e0e0; }
.ext-tile.onhold      { background: var(--onhold);  border-color: #4a148c; }

@keyframes pulse-ring {
  from { box-shadow: 0 0 4px rgba(249,168,37,0.4); }
  to   { box-shadow: 0 0 14px rgba(249,168,37,0.9); }
}

/* Linha principal: ⓘ + número: nome + ícone telefone */
.ext-main-row {
  display: flex; align-items: center; justify-content: space-between; gap: 4px;
}
.ext-label {
  display: flex; align-items: center; gap: 3px;
  font-size: 12px; font-weight: 700; color: #fff;
  flex: 1; overflow: hidden; white-space: nowrap;
}
.ext-tile.unregistered .ext-label { color: #757575; }
.ext-tile.ringing      .ext-label { color: #212121; }
.ext-num-bold { font-weight: 800; flex-shrink: 0; }
.ext-colon    { font-weight: 400; margin: 0 1px; flex-shrink: 0; }
.ext-name-txt { font-weight: 400; overflow: hidden; text-overflow: ellipsis; }
.ext-info-ico { font-size: 10px; opacity: 0.55; flex-shrink: 0; }
.ext-phone-ico { font-size: 16px; flex-shrink: 0; }
.ext-tile.unregistered .ext-phone-ico { opacity: 0.35; }

/* Linha de chamada: duração + número */
.ext-call-row {
  font-size: 10px; font-weight: 700;
  color: #0d47a1;
  margin-top: 2px; padding-left: 2px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ext-tile.ringing .ext-call-row { color: #e65100; }

/* ── TRONCOS — estilo FreePBX 4 retangular ── */
.trunk-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
  gap: 4px;
}
.trunk-tile {
  border-radius: 4px; padding: 5px 8px;
  border: 1px solid rgba(0,0,0,0.22);
  transition: transform 0.1s, box-shadow 0.1s;
  min-height: 34px;
  display: flex; flex-direction: column; justify-content: center;
}
.trunk-tile:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(0,0,0,0.15); }
.trunk-tile.online  { background: var(--idle);      border-color: #bf360c; }
.trunk-tile.active  { background: var(--idle);      border-color: #bf360c; }
.trunk-tile.offline { background: #fffde7;          border-color: #e0e0e0; }

/* Linha principal do tronco */
.trunk-main-row {
  display: flex; align-items: center; justify-content: space-between; gap: 4px;
}
.trunk-label {
  font-size: 12px; font-weight: 700; color: #fff;
  flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.trunk-tile.offline .trunk-label { color: #757575; }
.trunk-swap-ico { font-size: 14px; flex-shrink: 0; }
.trunk-tile.offline .trunk-swap-ico { opacity: 0.35; }

/* Linha de chamada do tronco */
.trunk-call-row {
  font-size: 10px; font-weight: 700; color: #0d47a1;
  margin-top: 2px; padding-left: 2px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── FILAS ── */
.queue-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}
.queue-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px; overflow: hidden;
}
.queue-head {
  background: rgba(67,160,71,0.2);
  padding: 8px 12px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid var(--border);
}
.queue-title { font-weight: 700; color: #2e7d32; font-size: 13px; }
.queue-stats { display: flex; gap: 10px; }
.q-stat { font-size: 11px; color: #555; }
.q-stat strong { color: #212121; }
.queue-callers { padding: 8px 12px; }
.caller-item {
  display: flex; align-items: center; gap: 8px; padding: 4px 0;
  border-bottom: 1px solid rgba(0,0,0,0.06); font-size: 12px;
}
.caller-item:last-child { border-bottom: none; }
.caller-pos { width: 20px; height: 20px; background: var(--green-d); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff; flex-shrink: 0; }
.caller-num { color: #212121; font-weight: 600; flex: 1; }
.caller-wait { font-size: 11px; color: #666; font-variant-numeric: tabular-nums; }
.no-callers { padding: 8px 12px; font-size: 12px; color: #888; font-style: italic; }
.queue-agents { padding: 6px 12px; border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 4px; }
.agent-chip {
  padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;
}
.agent-chip.available  { background: rgba(230,81,0,0.8);  color: #fff; }
.agent-chip.inuse      { background: rgba(183,28,28,0.8); color: #fff; }
.agent-chip.unavailable{ background: rgba(66,66,66,0.8);  color: #9e9e9e; }
.agent-chip.paused     { background: rgba(106,27,154,0.6);color: #ce93d8; }
.agent-chip { cursor: pointer; transition: opacity 0.15s; }
.agent-chip:hover { opacity: 0.8; }

/* ── MENU DE AÇÕES ── */
#action-popup {
  position: fixed; z-index: 9000;
  background: #1e1e2e;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 10px;
  padding: 10px 8px 8px;
  min-width: 210px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.7);
  display: none;
  animation: popIn 0.12s ease;
}
#action-popup.show { display: block; }
@keyframes popIn {
  from { opacity:0; transform: scale(0.92) translateY(-4px); }
  to   { opacity:1; transform: scale(1)    translateY(0); }
}
.ap-title {
  font-size: 11px; font-weight: 700; color: #aaa; text-transform: uppercase;
  letter-spacing: 0.8px; padding: 0 6px 8px; margin-bottom: 4px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.ap-ext-name { color: #fff; font-size: 13px; text-transform: none; letter-spacing: 0; }
.ap-btn {
  display: flex; align-items: center; gap: 9px;
  width: 100%; padding: 8px 10px; margin-bottom: 3px;
  background: rgba(255,255,255,0.07); border: none;
  border-radius: 6px; color: #ddd; font-size: 12px; font-weight: 600;
  cursor: pointer; text-align: left; transition: background 0.15s;
}
.ap-btn:last-child { margin-bottom: 0; }
.ap-btn:hover { background: rgba(255,255,255,0.16); color: #fff; }
.ap-btn .ap-ico { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }
.ap-btn.green  { background: rgba(46,125,50,0.35); }
.ap-btn.green:hover  { background: rgba(46,125,50,0.65); color:#fff; }
.ap-btn.red    { background: rgba(183,28,28,0.35); }
.ap-btn.red:hover    { background: rgba(183,28,28,0.65); color:#fff; }
.ap-btn.blue   { background: rgba(21,101,192,0.35); }
.ap-btn.blue:hover   { background: rgba(21,101,192,0.65); color:#fff; }
.ap-btn.purple { background: rgba(106,27,154,0.35); }
.ap-btn.purple:hover { background: rgba(106,27,154,0.65); color:#fff; }
.ap-btn.amber  { background: rgba(230,81,0,0.35); }
.ap-btn.amber:hover  { background: rgba(230,81,0,0.65); color:#fff; }
.ap-sep { height: 1px; background: rgba(255,255,255,0.08); margin: 5px 0; }

/* ── MODAL DE TRANSFERÊNCIA ── */
#modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.65);
  z-index: 9500; display: none;
  align-items: center; justify-content: center;
}
#modal-overlay.show { display: flex; }
.modal-box {
  background: #1e1e2e; border: 1px solid rgba(255,255,255,0.15);
  border-radius: 12px; padding: 24px 26px; min-width: 300px;
  box-shadow: 0 16px 50px rgba(0,0,0,0.8);
  animation: popIn 0.15s ease;
}
.modal-title { font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 6px; }
.modal-sub   { font-size: 12px; color: #888; margin-bottom: 16px; }
.modal-input {
  width: 100%; padding: 11px 14px; border-radius: 7px;
  border: 1.5px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.07);
  color: #fff; font-size: 15px; font-weight: 700; margin-bottom: 16px;
  box-sizing: border-box; text-align: center; letter-spacing: 2px;
}
.modal-input:focus { outline: none; border-color: #66bb6a; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; }
.modal-btn {
  padding: 9px 20px; border-radius: 7px; border: none;
  font-size: 13px; font-weight: 700; cursor: pointer;
}
.modal-btn.confirm { background: #43a047; color: #fff; }
.modal-btn.confirm:hover { background: #2e7d32; }
.modal-btn.cancel  { background: rgba(255,255,255,0.1); color: #aaa; }
.modal-btn.cancel:hover { background: rgba(255,255,255,0.18); }

/* ── MEU RAMAL ── */
.my-ext-wrap { display: flex; align-items: center; gap: 6px; }
.my-ext-lbl  { font-size: 11px; color: #a5d6a7; white-space: nowrap; }
.my-ext-inp  {
  width: 68px; padding: 4px 7px; border-radius: 5px;
  border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.12);
  color: #fff; font-size: 13px; font-weight: 700; text-align: center;
}
.my-ext-inp:focus { outline: none; border-color: #66bb6a; }

/* ── STATS BAR ── */
.stats-bar {
  display: flex; gap: 12px; flex-wrap: wrap;
  padding: 10px 16px;
  background: rgba(67,160,71,0.08);
  border-bottom: 1px solid var(--border);
}
.stat-pill {
  display: flex; align-items: center; gap: 6px;
  background: rgba(0,0,0,0.04); border-radius: 20px;
  padding: 4px 12px; border: 1px solid var(--border);
}
.stat-pill .sp-dot { width: 8px; height: 8px; border-radius: 50%; }
.stat-pill .sp-label { font-size: 11px; color: #666; }
.stat-pill .sp-val { font-size: 14px; font-weight: 700; color: #212121; }

/* ── Error/Loading states ── */
.error-box {
  background: rgba(183,28,28,0.2); border: 1px solid rgba(255,100,100,0.3);
  border-radius: 6px; padding: 16px; color: #ef9a9a; font-size: 13px; margin: 10px 0;
}
.error-box code { display: block; margin-top: 8px; font-size: 11px; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 4px; color: #ffcc80; }
.loading { text-align: center; padding: 40px; color: #888; }
.loading .spinner { display: inline-block; width: 32px; height: 32px; border: 3px solid rgba(67,160,71,0.3); border-top-color: #43a047; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 10px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Empty state ── */
.empty { text-align: center; padding: 20px; color: #888; font-size: 12px; }

/* ── Scrollbar ── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #2e7d32; border-radius: 3px; }

/* ── Responsive ── */
@media (max-width: 600px) {
  .ext-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
  .trunk-grid { grid-template-columns: 1fr; }
  .stats-bar { gap: 8px; }
}
/* ── SOFTPHONE WIDGET ── */
#softphone {
  position: fixed; bottom: 20px; right: 20px; z-index: 8000;
  font-family: "Segoe UI", Arial, sans-serif;
}
#sp-fab {
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg,#2e7d32,#43a047);
  box-shadow: 0 4px 18px rgba(0,0,0,0.45);
  border: none; cursor: pointer; font-size: 22px;
  display: flex; align-items: center; justify-content: center;
  transition: transform 0.15s, box-shadow 0.15s;
  position: relative;
}
#sp-fab:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(0,0,0,0.55); }
#sp-fab-dot {
  position: absolute; top:4px; right:4px; width:11px; height:11px;
  border-radius:50%; border:2px solid #1e1e2e; background:#f44336;
}
#sp-fab-dot.registered { background:#4caf50; }
#sp-fab-dot.calling    { background:#f9a825; animation: pulse-ring 0.6s infinite alternate; }
#sp-panel {
  display: none; flex-direction: column;
  width: 260px; border-radius: 14px; overflow: hidden;
  background: #1e1e2e; border: 1px solid rgba(255,255,255,0.12);
  box-shadow: 0 12px 40px rgba(0,0,0,0.7);
  margin-bottom: 10px; animation: popIn 0.15s ease;
}
#sp-panel.show { display: flex; }
.sp-hdr {
  background: linear-gradient(90deg,#1b5e20,#2e7d32);
  padding: 10px 14px; display: flex; align-items: center; gap: 8px;
  cursor: pointer; user-select: none;
}
.sp-hdr-ico  { font-size: 16px; }
.sp-hdr-info { flex:1; }
.sp-hdr-title { font-size: 12px; font-weight:700; color:#fff; }
.sp-hdr-sub   { font-size: 10px; color:#a5d6a7; }
.sp-hdr-close { color:#a5d6a7; font-size:14px; padding:2px 4px; cursor:pointer; }
.sp-hdr-close:hover { color:#fff; }
.sp-section { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.07); }
.sp-label { font-size: 10px; color:#888; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:6px; }
.sp-row { display:flex; gap:6px; }
.sp-inp {
  flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15);
  border-radius:6px; padding:7px 10px; color:#fff; font-size:13px;
}
.sp-inp:focus { outline:none; border-color:#66bb6a; }
.sp-inp-pass { -webkit-text-security:disc; }
.sp-btn {
  padding:7px 14px; border:none; border-radius:6px; font-size:12px;
  font-weight:700; cursor:pointer; white-space:nowrap; transition:background 0.15s;
}
.sp-btn.green  { background:#2e7d32; color:#fff; }
.sp-btn.green:hover  { background:#1b5e20; }
.sp-btn.red    { background:#b71c1c; color:#fff; }
.sp-btn.red:hover    { background:#7f0000; }
.sp-btn.gray   { background:rgba(255,255,255,0.1); color:#aaa; }
.sp-btn.gray:hover   { background:rgba(255,255,255,0.18); }
.sp-btn.amber  { background:#e65100; color:#fff; }
.sp-btn.amber:hover  { background:#bf360c; }
/* Status bar */
.sp-status-bar {
  padding:8px 14px; font-size:11px; font-weight:600; text-align:center;
  background:rgba(255,255,255,0.04);
}
.sp-status-bar.idle    { color:#66bb6a; }
.sp-status-bar.calling { color:#f9a825; }
.sp-status-bar.incall  { color:#ef9a9a; }
.sp-status-bar.incoming{ color:#80deea; background:rgba(0,150,200,0.15); }
/* Dialpad */
.sp-dialpad { padding:10px 14px 12px; }
.sp-dial-display {
  background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15);
  border-radius:7px; padding:8px 12px; color:#fff; font-size:18px; font-weight:700;
  text-align:center; margin-bottom:8px; min-height:38px; letter-spacing:2px;
  font-variant-numeric:tabular-nums;
}
.sp-keys { display:grid; grid-template-columns:repeat(3,1fr); gap:4px; margin-bottom:8px; }
.sp-key {
  padding:8px; border:none; border-radius:6px; font-size:14px; font-weight:700;
  background:rgba(255,255,255,0.09); color:#ddd; cursor:pointer; text-align:center;
  transition:background 0.1s;
}
.sp-key:hover { background:rgba(255,255,255,0.18); color:#fff; }
.sp-key.special { font-size:12px; background:rgba(255,255,255,0.05); color:#888; }
/* Call controls */
.sp-call-ctrls { display:flex; gap:6px; padding:0 14px 12px; justify-content:center; }
.sp-ctrl-btn {
  width:46px; height:46px; border-radius:50%; border:none; font-size:18px;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  transition:transform 0.1s, background 0.15s;
}
.sp-ctrl-btn:hover { transform:scale(1.1); }
.sp-ctrl-btn.answer  { background:#2e7d32; }
.sp-ctrl-btn.answer:hover  { background:#1b5e20; }
.sp-ctrl-btn.hangup  { background:#b71c1c; }
.sp-ctrl-btn.hangup:hover  { background:#7f0000; }
.sp-ctrl-btn.mute    { background:rgba(255,255,255,0.1); }
.sp-ctrl-btn.mute.active  { background:#e65100; }
.sp-call-timer { font-size:13px; color:#aaa; text-align:center; padding:0 14px 8px; font-variant-numeric:tabular-nums; }
</style>
</head>
<body>

<!-- TOP BAR -->
<header class="topbar">
  <div class="logo">
    <span>🌿</span>
    <div>FreePBX <span>Painel de Controle</span></div>
  </div>
  <div class="spacer"></div>
  <div id="clock" class="clock">--:--:--</div>
  <div id="conn-dot" class="status-dot offline"></div>
  <div id="conn-label" class="status-label">Conectando...</div>
  <div class="my-ext-wrap" title="Seu ramal — necessário para Espiar, Ligar e Intrometer">
    <span class="my-ext-lbl">🎧 Meu Ramal:</span>
    <input class="my-ext-inp" id="my-ext" type="text" inputmode="numeric" maxlength="6"
      placeholder="ex: 200" oninput="saveMeuRamal(this.value)">
  </div>
  <button class="refresh-btn" onclick="fetchData()">⟳ Atualizar</button>
  <button class="refresh-btn" onclick="openNamesEditor()" title="Editar nomes dos ramais">✏️ Nomes</button>
  <a href="/index.php" style="color:#c8e6c9;font-size:12px;text-decoration:none;">← FreePBX</a>
</header>

<!-- POPUP DE AÇÕES -->
<div id="action-popup">
  <div class="ap-title">Ações — <span id="ap-ext-name" class="ap-ext-name"></span></div>
  <div id="ap-buttons"></div>
</div>

<!-- MODAL DE TRANSFERÊNCIA -->
<div id="modal-overlay">
  <div class="modal-box">
    <div class="modal-title" id="modal-title">Transferir Chamada</div>
    <div class="modal-sub"  id="modal-sub">Digite o ramal de destino</div>
    <input class="modal-input" id="modal-input" type="text" inputmode="numeric"
      maxlength="10" placeholder="ramal ou número"
      onkeydown="if(event.key==='Enter')modalConfirm()">
    <div class="modal-btns">
      <button class="modal-btn cancel" onclick="hideModal()">Cancelar</button>
      <button class="modal-btn confirm" id="modal-confirm-btn" onclick="modalConfirm()">Confirmar</button>
    </div>
  </div>
</div>

<!-- MODAL EDITOR DE NOMES -->
<div id="names-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2000;display:none;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:10px;width:520px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 8px 40px rgba(0,0,0,0.35);">
    <div style="padding:16px 20px;background:linear-gradient(90deg,#2e7d32,#43a047);border-radius:10px 10px 0 0;display:flex;align-items:center;gap:10px;">
      <span style="font-size:18px;">✏️</span>
      <div style="color:#fff;font-weight:700;font-size:15px;">Editar Nomes dos Ramais</div>
      <div style="flex:1;"></div>
      <button onclick="closeNamesEditor()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;border-radius:4px;padding:4px 10px;cursor:pointer;font-size:14px;">✕</button>
    </div>
    <div style="padding:10px 20px;background:#e8f5e9;border-bottom:1px solid #c8e6c9;font-size:12px;color:#2e7d32;">
      Configure o nome de exibição de cada ramal. Aceita qualquer caractere — acentos, espaços, etc.
    </div>
    <div id="names-list" style="overflow-y:auto;flex:1;padding:14px 20px;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
      <div style="color:#aaa;text-align:center;grid-column:span 2;padding:30px;">Carregando ramais...</div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid #eee;display:flex;gap:10px;justify-content:flex-end;">
      <button onclick="closeNamesEditor()" style="padding:7px 18px;border:1px solid #ccc;background:#fff;border-radius:5px;cursor:pointer;font-size:13px;">Cancelar</button>
      <button onclick="saveNames()" style="padding:7px 20px;background:#43a047;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:13px;font-weight:700;">💾 Salvar Nomes</button>
    </div>
  </div>
</div>

<!-- STATS BAR -->
<div class="stats-bar" id="stats-bar">
  <div class="stat-pill"><div class="sp-dot" style="background:#e65100;"></div><div class="sp-label">Disponíveis</div><div class="sp-val" id="cnt-idle">-</div></div>
  <div class="stat-pill"><div class="sp-dot" style="background:#b71c1c;box-shadow:0 0 6px #b71c1c;"></div><div class="sp-label">Em Chamada</div><div class="sp-val" id="cnt-incall">-</div></div>
  <div class="stat-pill"><div class="sp-dot" style="background:#f9a825;"></div><div class="sp-label">Tocando</div><div class="sp-val" id="cnt-ringing">-</div></div>
  <div class="stat-pill"><div class="sp-dot" style="background:#424242;"></div><div class="sp-label">Não Registrados</div><div class="sp-val" id="cnt-unreg">-</div></div>
  <div class="stat-pill"><div class="sp-dot" style="background:#1565c0;"></div><div class="sp-label">Troncos Online</div><div class="sp-val" id="cnt-trunks">-</div></div>
  <div class="stat-pill"><div class="sp-dot" style="background:#43a047;"></div><div class="sp-label">Canais Ativos</div><div class="sp-val" id="cnt-channels">-</div></div>
</div>

<!-- SOFTPHONE WIDGET -->
<div id="softphone">
  <div id="sp-panel">
    <!-- Header -->
    <div class="sp-hdr" onclick="spTogglePanel()">
      <span class="sp-hdr-ico">☎</span>
      <div class="sp-hdr-info">
        <div class="sp-hdr-title">Softphone WebRTC</div>
        <div class="sp-hdr-sub" id="sp-hdr-sub">Desconectado</div>
      </div>
      <span class="sp-hdr-close" onclick="event.stopPropagation();spTogglePanel()">✕</span>
    </div>

    <!-- Formulário de conexão -->
    <div id="sp-form" class="sp-section">
      <div class="sp-label">Conectar ao ramal</div>
      <div class="sp-row" style="margin-bottom:6px;">
        <input id="sp-f-ext"  class="sp-inp" type="text" inputmode="numeric" placeholder="Ramal (ex: 201)" maxlength="6">
      </div>
      <div class="sp-row">
        <input id="sp-f-pass" class="sp-inp sp-inp-pass" type="text" placeholder="Senha SIP">
        <button class="sp-btn green" onclick="spConnect()">Entrar</button>
      </div>
    </div>

    <!-- Status bar -->
    <div id="sp-status-bar" class="sp-status-bar idle" style="display:none;">🟢 Livre — pronto para ligar</div>

    <!-- Discador -->
    <div id="sp-dialpad" class="sp-dialpad" style="display:none;">
      <div class="sp-dial-display" id="sp-dial-display">&#8203;</div>
      <div class="sp-keys">
        <button class="sp-key" onclick="spKey('1')">1</button>
        <button class="sp-key" onclick="spKey('2')">2</button>
        <button class="sp-key" onclick="spKey('3')">3</button>
        <button class="sp-key" onclick="spKey('4')">4</button>
        <button class="sp-key" onclick="spKey('5')">5</button>
        <button class="sp-key" onclick="spKey('6')">6</button>
        <button class="sp-key" onclick="spKey('7')">7</button>
        <button class="sp-key" onclick="spKey('8')">8</button>
        <button class="sp-key" onclick="spKey('9')">9</button>
        <button class="sp-key special" onclick="spKey('*')">*</button>
        <button class="sp-key" onclick="spKey('0')">0</button>
        <button class="sp-key special" onclick="spBackspace()">⌫</button>
      </div>
      <div class="sp-row">
        <button class="sp-btn green" style="flex:1;padding:10px;" onclick="spCall()">📞 Ligar</button>
        <button class="sp-btn gray" onclick="spDisconnect()">Sair</button>
      </div>
    </div>

    <!-- Controles de chamada -->
    <div id="sp-call-area" style="display:none;">
      <div id="sp-call-info" class="sp-status-bar incall"></div>
      <div id="sp-call-timer" class="sp-call-timer"></div>
      <div class="sp-call-ctrls" id="sp-call-ctrls"></div>
    </div>
  </div>

  <!-- FAB (botão flutuante) -->
  <button id="sp-fab" onclick="spTogglePanel()" title="Softphone WebRTC">
    ☎
    <div id="sp-fab-dot"></div>
  </button>
</div>

<audio id="sp-audio-remote" autoplay></audio>
<audio id="sp-audio-ring" loop>
  <source src="data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=">
</audio>

<!-- MAIN CONTENT -->
<div class="main">

  <!-- LEGENDA -->
  <div style="display:flex;gap:14px;flex-wrap:wrap;padding:4px 0;">
    <div class="leg-item"><div class="leg-dot" style="background:#e65100;"></div> Registrado / Disponível</div>
    <div class="leg-item"><div class="leg-dot" style="background:#b71c1c;"></div> Em Chamada</div>
    <div class="leg-item"><div class="leg-dot" style="background:#f9a825;"></div> Tocando / Chamando</div>
    <div class="leg-item"><div class="leg-dot" style="background:#6a1b9a;"></div> Em Espera (Hold)</div>
    <div class="leg-item"><div class="leg-dot" style="background:#424242;"></div> Não Registrado</div>
    <div class="leg-item"><div class="leg-dot" style="background:#1565c0;"></div> Tronco Online</div>
    <div class="leg-item"><div class="leg-dot" style="background:#00695c;"></div> Tronco com Chamada</div>
  </div>

  <!-- RAMAIS -->
  <div class="section">
    <div class="section-head">
      <span>📞</span>
      <div class="sh-title">Ramais</div>
      <div id="ext-count" class="sh-count">0</div>
      <div class="sh-filter">
        <button class="filter-btn active" onclick="filterExt('all',this)">Todos</button>
        <button class="filter-btn" onclick="filterExt('idle',this)">Disponíveis</button>
        <button class="filter-btn" onclick="filterExt('incall',this)">Em Chamada</button>
        <button class="filter-btn" onclick="filterExt('unregistered',this)">Offline</button>
      </div>
    </div>
    <div class="section-body">
      <div id="ext-grid" class="ext-grid">
        <div class="loading"><div class="spinner"></div><br>Carregando ramais...</div>
      </div>
    </div>
  </div>

  <!-- TRONCOS -->
  <div class="section">
    <div class="section-head">
      <span>🔌</span>
      <div class="sh-title">Troncos SIP/IAX</div>
      <div id="trunk-count" class="sh-count">0</div>
    </div>
    <div class="section-body">
      <div id="trunk-grid" class="trunk-grid">
        <div class="loading"><div class="spinner"></div><br>Carregando troncos...</div>
      </div>
    </div>
  </div>

  <!-- CANAIS ATIVOS -->
  <div class="section" id="channels-section">
    <div class="section-head">
      <span>⚡</span>
      <div class="sh-title">Canais Ativos</div>
      <div id="channel-count" class="sh-count">0</div>
    </div>
    <div class="section-body" style="padding:0;">
      <div id="channels-table"></div>
    </div>
  </div>

  <!-- FILAS -->
  <div class="section">
    <div class="section-head">
      <span>🎯</span>
      <div class="sh-title">Filas de Atendimento</div>
      <div id="queue-count" class="sh-count">0</div>
    </div>
    <div class="section-body">
      <div id="queue-grid" class="queue-grid">
        <div class="loading"><div class="spinner"></div><br>Carregando filas...</div>
      </div>
    </div>
  </div>

</div><!-- /main -->

<script>
// ── Configuração ──────────────────────────────────────────
const API_URL      = 'painel_api.php';
const REFRESH_MS   = 2000;  // 2 segundos
let currentFilter  = 'all';
let allExtensions  = [];
let refreshTimer   = null;
let lastData       = null;

// ── Relógio ───────────────────────────────────────────────
function updateClock() {
  const now = new Date();
  document.getElementById('clock').textContent =
    String(now.getHours()).padStart(2,'0') + ':' +
    String(now.getMinutes()).padStart(2,'0') + ':' +
    String(now.getSeconds()).padStart(2,'0');
}
setInterval(updateClock, 1000);
updateClock();

// ── Formatação de duração ─────────────────────────────────
function fmtDuration(d) {
  if (!d) return '';
  const parts = String(d).split(':');
  if (parts.length === 3) {
    const h = parseInt(parts[0]), m = parseInt(parts[1]), s = parseInt(parts[2]);
    return h > 0 ? `${h}h${String(m).padStart(2,'0')}m` : `${m}:${String(s).padStart(2,'0')}`;
  }
  const secs = parseInt(d) || 0;
  const m = Math.floor(secs/60), s = secs%60;
  return `${m}:${String(s).padStart(2,'0')}`;
}

// ── Render: Tiles de ramal — estilo FreePBX 4 ────────────
function renderExtension(ext) {
  const status    = ext.status || 'unregistered';
  const callerTgt = ext.connectedid || ext.callerid || '';
  const dur       = fmtDuration(ext.duration);
  const hasCall   = (status === 'incall' || status === 'ringing' || status === 'onhold');

  // Nome de exibição: vem do banco (displayName) ou fallback para o número
  const name = ext.displayName || ext.ext;

  // Linha de chamada: "00:01:23: 11981303339"
  let callRowHtml = '';
  if (hasCall) {
    const parts = [];
    if (dur)       parts.push(dur);
    if (callerTgt && callerTgt !== ext.ext) parts.push(callerTgt);
    if (parts.length > 0) {
      callRowHtml = `<div class="ext-call-row">${parts.join(': ')}</div>`;
    }
  }

  const phoneIcon = status === 'unregistered' ? '☎' : '☎';
  const tooltip   = `${ext.ext}: ${name} — ${statusLabel(status)}`;
  const extJson   = encodeURIComponent(JSON.stringify(ext));

  return `<div class="ext-tile ${status}" data-status="${status}" data-ext="${ext.ext}" title="${tooltip}"
    onclick="showActionMenu(event,'${extJson}')" style="cursor:pointer;">
    <div class="ext-main-row">
      <div class="ext-label">
        <span class="ext-info-ico">ℹ</span>
        <span class="ext-num-bold">${ext.ext}</span><span class="ext-colon">:</span>
        <span class="ext-name-txt">${name}</span>
      </div>
      <span class="ext-phone-ico">${phoneIcon}</span>
    </div>
    ${callRowHtml}
  </div>`;
}

function statusLabel(s) {
  return {idle:'Disponível',incall:'Em Chamada',ringing:'Tocando',unregistered:'Não Registrado',onhold:'Em Espera'}[s] || s;
}

// ── Render: Tiles de tronco — estilo FreePBX 4 ───────────
function renderTrunk(t) {
  const st = t.status || 'offline';

  // Linha de chamada: "00:00:05: 981303339"
  let callRowHtml = '';
  if (st === 'active') {
    const num = t.connectedid || t.callerid || '';
    const dur = fmtDuration(t.duration);
    const parts = [];
    if (dur) parts.push(dur);
    if (num) parts.push(num);
    if (parts.length > 0) {
      callRowHtml = `<div class="trunk-call-row">${parts.join(': ')}</div>`;
    }
  }

  const stLabel = {online:'Online / Registrado', active:'Em Chamada', offline:'Offline'}[st] || st;
  return `<div class="trunk-tile ${st}" title="${t.name} — ${stLabel}">
    <div class="trunk-main-row">
      <div class="trunk-label">${t.name}</div>
      <span class="trunk-swap-ico">⇄</span>
    </div>
    ${callRowHtml}
  </div>`;
}

// ── Render: Tabela de canais ──────────────────────────────
function renderChannels(channels) {
  if (!channels || channels.length === 0) {
    return '<div class="empty">Nenhum canal ativo no momento</div>';
  }
  let rows = channels.map(ch => `
    <tr>
      <td style="padding:7px 14px;border-bottom:1px solid rgba(0,0,0,0.06);color:#212121;font-size:12px;">${ch.channel}</td>
      <td style="padding:7px 14px;border-bottom:1px solid rgba(0,0,0,0.06);color:#555;font-size:12px;">${ch.callerid || '—'}</td>
      <td style="padding:7px 14px;border-bottom:1px solid rgba(0,0,0,0.06);color:#555;font-size:12px;">${ch.connectedid || '—'}</td>
      <td style="padding:7px 14px;border-bottom:1px solid rgba(0,0,0,0.06);font-size:12px;">
        <span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:rgba(230,81,0,0.85);color:#fff;">${ch.state || '—'}</span>
      </td>
      <td style="padding:7px 14px;border-bottom:1px solid rgba(0,0,0,0.06);color:#2e7d32;font-size:12px;font-variant-numeric:tabular-nums;">${fmtDuration(ch.duration) || '—'}</td>
      <td style="padding:7px 14px;border-bottom:1px solid rgba(0,0,0,0.06);color:#666;font-size:11px;">${ch.context || '—'}</td>
    </tr>`).join('');
  return `<table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:rgba(0,0,0,0.03);">
        <th style="padding:8px 14px;text-align:left;color:#2e7d32;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Canal</th>
        <th style="padding:8px 14px;text-align:left;color:#2e7d32;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Origem</th>
        <th style="padding:8px 14px;text-align:left;color:#2e7d32;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Destino</th>
        <th style="padding:8px 14px;text-align:left;color:#2e7d32;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Estado</th>
        <th style="padding:8px 14px;text-align:left;color:#2e7d32;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Duração</th>
        <th style="padding:8px 14px;text-align:left;color:#2e7d32;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Contexto</th>
      </tr>
    </thead>
    <tbody>${rows}</tbody>
  </table>`;
}

// ── Render: Cards de fila ─────────────────────────────────
function renderQueue(q) {
  const callers = (q.callers || []).slice().sort((a,b)=>a.position-b.position);
  const callersHtml = callers.length > 0
    ? callers.map(c => `<div class="caller-item">
        <div class="caller-pos">${c.position}</div>
        <div class="caller-num">${c.callerid || '?'}</div>
        <div class="caller-wait">⏱ ${fmtDuration(c.wait)}</div>
      </div>`).join('')
    : '<div class="no-callers">Sem chamadas em espera</div>';

  const members = (q.members || []);
  const agentsHtml = members.length > 0
    ? members.map(m => {
        const cls    = m.paused ? 'paused' : (m.status || 'unavailable');
        const icon   = m.paused ? '⏸' : {available:'🟠',inuse:'🔴',unavailable:'⚫',busy:'🔴'}[m.status] || '⚫';
        const extNum = m.ext || m.name;
        const iface  = `SIP/${extNum}`;
        const pauseIcon  = m.paused ? '▶' : '⏸';
        const pauseLabel = m.paused ? 'Retomar' : 'Pausar';
        const mJson  = encodeURIComponent(JSON.stringify({interface: iface, queue: q.name, paused: m.paused, ext: extNum}));
        return `<div class="agent-chip ${cls}" title="${m.name} — ${m.status}${m.paused?' (pausado)':''}"
          onclick="toggleQueuePause(event,'${mJson}')">${icon} ${extNum} <span style="font-size:9px;opacity:0.7;">${pauseIcon}</span></div>`;
      }).join('')
    : '<span style="color:#555;font-size:11px;">Sem agentes</span>';

  return `<div class="queue-card">
    <div class="queue-head">
      <div class="queue-title">🎯 ${q.name}</div>
      <div class="queue-stats">
        <div class="q-stat">Espera: <strong style="color:${callers.length>0?'#f9a825':'#66bb6a'}">${callers.length}</strong></div>
        <div class="q-stat">Atend.: <strong>${q.completed||0}</strong></div>
        <div class="q-stat">Aband.: <strong style="color:${(q.abandoned||0)>0?'#ef9a9a':'#aaa'}">${q.abandoned||0}</strong></div>
      </div>
    </div>
    <div class="queue-callers">${callersHtml}</div>
    <div class="queue-agents">${agentsHtml}</div>
  </div>`;
}

// ══════════════════════════════════════════════════════════
// EDITOR DE NOMES DOS RAMAIS
// ══════════════════════════════════════════════════════════
let _namesCache = {};  // cache dos nomes salvos

async function openNamesEditor() {
  const overlay = document.getElementById('names-overlay');
  overlay.style.display = 'flex';

  // Carrega nomes salvos do servidor
  try {
    const r = await fetch('painel_nomes.php?t=' + Date.now());
    if (r.ok) _namesCache = await r.json();
  } catch(e) { _namesCache = {}; }

  // Monta lista de campos
  const exts = allExtensions.length > 0 ? allExtensions
    : (lastData?.extensions || []);

  if (exts.length === 0) {
    document.getElementById('names-list').innerHTML =
      '<div style="color:#aaa;text-align:center;grid-column:span 2;padding:30px;">Nenhum ramal carregado. Aguarde a atualização do painel.</div>';
    return;
  }

  const html = exts.map(e => {
    const saved = _namesCache[e.ext] || '';
    return `<div style="display:flex;flex-direction:column;gap:3px;">
      <label style="font-size:11px;color:#555;font-weight:600;">Ramal ${e.ext}</label>
      <input type="text" id="nme-${e.ext}" value="${escHtml(saved)}"
        placeholder="Nome do usuário..."
        style="padding:6px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;width:100%;"
        onkeydown="if(event.key==='Enter')saveNames()">
    </div>`;
  }).join('');

  document.getElementById('names-list').innerHTML = html;
  // Foca no primeiro campo
  setTimeout(() => {
    const first = document.querySelector('#names-list input');
    if (first) first.focus();
  }, 50);
}

function closeNamesEditor() {
  document.getElementById('names-overlay').style.display = 'none';
}

async function saveNames() {
  const exts = allExtensions.length > 0 ? allExtensions : (lastData?.extensions || []);
  const names = {};
  exts.forEach(e => {
    const inp = document.getElementById('nme-' + e.ext);
    if (inp && inp.value.trim()) names[e.ext] = inp.value.trim();
  });

  try {
    const r = await fetch('painel_nomes.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(names)
    });
    const res = await r.json();
    if (res.ok) {
      _namesCache = names;
      showToast('✅ Nomes salvos! O painel vai atualizar em instantes.', true);
      closeNamesEditor();
      setTimeout(fetchData, 800);
    } else {
      showToast('❌ Erro ao salvar: ' + (res.error || 'desconhecido'), false);
    }
  } catch(e) {
    showToast('❌ Falha ao salvar nomes: ' + e.message, false);
  }
}

function escHtml(s) {
  return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Fechar modal de nomes ao clicar fora
document.getElementById('names-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeNamesEditor();
});

// ── Filtro de ramais ──────────────────────────────────────
function filterExt(filter, btn) {
  currentFilter = filter;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyFilter();
}

function applyFilter() {
  const tiles = document.querySelectorAll('#ext-grid .ext-tile');
  tiles.forEach(t => {
    const show = currentFilter === 'all' || t.dataset.status === currentFilter;
    t.style.display = show ? '' : 'none';
  });
}

// ── Atualiza contadores ───────────────────────────────────
function updateCounters(data) {
  const exts = data.extensions || [];
  const trunks = data.trunks || [];
  const channels = data.channels || [];
  const idle     = exts.filter(e => e.status==='idle').length;
  const incall   = exts.filter(e => e.status==='incall').length;
  const ringing  = exts.filter(e => e.status==='ringing').length;
  const unreg    = exts.filter(e => e.status==='unregistered').length;
  const tOnline  = trunks.filter(t => t.status==='online'||t.status==='active').length;

  document.getElementById('cnt-idle').textContent    = idle;
  document.getElementById('cnt-incall').textContent  = incall;
  document.getElementById('cnt-ringing').textContent = ringing;
  document.getElementById('cnt-unreg').textContent   = unreg;
  document.getElementById('cnt-trunks').textContent  = tOnline;
  document.getElementById('cnt-channels').textContent= channels.length;
}

// ── Busca dados da API ────────────────────────────────────
async function fetchData() {
  try {
    const res = await fetch(API_URL + '?t=' + Date.now());
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    lastData = data;

    if (!data.ok) {
      showError(data.error || 'Erro desconhecido');
      setConnected(false);
      return;
    }

    setConnected(true);
    allExtensions = data.extensions || [];
    updateCounters(data);
    renderAll(data);
  } catch(e) {
    setConnected(false);
    console.error('Erro ao buscar dados:', e);
  }
}

function setConnected(ok) {
  const dot   = document.getElementById('conn-dot');
  const label = document.getElementById('conn-label');
  if (ok) {
    dot.className = 'status-dot';
    label.textContent = 'Conectado · ' + (lastData?.timestamp || '');
  } else {
    dot.className = 'status-dot offline';
    label.textContent = 'Sem conexão AMI';
  }
}

function showError(msg) {
  document.getElementById('ext-grid').innerHTML =
    `<div class="error-box">❌ Erro ao conectar ao AMI<code>${msg}</code>
    <br><br>Edite o arquivo <strong>painel_api.php</strong> e configure AMI_USER e AMI_PASS.</div>`;
}

function renderAll(data) {
  // Ramais
  const extGrid = document.getElementById('ext-grid');
  if (allExtensions.length === 0) {
    extGrid.innerHTML = '<div class="empty">Nenhum ramal encontrado. Verifique as permissões AMI.</div>';
  } else {
    extGrid.innerHTML = allExtensions.map(renderExtension).join('');
    document.getElementById('ext-count').textContent = allExtensions.length;
    applyFilter();
  }

  // Troncos
  const trunks = data.trunks || [];
  const trunkGrid = document.getElementById('trunk-grid');
  if (trunks.length === 0) {
    trunkGrid.innerHTML = '<div class="empty">Nenhum tronco encontrado</div>';
  } else {
    trunkGrid.innerHTML = trunks.map(renderTrunk).join('');
    document.getElementById('trunk-count').textContent = trunks.length;
  }

  // Canais ativos
  const channels = data.channels || [];
  document.getElementById('channels-table').innerHTML = renderChannels(channels);
  document.getElementById('channel-count').textContent = channels.length;

  // Filas
  const queues = data.queues || [];
  const queueGrid = document.getElementById('queue-grid');
  if (queues.length === 0) {
    queueGrid.innerHTML = '<div class="empty">Nenhuma fila configurada</div>';
  } else {
    queueGrid.innerHTML = queues.map(renderQueue).join('');
    document.getElementById('queue-count').textContent = queues.length;
  }
}

// ══════════════════════════════════════════════════════════
// SOFTPHONE WEBRTC — JsSIP
// ══════════════════════════════════════════════════════════
(function() {
  // Carrega JsSIP — tenta local primeiro, CDN como fallback
  function loadScript(src, cb, errCb) {
    const s = document.createElement('script');
    s.src = src; s.onload = cb; s.onerror = errCb;
    document.head.appendChild(s);
  }
  loadScript(
    'jssip.min.js',
    () => { window._jssipLoaded = true;  spRestoreSession(); },
    () => loadScript(
      'https://cdn.jsdelivr.net/npm/jssip@3.10.0/dist/jssip.min.js',
      () => { window._jssipLoaded = true;  spRestoreSession(); },
      () => { window._jssipLoaded = false; }
    )
  );
})();

let _spUA        = null;   // JsSIP UA
let _spSession   = null;   // chamada atual
let _spMuted     = false;
let _spTimerInt  = null;
let _spTimerSec  = 0;
let _spPanelOpen = false;

// ── Abrir/fechar painel ───────────────────────────────────
function spTogglePanel() {
  _spPanelOpen = !_spPanelOpen;
  document.getElementById('sp-panel').classList.toggle('show', _spPanelOpen);
}

// ── Restaurar sessão salva ────────────────────────────────
function spRestoreSession() {
  const ext  = localStorage.getItem('spExt');
  const pass = sessionStorage.getItem('spPass');
  if (ext)  document.getElementById('sp-f-ext').value  = ext;
  if (pass) document.getElementById('sp-f-pass').value = pass;
  if (ext && pass) spConnect();  // reconecta automaticamente
}

// ── Conectar ao servidor SIP ──────────────────────────────
function spConnect() {
  if (!window.JsSIP) { showToast('❌ Biblioteca JsSIP não carregada ainda, tente novamente', false); return; }
  const ext  = document.getElementById('sp-f-ext').value.trim();
  const pass = document.getElementById('sp-f-pass').value.trim();
  if (!ext || !pass) { showToast('❌ Informe o ramal e a senha SIP', false); return; }

  // Desconecta UA anterior se existir
  if (_spUA) { try { _spUA.stop(); } catch(e){} _spUA = null; }

  const host   = window.location.hostname;
  const wsUri  = `ws://${host}:8088/ws`;

  JsSIP.debug.disable('JsSIP:*');

  const socket = new JsSIP.WebSocketInterface(wsUri);
  _spUA = new JsSIP.UA({
    sockets:           [socket],
    uri:               `sip:${ext}@${host}`,
    password:          pass,
    register:          true,
    register_expires:  60,
    user_agent:        'PainelFreePBX/1.0',
  });

  _spUA.on('connecting',   () => spSetStatus('connecting', '🔄 Conectando...'));
  _spUA.on('connected',    () => spSetStatus('connecting', '🔄 Registrando...'));
  _spUA.on('disconnected', () => { spSetStatus('offline','❌ Desconectado'); spSetDot(''); });
  _spUA.on('registered',   () => {
    localStorage.setItem('spExt', ext);
    sessionStorage.setItem('spPass', pass);
    spSetStatus('idle', `🟢 Livre — ramal ${ext}`);
    spSetDot('registered');
    spShowDialpad();
    document.getElementById('sp-form').style.display = 'none';
    document.getElementById('sp-hdr-sub').textContent = `Ramal ${ext}`;
    // Sincroniza com "Meu Ramal"
    document.getElementById('my-ext').value = ext;
    saveMeuRamal(ext);
  });
  _spUA.on('unregistered', () => { spSetStatus('idle','🔴 Não registrado'); spSetDot(''); });
  _spUA.on('registrationFailed', (e) => {
    spSetStatus('offline', `❌ Falha: ${e.cause}`);
    spSetDot('');
    showToast(`❌ Registro SIP falhou: ${e.cause}`, false);
    spShowForm();
  });
  _spUA.on('newRTCSession', (data) => spHandleSession(data));

  _spUA.start();
  spSetStatus('connecting', '🔄 Conectando ao servidor SIP...');
}

// ── Desconectar ───────────────────────────────────────────
function spDisconnect() {
  if (_spSession) { try { _spSession.terminate(); } catch(e){} _spSession = null; }
  if (_spUA)      { try { _spUA.stop(); }           catch(e){} _spUA = null; }
  sessionStorage.removeItem('spPass');
  spSetDot('');
  spShowForm();
  spSetStatus('idle','Desconectado');
  document.getElementById('sp-hdr-sub').textContent = 'Desconectado';
}

// ── Gerencia sessão (entrada e saída) ─────────────────────
function spHandleSession(data) {
  _spSession = data.session;
  _spMuted   = false;
  const isIncoming = data.originator === 'remote';
  const caller     = _spSession.remote_identity?.uri?.user || 'Desconhecido';

  _spSession.on('peerconnection', (d) => {
    d.peerconnection.ontrack = (e) => {
      document.getElementById('sp-audio-remote').srcObject = e.streams[0];
    };
  });

  _spSession.on('accepted', () => {
    spStopRing();
    spStartTimer();
    spSetStatus('incall', `🔴 Em chamada com ${caller}`);
    spSetDot('calling');
    spShowCallControls([
      `<button class="sp-ctrl-btn mute" id="sp-mute-btn" onclick="spToggleMute()" title="Mudo">🎙</button>`,
      `<button class="sp-ctrl-btn hangup" onclick="spHangupCall()" title="Desligar">📵</button>`,
    ]);
  });

  _spSession.on('ended',   () => spEndCall(caller));
  _spSession.on('failed',  () => spEndCall(caller));

  if (isIncoming) {
    spStartRing();
    spSetStatus('incoming', `📲 Chamada de ${caller}`);
    spSetDot('calling');
    spShowCallControls([
      `<button class="sp-ctrl-btn answer" onclick="spAnswerCall()" title="Atender">📞</button>`,
      `<button class="sp-ctrl-btn hangup" onclick="spHangupCall()" title="Rejeitar">📵</button>`,
    ]);
    // Abre o painel automaticamente
    if (!_spPanelOpen) spTogglePanel();
  }
}

function spAnswerCall() {
  if (!_spSession) return;
  _spSession.answer({ mediaConstraints: { audio: true, video: false } });
}

function spHangupCall() {
  if (!_spSession) return;
  try { _spSession.terminate(); } catch(e){}
}

function spEndCall(caller) {
  spStopRing();
  spStopTimer();
  _spSession = null;
  _spMuted   = false;
  spSetStatus('idle', '🟢 Livre — pronto para ligar');
  spSetDot('registered');
  document.getElementById('sp-call-area').style.display = 'none';
  document.getElementById('sp-dialpad').style.display   = 'block';
  document.getElementById('sp-status-bar').style.display = 'block';
  showToast(`📵 Chamada com ${caller} encerrada`, true);
}

function spToggleMute() {
  if (!_spSession) return;
  _spMuted = !_spMuted;
  _spMuted ? _spSession.mute() : _spSession.unmute();
  const btn = document.getElementById('sp-mute-btn');
  if (btn) { btn.classList.toggle('active', _spMuted); btn.textContent = _spMuted ? '🔇' : '🎙'; }
}

// ── Originar chamada pelo softphone ──────────────────────
function spCall(target) {
  if (!_spUA || !_spUA.isRegistered()) { showToast('❌ Softphone não conectado', false); return; }
  const num = target || document.getElementById('sp-dial-display').textContent.replace(/\u200B/g,'').trim();
  if (!num) { showToast('❌ Digite o número para ligar', false); return; }
  const host = window.location.hostname;
  _spUA.call(`sip:${num}@${host}`, {
    mediaConstraints:       { audio: true, video: false },
    pcConfig:               { iceServers: [] },
  });
  document.getElementById('sp-dial-display').textContent = '\u200B';
  spSetStatus('calling', `🔔 Chamando ${num}...`);
  spSetDot('calling');
}

// ── Discador ──────────────────────────────────────────────
function spKey(k) {
  const d = document.getElementById('sp-dial-display');
  const v = d.textContent.replace(/\u200B/g,'');
  d.textContent = v + k;
  if (_spSession && _spSession.isEstablished()) _spSession.sendDTMF(k);
}
function spBackspace() {
  const d = document.getElementById('sp-dial-display');
  const v = d.textContent.replace(/\u200B/g,'');
  d.textContent = v.slice(0,-1) || '\u200B';
}

// ── Timer de chamada ──────────────────────────────────────
function spStartTimer() {
  _spTimerSec = 0;
  clearInterval(_spTimerInt);
  _spTimerInt = setInterval(() => {
    _spTimerSec++;
    const m = Math.floor(_spTimerSec/60), s = _spTimerSec%60;
    const el = document.getElementById('sp-call-timer');
    if (el) el.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  }, 1000);
}
function spStopTimer() { clearInterval(_spTimerInt); }

// ── Ringback ──────────────────────────────────────────────
function spStartRing() {
  try { const a = document.getElementById('sp-audio-ring'); a.currentTime=0; a.play().catch(()=>{}); } catch(e){}
}
function spStopRing() {
  try { const a = document.getElementById('sp-audio-ring'); a.pause(); a.currentTime=0; } catch(e){}
}

// ── Helpers de UI ─────────────────────────────────────────
function spSetStatus(cls, msg) {
  const el = document.getElementById('sp-status-bar');
  el.className = `sp-status-bar ${cls}`;
  el.textContent = msg;
  el.style.display = 'block';
}
function spSetDot(cls) {
  document.getElementById('sp-fab-dot').className = cls;
}
function spShowForm() {
  document.getElementById('sp-form').style.display    = 'block';
  document.getElementById('sp-dialpad').style.display = 'none';
  document.getElementById('sp-call-area').style.display = 'none';
  document.getElementById('sp-status-bar').style.display = 'none';
}
function spShowDialpad() {
  document.getElementById('sp-form').style.display    = 'none';
  document.getElementById('sp-dialpad').style.display = 'block';
  document.getElementById('sp-call-area').style.display = 'none';
  document.getElementById('sp-status-bar').style.display = 'block';
}
function spShowCallControls(btns) {
  document.getElementById('sp-dialpad').style.display   = 'none';
  document.getElementById('sp-call-area').style.display = 'block';
  document.getElementById('sp-call-ctrls').innerHTML    = btns.join('');
  document.getElementById('sp-call-info').textContent   = document.getElementById('sp-status-bar').textContent;
  document.getElementById('sp-call-timer').textContent  = '';
}

// ── Integração com menu de ações ──────────────────────────
// Quando o softphone estiver conectado, o "Ligar" usa o softphone diretamente
const _origDoOriginate = doOriginate;
window.doOriginate = function(toExt) {
  if (_spUA && _spUA.isRegistered()) {
    if (!confirm(`Ligar do softphone para o ramal ${toExt}?`)) return;
    spCall(toExt);
    if (!_spPanelOpen) spTogglePanel();
  } else {
    _origDoOriginate(toExt);
  }
};

// ══════════════════════════════════════════════════════════
// SISTEMA DE AÇÕES — FOP2-like
// ══════════════════════════════════════════════════════════
const ACAO_URL = 'painel_acao.php';
let   _modalCb = null;       // callback do modal de input
let   _popupExt = null;      // extensão atual do popup

// ── Meu Ramal ────────────────────────────────────────────
function saveMeuRamal(v) { localStorage.setItem('meuRamal', v); }
function getMeuRamal()   { return (localStorage.getItem('meuRamal') || '').trim(); }
document.addEventListener('DOMContentLoaded', () => {
  const saved = getMeuRamal();
  if (saved) document.getElementById('my-ext').value = saved;
});

// ── Popup de ações ────────────────────────────────────────
function showActionMenu(event, extJson) {
  event.stopPropagation();
  const ext = JSON.parse(decodeURIComponent(extJson));
  _popupExt = ext;
  const popup  = document.getElementById('action-popup');
  const name   = ext.displayName && ext.displayName !== ext.ext ? `${ext.ext}: ${ext.displayName}` : ext.ext;
  document.getElementById('ap-ext-name').textContent = name;

  const status  = ext.status || 'unregistered';
  const hasCall = ['incall','ringing','onhold'].includes(status);
  const channel = ext.channel || '';
  const btns    = [];

  if (status !== 'unregistered') {
    // Ligar (originar)
    btns.push(`<button class="ap-btn green" onclick="doOriginate('${ext.ext}')">
      <span class="ap-ico">📞</span> Ligar para este ramal</button>`);
  }

  if (hasCall && channel) {
    btns.push(`<div class="ap-sep"></div>`);
    // Espiar
    btns.push(`<button class="ap-btn blue" onclick="doSpy('${ext.ext}','q')">
      <span class="ap-ico">👂</span> Espiar (ouvir em silêncio)</button>`);
    // Sussurrar
    btns.push(`<button class="ap-btn purple" onclick="doSpy('${ext.ext}','qw')">
      <span class="ap-ico">💬</span> Sussurrar (só o agente ouve)</button>`);
    // Intrometer
    btns.push(`<button class="ap-btn amber" onclick="doSpy('${ext.ext}','qBq')">
      <span class="ap-ico">🎙</span> Intrometer (entrar na chamada)</button>`);
    btns.push(`<div class="ap-sep"></div>`);
    // Transferir
    btns.push(`<button class="ap-btn" onclick="doTransfer('${channel}','${ext.ext}')">
      <span class="ap-ico">↔</span> Transferir chamada</button>`);
    // Estacionar
    btns.push(`<button class="ap-btn" onclick="doPark('${channel}')">
      <span class="ap-ico">🅿</span> Estacionar chamada</button>`);
    btns.push(`<div class="ap-sep"></div>`);
    // Desligar
    btns.push(`<button class="ap-btn red" onclick="doHangup('${channel}','${ext.ext}')">
      <span class="ap-ico">📵</span> Desligar chamada</button>`);
  }

  if (btns.length === 0) {
    btns.push(`<div style="color:#666;font-size:12px;padding:6px 10px;">Ramal não registrado — sem ações disponíveis</div>`);
  }

  document.getElementById('ap-buttons').innerHTML = btns.join('');

  // Posicionamento perto do clique
  popup.className = '';
  popup.style.display = 'none';
  const vw = window.innerWidth, vh = window.innerHeight;
  const pw = 220, ph = Math.min(btns.length * 44 + 60, 400);
  let x = event.clientX + 4, y = event.clientY + 4;
  if (x + pw > vw - 10) x = event.clientX - pw - 4;
  if (y + ph > vh - 10) y = event.clientY - ph - 4;
  popup.style.left = x + 'px';
  popup.style.top  = y + 'px';
  popup.style.display = 'block';
  popup.classList.add('show');
}

function hideActionMenu() {
  const 