# 0004. Kebijakan pengelolaan pengguna

Status: diterima oleh pemilik produk. Tanggal: 2026-11-24.

## Konteks

Peninjauan fondasi menandai tiga perilaku pengelolaan pengguna dan pengaturan sebagai temuan terbuka. Pemilik memutuskan ketiganya diterima apa adanya; catatan ini mencegah temuan yang sama diajukan ulang tanpa konteks.

## Keputusan

1. Pengguna non-Owner dengan permission `users.manage` BOLEH mengubah dan menonaktifkan sesama pengguna non-Owner yang permission-nya lebih tinggi daripada miliknya. Tidak ada hierarki permission antar pengguna non-Owner. Batasan yang tetap berlaku: Owner tetap terlindungi (tidak dapat diubah atau dinonaktifkan oleh non-Owner), dan pemeriksaan eskalasi pemberian (non-Owner tidak dapat memberikan role atau permission yang tidak ia miliki sendiri) tidak berubah.
2. Pesan "Email sudah terdaftar" tetap dipakai; dengan demikian keberadaan email dapat ditebak (enumerasi). Risikonya dibatasi oleh kuota undangan: `UserService` membatasi laju pengiriman undangan dan jumlah undangan yang belum diterima per tenant.
3. Format angka pengaturan bisnis dibatasi pada `id-ID` dan `en-US` (`SettingsValidator::NUMBER_FORMATS`, bawaan `id-ID`). Format lain tidak didukung sampai ada keputusan baru.

## Konsekuensi

- Pemilik yang ingin membatasi pengelolaan pengguna sebaiknya hanya memberikan `users.manage` kepada orang tepercaya; permission itu setara kuasa atas akun non-Owner lain di tenant.
- Enumerasi email tetap mungkin untuk pihak yang berwenang mengundang atau mendaftar; mitigasinya adalah pembatasan laju, bukan pesan yang disamarkan.
- Menambah format angka berarti mengubah validator dan tampilan, bukan hanya data.
