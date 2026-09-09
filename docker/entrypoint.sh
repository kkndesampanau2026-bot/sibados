#!/bin/sh
set -e

echo "[sibados] Menyiapkan aplikasi…"

# Railway menentukan port lewat $PORT; nginx tidak membaca env, jadi disisipkan
# dengan envsubst. Hanya ${PORT} yang diganti agar variabel nginx ($uri dkk)
# tetap utuh.
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
echo "[sibados] nginx akan mendengarkan port ${PORT}"

# Database privat Railway kadang belum siap tepat saat container hidup.
echo "[sibados] Menunggu database siap…"
attempt=1
until php artisan db:show --quiet > /dev/null 2>&1; do
    if [ "$attempt" -ge 30 ]; then
        echo "[sibados] Database tidak dapat dihubungi setelah 30 percobaan." >&2
        exit 1
    fi

    echo "[sibados]   percobaan ${attempt}/30…"
    attempt=$((attempt + 1))
    sleep 2
done
echo "[sibados] Database terhubung."

php artisan migrate --force --no-interaction

# Mengisi data awal hanya bila database benar-benar masih kosong.
php artisan sibados:seed-initial

# Cache dibangun saat runtime karena bergantung pada variabel lingkungan.
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[sibados] Siap. Menjalankan php-fpm dan nginx."

php-fpm -D
exec nginx -g 'daemon off;'
