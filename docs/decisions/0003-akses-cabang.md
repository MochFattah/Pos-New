# 0003. Semantik akses cabang

Status: diterima oleh pemilik produk. Tanggal: 2026-11-24.

## Konteks

`PolicyService` memeriksa akses cabang lewat tabel `user_branch_access`. Belum jelas bagaimana pengguna non-Owner tanpa baris akses diperlakukan, apakah daftar cabang ikut dibatasi, dan siapa yang berhak atas cabang yang baru dibuat.

## Keputusan

1. Pengguna non-Owner TANPA baris `user_branch_access` tidak memiliki akses cabang apa pun (gagal tertutup). Perilaku `PolicyService` ini dipertahankan.
2. Daftar cabang (`BranchService::list`, halaman `/cabang`) difilter untuk non-Owner ke cabang yang tercantum di `user_branch_access` miliknya. Owner melihat semua cabang tenant. Tanpa baris akses, halaman menampilkan keadaan kosong: "Anda belum memiliki akses cabang. Hubungi Owner."
3. Non-Owner yang membuat cabang otomatis diberi akses ke cabang itu, dalam transaksi yang sama dengan pembuatan cabang. Pemberian ini dicatat di audit sebagai `user.branches_assigned` (metadata `added`, `removed`, `reason: branch_created`) atas nama pengguna itu sendiri. Bila pembuatan cabang gagal, baris akses ikut dibatalkan. Owner tidak memerlukan baris akses.

## Konsekuensi

- Semua kueri baru bersifat tenant-scoped (`tenant_id` pada tabel cabang dan tabel akses); pemindai `TenantScopeScanner` tetap berlaku.
- Pengguna baru dengan permission `branches.view` tetapi tanpa penetapan cabang akan melihat keadaan kosong sampai Owner menetapkan akses lewat pengelolaan pengguna.
- Pengguna yang mengelola cabang tidak dapat "kehilangan" cabang yang baru dibuatnya.
- Tes: `tests/Feature/Branches/BranchTest.php` (filter daftar, keadaan kosong, pemberian otomatis dan audit, Owner tanpa baris akses, pembatalan bersama transaksi).
