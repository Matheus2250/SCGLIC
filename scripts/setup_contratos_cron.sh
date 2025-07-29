#!/bin/bash

# Script de configuração automática do cron para sincronização de contratos
# Sistema CGLIC - Ministério da Saúde
# 
# Este script configura a sincronização automática diária dos contratos via API Comprasnet

echo "=== Setup do Cron de Sincronização de Contratos ==="
echo ""

# Verificar se o usuário tem permissões adequadas
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Este script deve ser executado como root para configurar o cron"
    echo "   Execute: sudo $0"
    exit 1
fi

# Definir variáveis
PROJECT_PATH="/mnt/c/xampp/htdocs/sistema_licitacao"
PHP_PATH="/usr/bin/php"
CRON_USER="www-data"
LOG_PATH="/var/log/contratos_sync.log"

# Verificar se o PHP está instalado
if ! command -v php &> /dev/null; then
    echo "❌ PHP não encontrado. Instale o PHP primeiro."
    exit 1
fi

echo "✅ PHP encontrado: $(php --version | head -n 1)"

# Verificar se o projeto existe
if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Projeto não encontrado em: $PROJECT_PATH"
    echo "   Ajuste a variável PROJECT_PATH no script"
    exit 1
fi

echo "✅ Projeto encontrado em: $PROJECT_PATH"

# Verificar se o arquivo de sincronização existe
SYNC_SCRIPT="$PROJECT_PATH/api/contratos_sync.php"
if [ ! -f "$SYNC_SCRIPT" ]; then
    echo "❌ Script de sincronização não encontrado: $SYNC_SCRIPT"
    exit 1
fi

echo "✅ Script de sincronização encontrado"

# Criar diretório de logs se não existir
LOG_DIR=$(dirname "$LOG_PATH")
if [ ! -d "$LOG_DIR" ]; then
    mkdir -p "$LOG_DIR"
    echo "✅ Diretório de logs criado: $LOG_DIR"
fi

# Configurar permissões do arquivo de log
touch "$LOG_PATH"
chown $CRON_USER:$CRON_USER "$LOG_PATH"
chmod 664 "$LOG_PATH"

echo "✅ Arquivo de log configurado: $LOG_PATH"

# Criar entry do cron
CRON_ENTRY="# Sincronização diária de contratos - Sistema CGLIC
0 2 * * * $PHP_PATH $SYNC_SCRIPT --tipo=incremental >> $LOG_PATH 2>&1
0 6 * * 0 $PHP_PATH $SYNC_SCRIPT --tipo=completa >> $LOG_PATH 2>&1"

# Verificar se já existe uma entrada similar
if crontab -u $CRON_USER -l 2>/dev/null | grep -q "contratos_sync.php"; then
    echo "⚠️  Já existe uma entrada de cron para sincronização de contratos"
    echo "   Deseja substituir? (s/N): "
    read -r RESPONSE
    if [[ "$RESPONSE" =~ ^[Ss]$ ]]; then
        # Remover entradas existentes
        crontab -u $CRON_USER -l 2>/dev/null | grep -v "contratos_sync.php" | grep -v "Sistema CGLIC" | crontab -u $CRON_USER -
        echo "🔄 Entradas antigas removidas"
    else
        echo "❌ Cancelado pelo usuário"
        exit 0
    fi
fi

# Adicionar nova entrada ao cron
(crontab -u $CRON_USER -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -u $CRON_USER -

if [ $? -eq 0 ]; then
    echo "✅ Cron configurado com sucesso!"
    echo ""
    echo "📋 Configuração aplicada:"
    echo "   • Sincronização incremental: Todos os dias às 02:00"
    echo "   • Sincronização completa: Domingos às 06:00"
    echo "   • Usuario: $CRON_USER"
    echo "   • Logs em: $LOG_PATH"
    echo ""
    echo "🔍 Para verificar as entradas do cron:"
    echo "   sudo crontab -u $CRON_USER -l"
    echo ""
    echo "📊 Para monitorar os logs:"
    echo "   tail -f $LOG_PATH"
    echo ""
    echo "⚠️  IMPORTANTE:"
    echo "   • Configure as credenciais da API Comprasnet no sistema web"
    echo "   • Teste a sincronização manual antes de depender do cron"
    echo "   • Monitore os logs regularmente para detectar problemas"
else
    echo "❌ Erro ao configurar o cron"
    exit 1
fi

# Mostrar status do cron
echo ""
echo "📈 Status atual do cron:"
systemctl is-active cron &>/dev/null && echo "✅ Serviço cron está ativo" || echo "❌ Serviço cron está inativo"

# Testar comando de sincronização
echo ""
echo "🧪 Testando comando de sincronização..."
if sudo -u $CRON_USER $PHP_PATH $SYNC_SCRIPT --tipo=incremental --test 2>/dev/null; then
    echo "✅ Comando de sincronização funciona corretamente"
else
    echo "⚠️  Teste do comando falhou - verifique configurações"
    echo "   Comando testado: sudo -u $CRON_USER $PHP_PATH $SYNC_SCRIPT --tipo=incremental"
fi

echo ""
echo "🎉 Setup concluído com sucesso!"
echo ""
echo "📝 Próximos passos:"
echo "   1. Acesse o sistema web em: http://localhost/sistema_licitacao"
echo "   2. Vá ao módulo Contratos"
echo "   3. Configure as credenciais da API Comprasnet"
echo "   4. Execute uma sincronização manual para testar"
echo "   5. Monitore os logs para verificar a sincronização automática"
echo ""