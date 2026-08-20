#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64' .env; then
  php artisan key:generate --ansi --force
fi

php artisan migrate --force

# --no-reload: without it, `serve` strips container env vars (like DB_HOST)
# from the spawned server process whenever a .env file is present, since it
# re-execs on .env changes and otherwise only passes through a fixed
# whitelist of variables.
exec php artisan serve --host=0.0.0.0 --port=9000 --no-reload
