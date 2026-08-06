#!/bin/sh
# ── CollabSpace Container Startup ────────────────────────────
export PORT="${PORT:-80}"
echo "Starting CollabSpace on port $PORT..."

# Write resolved nginx config to /etc/nginx/ so includes resolve correctly
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Start PHP-FPM (nodaemonize = run in bg via &)
php-fpm --nodaemonize &
FPM_PID=$!
echo "PHP-FPM started (PID $FPM_PID)"

# Wait until PHP-FPM is listening on port 9000
echo "Waiting for PHP-FPM to be ready..."
for i in $(seq 1 20); do
    if nc -z 127.0.0.1 9000 2>/dev/null; then
        echo "PHP-FPM ready on port 9000."
        break
    fi
    sleep 0.5
done

# Start nginx in foreground
echo "Starting nginx..."
exec nginx -g "daemon off;"
