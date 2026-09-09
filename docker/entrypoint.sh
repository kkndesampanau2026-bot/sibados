#!/bin/sh
set -e

echo "[sibados] Menyiapkan aplikasi…"

# Railway menentukan port lewat $PORT; nginx tidak membaca env, jadi disisipkan
# dengan envsubst. Hanya ${PORT} yang diganti agar variabel nginx ($uri dkk)
# tetap utuh.
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
echo "[sibados] nginx akan mendengarkan port ${PORT}"

echo "[sibados] Target database: ${DB_CONNECTION}://${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"

# Jaringan privat Railway butuh beberapa saat untuk siap setelah container
# hidup, jadi koneksi dicoba ulang. Galat aslinya ikut dicetak supaya kegagalan
# yang sesungguhnya tidak tersamar oleh loop ini.
echo "[sibados] Menunggu database siap…"
attempt=1
max_attempts=60

until php artisan db:show --quiet > /tmp/db-check.log 2>&1; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "[sibados] Database tidak dapat dihubungi setelah ${max_attempts} percobaan." >&2
        echo "[sibados] Galat terakhir:" >&2
        cat /tmp/db-check.log >&2
        exit 1
    fi

    # Cetak galat sesekali agar penyebabnya terlihat tanpa membanjiri log.
    if [ "$((attempt % 5))" -eq 1 ]; then
        echo "[sibados]   percobaan ${attempt}/${max_attempts} — galat saat ini:"
        head -c 600 /tmp/db-check.log || true
    fi

    attempt=$((attempt + 1))
    sleep 2
done

echo "[sibados] Database terhubung setelah ${attempt} percobaan."

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
