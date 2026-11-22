# Deploy dengan Nginx dan PHP-FPM

Belum diuji pada server Nginx nyata; contoh ini mengikuti struktur proyek dan perlu disesuaikan.

Prinsip: `root` mengarah ke `public/`. Semua lainnya (`.env`, `app/`, `storage/`, `vendor/`) berada di atas root web dan tidak dapat dijangkau. `.htaccess` tidak dipakai di Nginx.

```nginx
server {
    listen 443 ssl http2;
    server_name pos.example.com;
    # ssl_certificate dan ssl_certificate_key sesuai penyedia sertifikat

    root /var/www/pos-retail/public;
    index index.php;

    # Ukuran badan permintaan: logo maks 1 MB; sisakan ruang untuk formulir.
    client_max_body_size 3m;

    # Tolak dotfile, kecuali .well-known
    location ~ /\.(?!well-known/) { deny all; }

    location / {
        try_files $uri /index.php$is_args$args;
    }

    # Hanya front controller yang boleh dieksekusi PHP.
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ \.php$ { return 404; }
}

server {
    listen 80;
    server_name pos.example.com;
    return 301 https://$host$request_uri;
}
```

Catatan:

- IP klien (dipakai pembatas laju) diambil dari `REMOTE_ADDR`. `X-Forwarded-For` diabaikan kecuali `REMOTE_ADDR` termasuk `TRUSTED_PROXIES` di `.env` (IP/CIDR dipisah koma, IPv4/IPv6, bawaan kosong). Bila ya, dipakai entri paling kanan yang bukan proxy tepercaya; entri tak valid membuat aplikasi kembali ke `REMOTE_ADDR`. **Di balik proxy/CDN/load balancer wajib mengisi `TRUSTED_PROXIES`**; bila tidak, semua pengguna berbagi satu IP dan 50 login gagal dari siapa pun mengunci login semua orang selama 15 menit. Proxy harus menambahkan (bukan menimpa tanpa memeriksa) IP asli ke `X-Forwarded-For`, mis. `proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;`. Jangan isi `TRUSTED_PROXIES` bila aplikasi dapat dijangkau langsung dari internet.
- Bila TLS diakhiri di proxy, set `SESSION_SECURE=true` di `.env`.
- PHP-FPM: PHP 8.3+, ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`. `upload_max_filesize` dan `post_max_size` minimal 2M dan 3M.
- Izin, `.env`, database, penginstal, Super Admin, SMTP, backup, dan upgrade sama dengan `docs/deployment/cpanel.md` (bagian 3 sampai 10). Di server sendiri jalankan `php scripts/migrate.php` langsung lewat SSH.
- Pastikan pengguna PHP-FPM dapat menulis `storage/`.
