#!/bin/bash
set -Eeuo pipefail
trap 'echo "❌ Deploy falhou na linha $LINENO"' ERR

cd /home/deploy/raphael-hub

echo "--- Git sync ---"
git fetch origin main
LOCAL_COMMIT=$(git rev-parse HEAD)
REMOTE_COMMIT=$(git rev-parse origin/main)

if [ "$LOCAL_COMMIT" = "$REMOTE_COMMIT" ]; then
  echo "Nenhuma mudança nova em origin/main"
  exit 0
fi

git checkout -- .
git pull origin main

PREV_COMMIT=$(git rev-parse HEAD@{1} 2>/dev/null || echo "")
CURR_COMMIT=$(git rev-parse HEAD)

if [ -n "$PREV_COMMIT" ]; then
  CHANGED=$(git diff --name-only "$PREV_COMMIT" "$CURR_COMMIT")
else
  CHANGED=$(git diff-tree --no-commit-id --name-only -r "$CURR_COMMIT" 2>/dev/null || true)
fi

echo "--- Arquivos alterados ---"
echo "$CHANGED"

APP_SERVICES="app nginx postgres redis horizon queue scheduler minio evolution-postgres evolution-redis evolution"
BASE_APP_SERVICES="app nginx postgres redis horizon queue scheduler"
INFRA_SERVICES="nginx postgres redis minio evolution-postgres evolution-redis evolution"
PLAYWRIGHT_SERVICE="playwright"

NEEDS_APP_BUILD=false
NEEDS_PLAYWRIGHT_BUILD=false
NEEDS_INFRA_REFRESH=false
NEEDS_FRONT_BUILD=false
NEEDS_COMPOSER_INSTALL=false
NEEDS_NPM_INSTALL=false
NEEDS_MIGRATION=false
NEEDS_ENV_CACHE_REFRESH=true

# -------------------------------------------------------------------
# Classificação das mudanças
# -------------------------------------------------------------------

if echo "$CHANGED" | grep -qE '(^|/)(Dockerfile)$|composer\.(json|lock)$'; then
  NEEDS_APP_BUILD=true
  NEEDS_COMPOSER_INSTALL=true
fi

if echo "$CHANGED" | grep -qE '(^|/)(Dockerfile\.playwright)$|^playwright/'; then
  NEEDS_PLAYWRIGHT_BUILD=true
fi

if echo "$CHANGED" | grep -qE '^docker-compose\.yml$|^docker/|^deploy\.sh$'; then
  NEEDS_INFRA_REFRESH=true
fi

if echo "$CHANGED" | grep -qE '^resources/|^public/|^vite\.config|^package(-lock)?\.json$'; then
  NEEDS_FRONT_BUILD=true
  NEEDS_NPM_INSTALL=true
fi

if echo "$CHANGED" | grep -qE '^database/migrations/'; then
  NEEDS_MIGRATION=true
fi

if echo "$CHANGED" | grep -qE '^config/|^routes/|^bootstrap/|^app/|^composer\.(json|lock)$|^\.env\.example$'; then
  NEEDS_ENV_CACHE_REFRESH=true
fi

# -------------------------------------------------------------------
# Encerramento gracioso
# -------------------------------------------------------------------

echo "--- Horizon terminate ---"
docker compose exec -T horizon php artisan horizon:terminate || true
sleep 3

# -------------------------------------------------------------------
# Garantir diretórios críticos
# -------------------------------------------------------------------

echo "--- Permissões de diretórios Laravel ---"
mkdir -p storage bootstrap/cache
chown -R deploy:deploy storage bootstrap/cache || true

# -------------------------------------------------------------------
# Rebuild / refresh de infraestrutura
# -------------------------------------------------------------------

if [ "$NEEDS_APP_BUILD" = true ]; then
  echo "--- Build da imagem do app ---"
  docker compose build app
fi

if [ "$NEEDS_PLAYWRIGHT_BUILD" = true ]; then
  echo "--- Build do Playwright ---"
  docker compose build "$PLAYWRIGHT_SERVICE"
fi

if [ "$NEEDS_INFRA_REFRESH" = true ] || [ "$NEEDS_APP_BUILD" = true ] || [ "$NEEDS_PLAYWRIGHT_BUILD" = true ]; then
  echo "--- Subindo / recriando stack necessária ---"
  docker compose up -d --force-recreate --remove-orphans \
    $APP_SERVICES $PLAYWRIGHT_SERVICE
  sleep 15
else
  echo "--- Restart leve dos serviços da aplicação ---"
  docker compose restart $BASE_APP_SERVICES
  sleep 5
fi

# -------------------------------------------------------------------
# Dependências do app
# -------------------------------------------------------------------

if [ "$NEEDS_COMPOSER_INSTALL" = true ]; then
  echo "--- Composer install ---"
  docker compose exec -T app composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev
fi

if [ "$NEEDS_NPM_INSTALL" = true ]; then
  echo "--- NPM install / build assets ---"
  docker compose exec -T app sh -c "npm ci && npm run build"
elif [ "$NEEDS_FRONT_BUILD" = true ]; then
  echo "--- Build assets ---"
  docker compose exec -T app sh -c "npm run build"
fi

# -------------------------------------------------------------------
# Dependências do Playwright
# -------------------------------------------------------------------

if [ "$NEEDS_PLAYWRIGHT_BUILD" = true ]; then
  echo "--- Validando container Playwright ---"
  docker compose up -d "$PLAYWRIGHT_SERVICE"
  sleep 5
fi

# -------------------------------------------------------------------
# Migrations
# -------------------------------------------------------------------

if [ "$NEEDS_MIGRATION" = true ]; then
  echo "--- Migrations ---"
  docker compose exec -T app php artisan migrate --force
fi

# -------------------------------------------------------------------
# Cache Laravel
# -------------------------------------------------------------------

echo "--- Limpando caches Laravel ---"
docker compose exec -T app php artisan optimize:clear

echo "--- Recriando caches Laravel ---"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

# -------------------------------------------------------------------
# Health checks
# -------------------------------------------------------------------

echo "--- Health checks ---"

if curl -sf http://127.0.0.1:8082/up >/dev/null; then
  echo "✓ App OK"
else
  echo "⚠ App não respondeu em /up"
fi

if curl -sf http://127.0.0.1:19000/minio/health/live >/dev/null; then
  echo "✓ MinIO OK"
else
  echo "⚠ MinIO não respondeu"
fi

if curl -sf http://127.0.0.1:18081 >/dev/null; then
  echo "✓ Evolution OK"
else
  echo "⚠ Evolution não respondeu"
fi

if docker compose exec -T playwright sh -c "wget -q -O - http://127.0.0.1:3001/health | grep -q ok"; then
  echo "✓ Playwright OK"
else
  echo "⚠ Playwright não respondeu"
fi

# -------------------------------------------------------------------
# Limpeza
# -------------------------------------------------------------------

echo "--- Docker prune ---"
docker image prune -f || true
docker builder prune -af || true

echo "Deploy concluído! https://api.raphael-martins.com"