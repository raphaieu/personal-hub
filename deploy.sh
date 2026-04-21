#!/bin/bash
set -e

cd /home/deploy/raphael-hub

echo "--- Git pull ---"
git checkout -- .
git pull origin main

echo "--- Horizon terminate ---"
docker compose exec -T horizon php artisan horizon:terminate || true
sleep 3

echo "--- Rebuild imagem ---"
docker compose build app

echo "--- Recrear containers ---"
docker compose up -d --force-recreate --remove-orphans
sleep 15

echo "--- Composer ---"
docker compose exec -T app composer install \
  --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "--- Build assets ---"
docker compose exec -T app sh -c "npm ci && npm run build"

echo "--- Permissões ---"
mkdir -p storage/app/public storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "--- Migrations ---"
docker compose exec -T app php artisan migrate --force

echo "--- Cache Laravel ---"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan horizon:publish

echo "--- Health check ---"
curl -sf http://127.0.0.1:8082/up && echo "✓ App OK" || echo "⚠ App não respondeu"

docker image prune -f || true
echo "==============================="
echo "Deploy concluído!"
echo "https://api.raphael-martins.com"
echo "==============================="