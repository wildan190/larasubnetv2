#!/bin/bash
set -e

# Tunggu sampai Postgres siap
echo "Menunggu database siap..."
# default credentials sesuai compose
until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-root}" >/dev/null 2>&1; do
  sleep 1
done

echo "Database tersedia."

# Copy .env jika belum ada (opsional, tergantung setup kamu)
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Generate app key kalau belum ada
php artisan key:generate --force

# Jalankan migrasi (pakai force agar non-interactive)
php artisan migrate --force

# Bisa tambahkan seeding kalau perlu
# php artisan db:seed --force

# Teruskan ke CMD asli (php-fpm)
exec "$@"
