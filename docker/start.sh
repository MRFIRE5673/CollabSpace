#!/bin/sh
# ── CollabSpace Container Startup ────────────────────────────
# Railway injects $PORT — default to 80 for local Docker
export PORT="${PORT:-80}"

echo "Starting CollabSpace on port $PORT..."

# Replace ${PORT} in nginx config with actual port value
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /tmp/nginx_resolved.conf

# Start PHP-FPM in background
php-fpm -D
echo "PHP-FPM started."

# Start nginx with resolved config in foreground
exec nginx -c /tmp/nginx_resolved.conf -g "daemon off;"
