# 0002. Verifikasi email wajib (catatan revisi PRD v1.4)

Status: diterima oleh pemilik produk. Tanggal: 2026-11-24. Menggantikan sebagian PRD v1.3 dan menjadi catatan revisi untuk PRD v1.4.

## Konteks

PRD v1.3 bagian 6.1 (Alur Registrasi) tidak mewajibkan verifikasi email: setelah registrasi, Owner langsung masuk ke dashboard dan demo langsung aktif (langkah 7 dan 8). Implementasi fondasi sudah memiliki alur verifikasi email, tetapi belum ada keputusan resmi apakah login sebelum verifikasi diizinkan dan kapan demo dimulai.

## Keputusan

1. Verifikasi email bersifat WAJIB. Akun yang emailnya belum diverifikasi tidak dapat login dan tidak dapat memakai aplikasi. PRD v1.3 yang membolehkan login sebelum verifikasi tidak lagi berlaku.
2. Demo dimulai saat email diverifikasi, bukan saat registrasi. Waktu mulai dan akhir demo dihitung dari saat verifikasi berhasil; registrasi yang tidak pernah diverifikasi tidak menghabiskan masa demo.
3. Registrasi tetap membuat akun Owner, tenant, dan cabang utama; yang berubah adalah status akun (menunggu verifikasi) dan kapan demo diaktifkan.

## Bagian teks PRD yang berubah (untuk PRD v1.4)

| Bagian PRD | Teks v1.3 | Perubahan di v1.4 |
|---|---|---|
| Riwayat versi (tabel di awal) dan baris `Versi` | Versi 1.3 | Tambah baris 1.4: verifikasi email wajib dan pemicu mulai demo; naikkan `Versi` menjadi 1.4; pernyataan penutup "PRD versi 1.3" menjadi 1.4 |
| 6.1 Alur Registrasi, langkah 4 | "Sistem memvalidasi email dan data wajib." | Tambahan: sistem mengirim email verifikasi |
| 6.1 Alur Registrasi, langkah 5 | "Sistem membuat akun Owner, tenant, dan cabang utama." | Akun dibuat berstatus menunggu verifikasi email |
| 6.1 Alur Registrasi, langkah 7 | "Sistem mengaktifkan demo sesuai konfigurasi platform." | Dipindah setelah verifikasi: sistem mengaktifkan demo saat Owner memverifikasi email |
| 6.1 Alur Registrasi, langkah 8 | "Owner masuk ke dashboard dan dapat menggunakan fitur demo." | Owner hanya dapat login dan masuk ke dashboard setelah verifikasi email berhasil |
| 6.2 Pengaturan Demo | Durasi demo tanpa pemicu awal | Tambah kalimat: durasi dihitung sejak email diverifikasi |
| Bagian 1 (ringkasan produk) dan Asumsi poin 10 | "Pengguna baru memperoleh demo ... durasi awal satu hari" | Klarifikasi: demo diberikan setelah email terverifikasi |
| FR-001 | "registrasi dan autentikasi pengguna" | Tambah: verifikasi email wajib sebelum login |

Bagian lain (perpanjangan demo oleh Super Admin, kebijakan kedaluwarsa) tidak berubah.

## Konsekuensi

- Pengguna harus memiliki akses ke email yang didaftarkan sebelum bisa mencoba produk; tautan verifikasi harus terkirim andal (lihat `docs/deployment/` untuk konfigurasi email).
- Tes dan dokumentasi fondasi yang mengasumsikan demo dimulai saat registrasi perlu mengikuti keputusan ini.
- Berkas `PRD.md` belum diubah oleh catatan ini; pembaruan teks dilakukan pemilik produk saat menerbitkan PRD v1.4.
