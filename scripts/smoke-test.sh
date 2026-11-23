#!/usr/bin/env bash
# Teste de fumaça: sobe o sistema no Docker e confere se o essencial responde.
# Uso: ./scripts/smoke-test.sh [URL_BASE]   (padrão: http://localhost:8080)
BASE="${1:-http://localhost:8080}"
FALHAS=0

ok()   { echo "  ✅ $1"; }
erro() { echo "  ❌ $1"; FALHAS=$((FALHAS+1)); }

# confere o código HTTP de uma URL
checa_status() {  # descrição, caminho, status esperado
  local code
  code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE$2")
  [ "$code" = "$3" ] && ok "$1 (HTTP $code)" || erro "$1 (esperado $3, veio $code)"
}

echo "Aguardando o sistema subir em $BASE ..."
for i in $(seq 1 40); do
  curl -s "$BASE/backend/api/listar_servicos.php" | grep -q servicos && break
  sleep 2
done

echo "Rodando testes:"
checa_status "Site público abre"               "/frontend/"            200
checa_status "Tela de login do painel abre"    "/admin/login.php"      200
checa_status "Pasta do banco está protegida"   "/database/schema.sql"  403
checa_status "Helpers protegidos do navegador" "/backend/helpers/funcoes.php" 403

# API + banco: precisa devolver os serviços cadastrados no schema.sql
RESP=$(curl -s "$BASE/backend/api/listar_servicos.php")
echo "$RESP" | grep -q "Volume Brasileiro" \
  && ok "API lê os serviços do MySQL" \
  || erro "API não devolveu os serviços (resposta: $RESP)"

# Sessão/CSRF da API
curl -s "$BASE/backend/api/me.php" | grep -q '"csrf"' \
  && ok "API de sessão devolve token CSRF" \
  || erro "API de sessão (me.php) falhou"

echo
if [ "$FALHAS" -eq 0 ]; then echo "🎉 Todos os testes passaram!"; else echo "💥 $FALHAS teste(s) falharam."; exit 1; fi
