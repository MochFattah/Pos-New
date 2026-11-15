# Font

Inter (DESAIN.md 3.1) dilayani dari folder ini, tanpa CDN:

- `Inter-Regular.woff` (400), `Inter-Medium.woff` (500), `Inter-SemiBold.woff` (600)
- Berasal dari paket `fonts-inter` (Inter 3.x, SIL OFL 1.1; teks lisensi di `LICENSE-OFL.txt`),
  di-subset ke Latin dan Latin-1 (cukup untuk Bahasa Indonesia) lalu dikemas WOFF.
- Kemasan WOFF2 tidak tersedia di lingkungan build (modul `brotli` tidak ada); WOFF cukup kecil (sekitar 25 KB per bobot).
- Bila berkas hilang, `tokens.css` otomatis jatuh ke tumpukan font sistem
  (`system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`).
