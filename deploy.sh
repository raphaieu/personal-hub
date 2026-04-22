#!/bin/bash
set -e
cd /home/deploy/raphael-hub

echo "--- Git pull ---"
git checkout -- .
git pull origin main

# Detectar o que mudou
CHANGED=$(git diff HEAD@{1} HEAD --name-only 2>/dev/null || echo "")

echo "--- Horizon terminate ---"
docker compose exec -T horizon php artisan horizon:terminate || true
sleep 3

# Só rebuilda se mudou Dockerfile ou dependências
if echo "$CHANGED" | grep -qE "Dockerfile|composer\.(json|lock)|package(-lock)?\.json"; then
    echo "--- Rebuild imagem ---"
    docker compose build app
    docker compose up -d --force-recreate --remove-orphans \
        app nginx postgres redis horizon queue scheduler
    sleep 15

    echo "--- Composer ---"
    docker compose exec -T app composer install \
        --no-interaction --prefer-dist --optimize-autoloader --no-dev

    echo "--- Build assets ---"
    docker compose exec -T app sh -c "npm ci && npm run build"
else
    echo "--- Apenas restart do app ---"
    docker compose restart app horizon queue scheduler
    sleep 5
fi

# Migrations só se tiver migration nova
if echo "$CHANGED" | grep -q "database/migrations"; then
    echo "--- Migrations ---"
    docker compose exec -T app php artisan migrate --force
fi

echo "--- Cache Laravel ---"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

echo "--- Health check ---"
curl -sf http://127.0.0.1:8082/up && echo "✓ App OK" || echo "⚠ App não respondeu"

docker image prune -f || true
echo "Deploy concluído! https://api.raphael-martins.com"
