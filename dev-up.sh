#!/usr/bin/env bash
# -------------------------------------------------------
# dev-up.sh — sobe o Raphael Hub em dev local (nativo)
#
# Uso:
#   ./dev-up.sh              → sobe só o artisan serve (porta 8010)
#   ./dev-up.sh --queue      → sobe serve + queue worker
#   ./dev-up.sh --horizon    → sobe serve + horizon
# -------------------------------------------------------

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_DIR="/tmp/raphael-hub-dev"
mkdir -p "$PID_DIR"

HUB_PORT=8010

# --- helpers ---
die() { echo "❌ $1" >&2; exit 1; }
ok()  { echo "✅ $1"; }

# --- verifica infra compartilhada ---
check_infra() {
    if ! docker ps --format '{{.Names}}' | grep -q "infra_postgres"; then
        die "infra_postgres não está rodando. Suba a infra: cd ~/projects/infra && docker compose up -d"
    fi
    if ! docker ps --format '{{.Names}}' | grep -q "infra_redis"; then
        die "infra_redis não está rodando. Suba a infra: cd ~/projects/infra && docker compose up -d"
    fi
    ok "Infra compartilhada (Postgres 5433, Redis 6379) está no ar"
}

# --- verifica banco ---
check_db() {
    if ! PGPASSWORD=secret psql -h 127.0.0.1 -p 5433 -U raphael_app -d raphael -c "SELECT 1" > /dev/null 2>&1; then
        die "Banco 'raphael' não responde. Rode: php artisan migrate"
    fi
    ok "Banco raphael conectado"
}

# --- status ---
show_status() {
    echo ""
    echo "─────────────────────────────────────────────"
    echo "  Raphael Hub — Dev Status"
    echo "─────────────────────────────────────────────"
    if [ -f "$PID_DIR/serve.pid" ] && kill -0 "$(cat "$PID_DIR/serve.pid")" 2>/dev/null; then
        echo "  🟢 Serve (API)    → http://hub.test:$HUB_PORT  (PID: $(cat $PID_DIR/serve.pid))"
    else
        echo "  🔴 Serve (API)    → parado"
    fi
    if [ -f "$PID_DIR/queue.pid" ] && kill -0 "$(cat "$PID_DIR/queue.pid")" 2>/dev/null; then
        echo "  🟢 Queue worker   → rodando (PID: $(cat $PID_DIR/queue.pid))"
    else
        echo "  🔴 Queue worker   → parado"
    fi
    if [ -f "$PID_DIR/horizon.pid" ] && kill -0 "$(cat "$PID_DIR/horizon.pid")" 2>/dev/null; then
        echo "  🟢 Horizon        → rodando (PID: $(cat $PID_DIR/horizon.pid))"
    else
        echo "  🔴 Horizon        → parado"
    fi
    echo "─────────────────────────────────────────────"
    echo "  Mailpit          → http://localhost:8025"
    echo "  Postgres (infra) → 127.0.0.1:5433"
    echo "  Redis (infra)    → 127.0.0.1:6379"
    echo "─────────────────────────────────────────────"
    echo ""
}

# --- start ---
start_serve() {
    if [ -f "$PID_DIR/serve.pid" ] && kill -0 "$(cat "$PID_DIR/serve.pid")" 2>/dev/null; then
        ok "Serve já rodando (PID: $(cat $PID_DIR/serve.pid))"
        return
    fi
    cd "$SCRIPT_DIR"
    php artisan serve --host=0.0.0.0 --port="$HUB_PORT" > "$PID_DIR/serve.log" 2>&1 &
    echo $! > "$PID_DIR/serve.pid"
    sleep 1
    if curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$HUB_PORT/" | grep -q "200"; then
        ok "Serve subiu → http://hub.test (proxy porta $HUB_PORT)"
    else
        echo "⚠️  Serve subiu mas não respondeu 200 ainda (log: $PID_DIR/serve.log)"
    fi
}

start_queue() {
    if [ -f "$PID_DIR/queue.pid" ] && kill -0 "$(cat "$PID_DIR/queue.pid")" 2>/dev/null; then
        ok "Queue já rodando (PID: $(cat $PID_DIR/queue.pid))"
        return
    fi
    cd "$SCRIPT_DIR"
    php artisan queue:work redis --sleep=3 --tries=3 --timeout=300 --queue=default,notifications,ai,media > "$PID_DIR/queue.log" 2>&1 &
    echo $! > "$PID_DIR/queue.pid"
    ok "Queue worker subiu (fila default,notifications,ai,media)"
}

start_horizon() {
    if [ -f "$PID_DIR/horizon.pid" ] && kill -0 "$(cat "$PID_DIR/horizon.pid")" 2>/dev/null; then
        ok "Horizon já rodando (PID: $(cat $PID_DIR/horizon.pid))"
        return
    fi
    cd "$SCRIPT_DIR"
    php artisan horizon > "$PID_DIR/horizon.log" 2>&1 &
    echo $! > "$PID_DIR/horizon.pid"
    ok "Horizon subiu → http://hub.test/horizon"
}

# --- stop ---
stop_all() {
    for svc in serve queue horizon; do
        pidfile="$PID_DIR/$svc.pid"
        if [ -f "$pidfile" ]; then
            pid=$(cat "$pidfile")
            if kill -0 "$pid" 2>/dev/null; then
                kill "$pid" 2>/dev/null && ok "$svc parado (PID $pid)"
            fi
            rm -f "$pidfile"
        fi
    done
}

# --- CLI ---
case "${1:-}" in
    --stop)
        stop_all
        exit 0
        ;;
    --status)
        show_status
        exit 0
        ;;
    --queue)
        check_infra; check_db; start_serve; start_queue; show_status
        ;;
    --horizon)
        check_infra; check_db; start_serve; start_horizon; show_status
        ;;
    *)
        check_infra; check_db; start_serve; show_status
        ;;
esac
