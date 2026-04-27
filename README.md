# FreePBX — Painel de Controle em Tempo Real

Painel web para monitoramento e controle de centrais telefônicas **FreePBX** (Asterisk),
com suporte completo a **PJSIP** (Chan_PJSIP) — o driver padrão das versões atuais.

## Funcionalidades

- **Monitoramento em Tempo Real** — Ramais, troncos, canais ativos e filas (atualização a cada 3s)
- **Ações de Supervisor** — Desligar, transferir, espionar, sussurrar, barge, estacionar chamadas
- **Click-to-Call** — Originar chamadas diretamente pelo painel
- **Filas de Atendimento** — Status de agentes com pausar/retomar
- **Editor de Nomes** — Configure nomes dos ramais sem restrição de caracteres
- **Softphone WebRTC** — JsSIP integrado para ligar pelo navegador
- **Instalação Automática** — Script bash `instalar_freepbx.sh`

## Tecnologias

- PHP 7.4+ (sem frameworks)
- JavaScript ES6 (sem frameworks)
- AMI — Asterisk Manager Interface (porta 5038)
- PJSIP / Chan_PJSIP (FreePBX padrão)
- JsSIP / WebRTC (softphone)
- MySQL (leitura de nomes do banco FreePBX)

## Arquivos

| Arquivo | Função |
|---------|--------|
| `painel_controle.php` | Interface principal |
| `painel_api.php` | Backend — consulta AMI |
| `painel_acao.php` | Backend — executa ações |
| `painel_config.php` | ⚙️ Configurações (editar este) |
| `painel_nomes.php` | API de nomes personalizados |
| `instalar_freepbx.sh` | Script de instalação Linux |

## Instalação Rápida

```bash
# 1. Copiar arquivos para o servidor
scp *.php instalar_freepbx.sh root@IP_SERVIDOR:/var/www/html/painel/

# 2. No servidor:
cd /var/www/html/painel
chmod +x instalar_freepbx.sh && ./instalar_freepbx.sh

# 3. Editar configurações
nano painel_config.php   # definir AMI_USER, AMI_PASS, DB_PASS
```

## Acesso

```
http://IP_DO_SERVIDOR/painel/painel_controle.php
http://IP_DO_SERVIDOR/painel/painel_api.php      (teste da API)
```

## Versão

v1.0 — 27/04/2026

---
*Desenvolvido com auxílio de IA (Claude / Anthropic)*
