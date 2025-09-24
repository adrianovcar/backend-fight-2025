#!/bin/sh -e
cd /var/www/html

# bootstrap
[ -f .env ] || cp .env.example .env
php artisan key:generate --force || true
php artisan optimize:clear || true

WATCH_PATHS="app bootstrap config database resources routes"

# ── Watcher A: inotify (se existir)
if command -v inotifywait >/dev/null 2>&1; then
  (
    echo "[dev] watcher: inotify ativo"
    inotifywait -m -r -q -e modify,create,delete,move $WATCH_PATHS \
    | while read -r DIR EVENT FILE; do
        echo "[dev] change: ${DIR}${FILE} (${EVENT}) → reload"
        php artisan octane:reload || true
      done
  ) &
fi

# ── Watcher B: polling (sempre ligado; cobre caso o inotify não dispare em bind-mount)
(
  echo "[dev] watcher: polling ativo"
  LAST=""
  while true; do
    CUR="$(find $WATCH_PATHS -type f \( -name '*.php' -o -name '*.env' -o -name '*.blade.php' -o -name '*.json' \) -not -path 'vendor/*' -print 2>/dev/null \
      | sort \
      | xargs -r cat 2>/dev/null | md5sum | awk '{print $1}')"
    if [ "$CUR" != "$LAST" ] && [ -n "$CUR" ]; then
      echo "[dev] polling detectou mudança → reload"
      LAST="$CUR"
      php artisan octane:reload || true
    fi
    sleep 3
  done
) &

# Inicia Octane (sem --watch)
exec php artisan octane:start \
  --server=swoole \
  --host=0.0.0.0 \
  --port=8000 \
  --workers=1 \
  --task-workers=0 \
  --max-requests=0
