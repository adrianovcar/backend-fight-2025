#!/bin/sh -e
cd /var/www/html

# bootstrap simples
[ -f .env ] || cp .env.example .env
php artisan key:generate --force || true
php artisan optimize:clear || true

# watcher externo: quando houver mudança em app/, routes/, etc., manda reload
if command -v inotifywait >/dev/null 2>&1; then
  (
    while true; do
      inotifywait -qr -e modify,create,delete,move \
        app bootstrap config database resources routes || true
      php artisan octane:reload || true
    done
  ) &
fi

# inicia Octane (sem --watch)
exec php artisan octane:start \
  --server=swoole \
  --host=0.0.0.0 \
  --port=8000 \
  --workers=1 \
  --task-workers=0 \
  --max-requests=0
