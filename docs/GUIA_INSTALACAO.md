# FreePBX — Painel de Controle: Guia de Instalação
**Versão 1.0 | Abril 2026**

---

## O que é diferente do Issabel?

| Item | Issabel 5 | FreePBX |
|------|-----------|---------|
| Canal SIP padrão | chan_sip | **PJSIP** (Chan_PJSIP) |
| Config SIP | `/etc/asterisk/sip_additional.conf` | `/etc/asterisk/pjsip_additional.conf` |
| Credenciais DB | `/etc/issabelpbx.conf` | `/etc/freepbx.conf` |
| Tabela de ramais | `sip` (keyword=callerid) | **`users`** (extension + name) |
| Sistema de licença | Sim | **Não (removido)** |
| AMI user padrão | admin / Mamutgravador | Você cria no GUI |

---

## PASSO 1 — Criar o Usuário AMI no FreePBX

Este é o passo mais importante. O painel se conecta ao Asterisk via AMI.

1. Acesse o FreePBX no browser: `http://IP_DO_SERVIDOR/admin`
2. Vá em **Admin → Asterisk Manager Users**
3. Clique em **Add Manager**
4. Preencha:
   - **Username:** `painel`
   - **Password:** escolha uma senha forte (ex: `P@inel2024!`)
   - **Permit:** `127.0.0.1/255.255.255.255` (só local) ou o IP do seu computador
5. Em **Authorization Type**, marque as seguintes permissões:
   - ✅ `call` — para hangup e transfer
   - ✅ `reporting` — para listar ramais, troncos e filas
   - ✅ `originate` — para click-to-call, spy, barge
   - ✅ `system` — recomendado para acesso completo
6. Clique em **Submit** e depois em **Apply Config** (botão vermelho)

> ⚠️ Se não aparecer "Asterisk Manager Users" no menu Admin, instale o módulo:
> Admin → Module Admin → procure "Asterisk Manager" → Install

---

## PASSO 2 — Transferir os Arquivos para o Servidor

### Via WinSCP (Windows, recomendado)

1. Abra o WinSCP
2. Conecte ao servidor FreePBX:
   - Host: IP do servidor
   - Protocolo: SFTP
   - Usuário: `root`
   - Senha: senha do root
3. No painel direito (servidor), navegue até `/var/www/html/`
4. Crie uma pasta chamada `painel`
5. Copie todos os arquivos `.php` da pasta `arquivos/` local para `/var/www/html/painel/`

### Via SCP (linha de comando)

```bash
scp arquivos/*.php root@IP_DO_SERVIDOR:/var/www/html/painel/
```

### Via script automático (se tiver acesso SSH direto)

```bash
# No servidor, via SSH:
mkdir -p /var/www/html/painel
# Depois use o instalar_freepbx.sh
```

---

## PASSO 3 — Editar painel_config.php

Abra o arquivo `/var/www/html/painel/painel_config.php` no servidor e ajuste:

```php
define('AMI_USER', 'painel');          // usuário que criou no Passo 1
define('AMI_PASS', 'SuaSenha');        // senha que definiu no Passo 1
```

O restante (banco de dados, driver SIP) é detectado automaticamente.

Para editar via SSH:
```bash
nano /var/www/html/painel/painel_config.php
```

---

## PASSO 4 — Permissões dos Arquivos

Execute via SSH no servidor:

```bash
chown -R asterisk:asterisk /var/www/html/painel/
chmod 755 /var/www/html/painel/
chmod 644 /var/www/html/painel/*.php
```

Se der erro com `asterisk`, tente com `apache` ou `www-data` conforme seu sistema.

---

## PASSO 5 — Testar a API

Acesse no browser:
```
http://IP_DO_SERVIDOR/painel/painel_api.php
```

Você deve ver um JSON como:
```json
{
  "ok": true,
  "driver": "pjsip",
  "extensions": [...],
  "trunks": [...],
  "queues": [...],
  "timestamp": "14:32:05"
}
```

Se aparecer `"ok": false`, leia o campo `"error"` — geralmente é problema de credencial AMI.

---

## PASSO 6 — Acessar o Painel

```
http://IP_DO_SERVIDOR/painel/painel_controle.php
```

---

## PASSO 7 — Softphone WebRTC (opcional)

Para usar o softphone integrado, o Asterisk precisa estar com WebSocket habilitado.

### Verificar se HTTP está habilitado:
```bash
grep -i "enabled" /etc/asterisk/http.conf
```

### Se não estiver, adicione em `/etc/asterisk/http_general_custom.conf`:
```ini
[general]
enabled=yes
bindaddr=0.0.0.0
bindport=8088
```

### Compilar JsSIP localmente (recomendado):
```bash
# Instalar Node.js (se não tiver)
curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
dnf install nodejs -y   # ou: yum install nodejs -y

# Instalar e compilar JsSIP
npm install jssip
npm install -g esbuild
esbuild node_modules/jssip/lib/JsSIP.js \
  --bundle --minify --global-name=JsSIP \
  --outfile=/var/www/html/painel/jssip.min.js \
  --platform=browser --format=iife

# Recarregar Asterisk
asterisk -rx "core reload"
```

### Configurar ramal para WebRTC (no FreePBX GUI):
1. Applications → Extensions → editar o ramal
2. Aba **Advanced**: Transport = `WSS/WS`
3. Aba **Advanced**: Enable `AVPF` e `DTLS-SRTP` se usar WSS
4. Apply Config

---

## Problemas Comuns

### "Não foi possível conectar ao AMI"
- Verifique se o usuário AMI foi criado e o Apply Config foi feito
- Confirme que `AMI_USER` e `AMI_PASS` em `painel_config.php` estão corretos
- Teste: `telnet 127.0.0.1 5038` (deve aparecer banner do Asterisk)

### "Ramais não aparecem"
- FreePBX com PJSIP: o `painel_api.php` usa `PJSIPShowEndpoints` automaticamente
- Se usar chan_sip legado, mude em `painel_config.php`: `define('SIP_DRIVER', 'chansip')`

### "Nomes dos ramais não aparecem, só números"
- Os nomes vêm da tabela `users` do banco FreePBX
- Verifique se os ramais têm nome configurado em: Applications → Extensions → campo Display Name
- A API tenta `/etc/freepbx.conf` para ler as credenciais do banco automaticamente

### "Erro 403 ao acessar o painel"
- Verifique o SELinux: `setenforce 0` (temporário) ou configure contexto correto
- Verifique permissões: `ls -la /var/www/html/painel/`

### "Softphone não conecta"
- Verifique se HTTP do Asterisk está habilitado (porta 8088)
- No painel, clique em ⚙ do softphone e confirme o IP do servidor
- Use `ws://` (não `wss://`) para conexão sem certificado SSL

---

## Diferença PJSIP vs chan_sip no painel

O painel detecta automaticamente qual driver está em uso. A principal diferença visível é que:

- Com **PJSIP**: o status dos ramais vem do campo `DeviceState` do Asterisk (mais preciso)
- Com **chan_sip**: o status vem do campo `Status` do SIPpeers (OK/Unreachable/Unknown)

Se o seu FreePBX usa ambos (migração híbrida), o painel tenta PJSIP primeiro e exibe os dois grupos.

---

## Arquivos do Projeto

| Arquivo | Função |
|---------|--------|
| `painel_controle.php` | Interface HTML/CSS/JS — tela principal |
| `painel_api.php` | Backend — consulta AMI, retorna JSON |
| `painel_acao.php` | Backend — executa ações (hangup, transfer, spy...) |
| `painel_config.php` | ⚙️ Configurações — **edite este arquivo** |
| `instalar_freepbx.sh` | Script de instalação automática (Linux) |
| `jssip.min.js` | Biblioteca softphone WebRTC (compilar separado) |
