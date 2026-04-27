#!/bin/bash
# =============================================================
#  FreePBX - Painel de Controle em Tempo Real
#  Script de instalação automática
#  Execute como root no servidor FreePBX:
#    chmod +x instalar_freepbx.sh && ./instalar_freepbx.sh
# =============================================================

set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'; BOLD='\033[1m'

echo -e "${BLUE}${BOLD}"
echo "  ╔══════════════════════════════════════════════╗"
echo "  ║   FreePBX - Painel de Controle v1.0          ║"
echo "  ║   Script de instalação automática            ║"
echo "  ╚══════════════════════════════════════════════╝"
echo -e "${NC}"

# ── Verificações ──────────────────────────────────────────────
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}✗ Execute como root: sudo ./instalar_freepbx.sh${NC}"
    exit 1
fi

if [ ! -f /etc/freepbx.conf ] && [ ! -f /etc/schmooze/pbx.conf ]; then
    echo -e "${YELLOW}⚠  /etc/freepbx.conf não encontrado. Verifique se o FreePBX está instalado.${NC}"
fi

WEBROOT="/var/www/html"
PAINEL_DIR="${WEBROOT}/painel"

echo -e "${BLUE}▶ Destino da instalação: ${PAINEL_DIR}${NC}"

# ── Criar diretório ───────────────────────────────────────────
mkdir -p "${PAINEL_DIR}"

# ── Copiar arquivos PHP ───────────────────────────────────────
echo -e "${BLUE}▶ Copiando arquivos...${NC}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

for f in painel_controle.php painel_api.php painel_acao.php painel_config.php; do
    if [ -f "${SCRIPT_DIR}/${f}" ]; then
        cp "${SCRIPT_DIR}/${f}" "${PAINEL_DIR}/${f}"
        echo -e "  ${GREEN}✓${NC} ${f}"
    else
        echo -e "  ${RED}✗ Não encontrado: ${f}${NC}"
    fi
done

# ── Permissões ────────────────────────────────────────────────
echo -e "${BLUE}▶ Configurando permissões...${NC}"
chown -R asterisk:asterisk "${PAINEL_DIR}" 2>/dev/null || \
chown -R apache:apache     "${PAINEL_DIR}" 2>/dev/null || \
chown -R www-data:www-data "${PAINEL_DIR}" 2>/dev/null || true
chmod 755 "${PAINEL_DIR}"
chmod 644 "${PAINEL_DIR}"/*.php
echo -e "  ${GREEN}✓ Permissões configuradas${NC}"

# ── JsSIP (softphone WebRTC) ──────────────────────────────────
echo -e "${BLUE}▶ Verificando JsSIP...${NC}"
if [ -f "${WEBROOT}/jssip.min.js" ]; then
    cp "${WEBROOT}/jssip.min.js" "${PAINEL_DIR}/jssip.min.js"
    echo -e "  ${GREEN}✓ jssip.min.js copiado de ${WEBROOT}${NC}"
elif command -v npm &>/dev/null; then
    echo -e "  ${YELLOW}▶ Compilando JsSIP via npm/esbuild...${NC}"
    cd /tmp
    npm install jssip --no-save --silent 2>/dev/null || true
    if [ -d /tmp/node_modules/jssip ]; then
        npm install -g esbuild --silent 2>/dev/null || true
        esbuild /tmp/node_modules/jssip/lib/JsSIP.js \
            --bundle --minify --global-name=JsSIP \
            --outfile="${PAINEL_DIR}/jssip.min.js" \
            --platform=browser --format=iife 2>/dev/null && \
        echo -e "  ${GREEN}✓ JsSIP compilado ($(du -sh "${PAINEL_DIR}/jssip.min.js" | cut -f1))${NC}" || \
        echo -e "  ${YELLOW}⚠ Compilação falhou — softphone usará CDN${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠ npm não encontrado — softphone usará CDN (precisa de internet no servidor)${NC}"
fi

# ── Verificar WebSocket no Asterisk ──────────────────────────
echo -e "${BLUE}▶ Verificando suporte a WebSocket no Asterisk...${NC}"
WS_CONF=""
for f in /etc/asterisk/http.conf /etc/asterisk/http_general_custom.conf; do
    [ -f "$f" ] && WS_CONF="$f" && break
done

if [ -n "$WS_CONF" ]; then
    if grep -q "^enabled\s*=\s*yes" "$WS_CONF" 2>/dev/null; then
        echo -e "  ${GREEN}✓ HTTP Asterisk habilitado em ${WS_CONF}${NC}"
    else
        echo -e "  ${YELLOW}⚠ HTTP Asterisk pode estar desabilitado em ${WS_CONF}${NC}"
        echo -e "  ${YELLOW}  Para softphone WebRTC, certifique-se de que 'enabled=yes' está configurado${NC}"
    fi
fi

# ── Testar conexão AMI ────────────────────────────────────────
echo -e "${BLUE}▶ Testando conexão AMI...${NC}"
if command -v timeout &>/dev/null; then
    AMI_RESP=$(echo -e "Action: Login\r\nUsername: admin\r\nSecret: admin\r\n\r\nAction: Logoff\r\n\r\n" | \
        timeout 3 nc -q1 127.0.0.1 5038 2>/dev/null | head -5 || true)
    if echo "$AMI_RESP" | grep -q "Asterisk"; then
        echo -e "  ${GREEN}✓ AMI acessível na porta 5038${NC}"
    else
        echo -e "  ${YELLOW}⚠ AMI pode estar inacessível. Verifique as credenciais em painel_config.php${NC}"
    fi
fi

# ── Resumo ────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}╔══════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}${BOLD}║  ✅  Instalação concluída!                    ║${NC}"
echo -e "${GREEN}${BOLD}╚══════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BOLD}Próximos passos:${NC}"
echo ""
echo -e "  1. ${YELLOW}Criar usuário AMI no FreePBX:${NC}"
echo -e "     Admin → Asterisk Manager Users → Add Manager"
echo -e "     User: painel | Password: SuaSenhaAqui"
echo -e "     Permissões: call, reporting, originate (ver guia)"
echo ""
echo -e "  2. ${YELLOW}Editar painel_config.php com suas credenciais:${NC}"
echo -e "     nano ${PAINEL_DIR}/painel_config.php"
echo ""
echo -e "  3. ${YELLOW}Acessar o painel:${NC}"
echo -e "     http://IP_DO_SERVIDOR/painel/painel_controle.php"
echo ""
echo -e "  4. ${YELLOW}Teste da API:${NC}"
echo -e "     http://IP_DO_SERVIDOR/painel/painel_api.php"
echo ""
