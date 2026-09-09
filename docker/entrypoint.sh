#!/bin/sh
set -e

echo "[sibados] Menyiapkan aplikasi…"

# Railway menentukan port lewat $PORT; nginx tidak membaca env, jadi disisipkan
# dengan envsubst. Hanya ${PORT} yang diganti agar variabel nginx ($uri dkk)
# tetap utuh.
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
echo "[sibados] nginx akan mendengarkan port ${PORT}"

echo "[sibados] Target database: ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
# Panjangnya saja, bukan nilainya — cukup untuk mendeteksi variabel yang gagal
# ter-resolve tanpa membocorkan kata sandi ke log.
echo "[sibados] Panjang DB_PASSWORD: ${#DB_PASSWORD}"

# Probe koneksi memakai PDO langsung: pesan galatnya ringkas satu baris,
# berbeda dengan `artisan db:show` yang memuntahkan seluruh stack trace.
db_ready() {
    php -r '
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE"));
        try {
            new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage());
            exit(1);
        }
    ' 2> /tmp/db-error.log
}

echo "[sibados] Menunggu database siap…"
attempt=1
max_attempts=60

until db_ready; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "[sibados] Database tidak dapat dihubungi setelah ${max_attempts} percobaan." >&2
        echo "[sibados] Galat: $(cat /tmp/db-error.log)" >&2
        exit 1
    fi

    if [ "$((attempt % 5))" -eq 1 ]; then
        echo "[sibados]   percobaan ${attempt}/${max_attempts} — galat: $(cat /tmp/db-error.log)"
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
