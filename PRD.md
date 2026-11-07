# PRODUCT REQUIREMENTS DOCUMENT (PRD)

# POS Retail Web Application

**Versi:** 1.3\
**Status:** Baseline kebutuhan produk\
**Bahasa:** Indonesia\
**Platform:** Web App + Progressive Web App (PWA)\
**Teknologi utama:** PHP Native, MySQL, JavaScript\
**Model bisnis:** SaaS berlangganan\
**Tanggal dokumen:** 8 November 2026

------------------------------------------------------------------------

## Riwayat Versi

  -----------------------------------------------------------------------
  Versi                               Perubahan
  ----------------------------------- -----------------------------------
  1.0                                 Penyusunan kebutuhan awal POS
                                      Retail multi-tenant, multi-cabang,
                                      PWA, POS offline, inventori,
                                      pembelian, laporan, akuntansi, dan
                                      Super Admin.

  1.1                                 Penambahan keputusan tipe produk,
                                      paket Starter/Business/Enterprise,
                                      demo, Midtrans, akuntansi
                                      double-entry, dan cakupan offline
                                      POS.

  1.2                                 Penetapan shift kasir wajib, kanal
                                      dukungan Email dan Tiket Bantuan,
                                      demo berulang oleh Super Admin,
                                      paket harga khusus, absensi
                                      berbasis shift, dan scan barcode
                                      via kamera HP.

  1.3                                 Penegasan bahwa Midtrans hanya
                                      untuk langganan platform,
                                      konfigurasi Midtrans hanya dikelola
                                      Super Admin, serta metode
                                      pembayaran toko tidak menggunakan
                                      Midtrans.
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 1. Ringkasan Produk

POS Retail Web Application adalah aplikasi Point of Sale (POS) berbasis
web dan PWA untuk membantu bisnis retail mengelola transaksi penjualan,
produk, variasi produk, persediaan, pembelian, pelanggan, pemasok,
karyawan, cabang, kas, keuangan, akuntansi, dan laporan dalam satu
sistem.

Produk ditujukan untuk bisnis retail dari skala UMKM, menengah, hingga
perusahaan retail besar. Sistem mendukung banyak tenant dan banyak
cabang. Setiap tenant memiliki data bisnis yang terisolasi, pengguna,
cabang, konfigurasi operasional, serta langganan masing-masing.

Aplikasi menggunakan model SaaS berlangganan dengan paket Starter,
Business, Enterprise, dan paket harga khusus. Pengguna baru memperoleh
demo dengan akses fitur penuh selama durasi yang dapat diatur melalui
dashboard platform; durasi awal yang ditetapkan adalah satu hari. Super
Admin dapat memberikan demo atau perpanjangan demo berkali-kali.

Aplikasi harus dapat digunakan secara online untuk seluruh modul. Mode
offline dibatasi untuk POS, sehingga kasir tetap dapat mencatat
transaksi saat koneksi internet terputus dan menyinkronkannya kembali
ketika koneksi pulih.

## 1.1 Prinsip Produk

1.  Satu platform untuk berbagai jenis bisnis retail.
2.  Mendukung multi-tenant dan multi-cabang.
3.  Data antar-tenant wajib terisolasi.
4.  POS harus cepat, mudah dipahami, dan mendukung perangkat desktop
    maupun mobile.
5.  Tidak boleh terjadi stok negatif.
6.  Setiap transaksi keuangan yang relevan menghasilkan jurnal akuntansi
    yang seimbang.
7.  Setiap kasir wajib membuka shift sebelum transaksi dan menutup shift
    setelah selesai.
8.  Pembayaran langganan platform menggunakan Midtrans.
9.  Pembayaran penjualan toko dicatat di POS tanpa integrasi Midtrans.
10. Pengelolaan platform dilakukan melalui dashboard Super Admin.

## 1.2 Istilah

  -----------------------------------------------------------------------
  Istilah                             Definisi
  ----------------------------------- -----------------------------------
  Platform                            Aplikasi SaaS POS Retail yang
                                      disediakan penyelenggara.

  Tenant                              Satu bisnis/perusahaan pelanggan
                                      platform.

  Owner Tenant                        Pemilik atau pengelola utama akun
                                      bisnis tenant.

  Super Admin                         Pengelola platform SaaS.

  Cabang                              Lokasi operasional milik tenant.

  POS                                 Modul kasir untuk transaksi
                                      penjualan.

  Shift Kasir                         Sesi kerja kasir dari pembukaan
                                      sampai penutupan kas.

  Demo                                Masa uji coba aplikasi dengan akses
                                      fitur penuh dan batasan yang
                                      dikonfigurasi.

  Offline Queue                       Antrean transaksi lokal yang
                                      menunggu sinkronisasi ke server.
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 2. Latar Belakang dan Permasalahan

Bisnis retail membutuhkan sistem terpusat untuk mengelola penjualan,
stok, pembelian, pelanggan, karyawan, kas, dan laporan. Penggunaan
pencatatan manual atau beberapa aplikasi yang terpisah menimbulkan
risiko seperti:

-   Data produk dan stok tidak konsisten.
-   Kesulitan memantau banyak cabang.
-   Kesalahan pencatatan kas dan transaksi.
-   Tidak adanya rekam jejak aktivitas karyawan.
-   Kesulitan mengetahui laba, rugi, arus kas, dan posisi keuangan.
-   Laporan tidak tersedia secara cepat dan terperinci.
-   Operasional kasir terganggu ketika internet terputus.
-   Pengendalian akses dan audit transaksi tidak memadai.

Produk ini menyatukan proses tersebut dalam satu aplikasi dengan kontrol
akses, pencatatan audit, sinkronisasi offline POS, dan akuntansi
double-entry.

------------------------------------------------------------------------

# 3. Tujuan Produk dan Batasan

## 3.1 Tujuan

-   Menyediakan sistem POS yang dapat digunakan berbagai jenis bisnis
    retail.
-   Mendukung operasional satu atau banyak cabang.
-   Mempermudah pengelolaan produk, varian, stok, pembelian, dan
    penjualan.
-   Menyediakan laporan operasional dan keuangan secara terperinci.
-   Menerapkan pembukuan double-entry.
-   Mencatat kehadiran kasir melalui aktivitas buka dan tutup shift.
-   Menyediakan mode offline khusus POS.
-   Menyediakan dashboard platform untuk mengelola tenant, demo, paket,
    langganan, pembayaran, dan dukungan.
-   Menyediakan model SaaS yang dapat dikembangkan dari UMKM sampai
    perusahaan besar.

## 3.2 Bukan Tujuan MVP

Hal berikut tidak menjadi komitmen MVP kecuali ditambahkan melalui
keputusan produk berikutnya:

-   Aplikasi native Android/iOS yang didistribusikan melalui app store.
-   Payroll penuh dan perhitungan pajak penghasilan karyawan.
-   Integrasi otomatis dengan seluruh bank, marketplace, atau penyedia
    payment gateway.
-   Penggunaan Midtrans untuk pembayaran pelanggan toko.
-   Mode offline untuk inventori, pembelian, akuntansi, dashboard, atau
    modul selain POS.
-   White-label penuh untuk setiap tenant.
-   Integrasi perangkat keras yang dijamin kompatibel dengan seluruh
    kombinasi perangkat, browser, dan sistem operasi.

------------------------------------------------------------------------

# 4. Target Pengguna dan Hak Akses

## 4.1 Target Pengguna

1.  UMKM retail.
2.  Retail skala menengah.
3.  Perusahaan retail besar.
4.  Bisnis retail dengan satu atau banyak cabang.
5.  Pemilik usaha, pengelola cabang, kasir, staf gudang, staf pembelian,
    dan staf keuangan.

## 4.2 Persona

### Persona A --- Owner Bisnis

-   Memiliki satu atau beberapa cabang.
-   Membutuhkan ringkasan penjualan, stok, laba, arus kas, dan kinerja
    cabang.
-   Mengelola paket, pengguna, dan kebijakan bisnis.
-   Membutuhkan akses laporan dari mana saja.

### Persona B --- Kasir

-   Melakukan transaksi penjualan setiap hari.
-   Menggunakan scanner atau kamera HP untuk mencari produk.
-   Harus membuka shift sebelum transaksi.
-   Perlu tetap bertransaksi ketika internet terputus.
-   Menutup shift dan mencocokkan kas pada akhir kerja.

### Persona C --- Manager Cabang

-   Memantau operasional cabang.
-   Mengelola karyawan, stok, shift, dan laporan cabang sesuai
    kewenangan.
-   Menyetujui tindakan operasional tertentu.

### Persona D --- Super Admin Platform

-   Mengelola tenant dan akun platform.
-   Mengelola paket, demo, langganan, serta pembayaran.
-   Mengelola konfigurasi Midtrans platform.
-   Menangani tiket bantuan dan memantau kesehatan sistem.

## 4.3 Role dan Hak Akses

Role minimum yang harus tersedia:

  -----------------------------------------------------------------------
  Role                                Cakupan kewenangan
  ----------------------------------- -----------------------------------
  Super Admin                         Seluruh pengelolaan platform dan
                                      tenant sesuai kebijakan platform.

  Owner Tenant                        Seluruh pengelolaan bisnis tenant,
                                      cabang, pengguna, laporan, dan
                                      konfigurasi tenant.

  Manager                             Pengelolaan operasional cabang
                                      sesuai izin yang diberikan.

  Supervisor                          Pemantauan dan persetujuan
                                      operasional tertentu.

  Kasir                               POS, shift sendiri, dan fungsi
                                      kasir yang diizinkan.

  Staf Gudang                         Produk dan persediaan sesuai izin.

  Staf Pembelian                      Supplier, purchase order, dan
                                      penerimaan barang sesuai izin.

  Staf Keuangan                       Kas, pembayaran, piutang, utang,
                                      dan laporan sesuai izin.

  Akuntan                             Jurnal, buku besar, periode, dan
                                      laporan keuangan sesuai izin.
  -----------------------------------------------------------------------

RBAC harus mendukung permission granular, bukan hanya role tetap. Owner
dapat mengatur hak akses pengguna tenant selama tidak melampaui
kewenangan platform.

------------------------------------------------------------------------

# 5. Ruang Lingkup Fitur

Semua kelompok fitur berikut termasuk ruang lingkup MVP sesuai kebutuhan
produk. Implementasi dapat dibagi menjadi beberapa sprint, tetapi tidak
boleh menghilangkan kebutuhan tanpa persetujuan perubahan scope.

## 5.1 Modul Tenant dan Akun

-   Registrasi Owner Tenant.
-   Login, logout, dan reset password.
-   Profil bisnis.
-   Pembuatan tenant dan cabang utama.
-   Pengelolaan pengguna dan role.
-   Isolasi data antar-tenant.
-   Pengaturan profil dan logo tenant.
-   Pengaturan zona waktu, mata uang, format nomor, dan preferensi
    bisnis.
-   Pengelolaan status akun tenant.

## 5.2 Modul Paket dan Langganan

-   Paket Starter, Business, dan Enterprise.
-   Paket harga khusus terpisah.
-   Pengaturan harga dan periode langganan.
-   Batas cabang, pengguna, fitur, dan kapasitas berdasarkan paket.
-   Masa demo dengan durasi yang dapat diatur.
-   Demo awal satu hari secara default.
-   Pemberian dan perpanjangan demo berkali-kali oleh Super Admin.
-   Invoice dan riwayat pembayaran.
-   Pembayaran langganan melalui Midtrans.
-   Aktivasi dan perpanjangan langganan berdasarkan webhook yang
    tervalidasi.
-   Pengingat masa demo atau langganan berakhir.
-   Pemblokiran akses operasional saat demo/langganan berakhir.

**Kebijakan kedaluwarsa:** setelah demo atau langganan berakhir, akses
operasional aplikasi diblokir. Data tenant tetap disimpan dan tidak
otomatis dihapus. Mekanisme akses terbatas untuk pembayaran atau
perpanjangan harus disediakan melalui tautan tagihan/kanal yang
ditentukan platform, tanpa membuka kembali akses operasional sebelum
pembayaran atau persetujuan yang sah.

## 5.3 Modul Cabang

-   Tambah, ubah, dan nonaktifkan cabang.
-   Alamat dan informasi cabang.
-   Penetapan pengguna ke cabang.
-   Pengaturan gudang dan lokasi stok.
-   Laporan per cabang dan konsolidasi seluruh cabang.
-   Transfer stok antar-cabang.

## 5.4 Modul Produk

-   Kategori dan subkategori.
-   Merek/brand.
-   SKU dan barcode.
-   Nama, deskripsi, foto, harga beli, harga jual, dan status produk.
-   Produk biasa.
-   Produk dengan varian seperti ukuran dan warna.
-   Produk dengan nomor seri/IMEI.
-   Produk berbasis batch dan tanggal kedaluwarsa.
-   Produk dengan konversi satuan seperti dus, box, dan pcs.
-   Produk timbang seperti kg/gram.
-   Produk komposit/bundle jika diaktifkan.
-   Harga jual bertingkat atau harga khusus sesuai kebutuhan
    paket/konfigurasi.
-   Riwayat perubahan harga dan data produk.
-   Impor dan ekspor data produk.

### Aturan produk

-   Setiap varian yang dapat dijual harus memiliki identitas SKU/barcode
    yang dapat dibedakan.
-   Produk serial harus mencatat serial/IMEI unik.
-   Produk batch harus menyimpan nomor batch dan tanggal kedaluwarsa.
-   Stok disimpan dalam satuan dasar dan konversi dilakukan secara
    konsisten.
-   Sistem harus mencegah penjualan produk kedaluwarsa jika aturan
    tenant mengaktifkannya.
-   Produk nonaktif tidak dapat ditambahkan ke transaksi baru.

## 5.5 Modul Barcode dan Scanner

Sistem mendukung:

1.  Scanner barcode USB.
2.  Scanner Bluetooth.
3.  Pemindaian barcode melalui kamera HP.
4.  Pemindaian barcode melalui kamera perangkat yang kompatibel.
5.  Input barcode manual sebagai fallback.

### Pemindaian melalui kamera HP

-   Kasir dapat membuka kamera dari halaman POS.
-   Kamera belakang menjadi pilihan utama jika tersedia.
-   Barcode yang dikenali akan mencari produk/varian terkait.
-   Produk ditemukan: tambahkan ke keranjang atau tingkatkan kuantitas
    sesuai pengaturan POS.
-   Barcode tidak ditemukan: tampilkan notifikasi yang jelas.
-   Mendukung pemindaian berulang.
-   Mendukung Android dan iPhone melalui browser yang kompatibel.
-   Menggunakan `getUserMedia` dan `BarcodeDetector API` jika tersedia.
-   Menyediakan library JavaScript alternatif untuk browser yang belum
    mendukung `BarcodeDetector API`.
-   Akses kamera memerlukan HTTPS dan izin pengguna.
-   Aset scanner harus tersedia secara lokal agar fungsi pemindaian
    dapat berjalan pada POS offline setelah aplikasi dipersiapkan.

## 5.6 Modul Inventori

-   Stok per cabang dan lokasi gudang.
-   Kartu stok.
-   Penyesuaian stok.
-   Stock opname.
-   Stok masuk dan keluar.
-   Transfer stok antar-cabang.
-   Stok minimum dan notifikasi.
-   Riwayat pergerakan stok.
-   Pelacakan batch dan serial.
-   Penilaian persediaan.
-   Larangan stok negatif.
-   Laporan nilai persediaan.

**Kebijakan:** stok negatif tidak diperbolehkan. Semua transaksi yang
mengurangi stok harus memeriksa ketersediaan stok pada saat posting.
Untuk transaksi offline, server harus memvalidasi kembali stok saat
sinkronisasi.

## 5.7 Modul POS

-   Pencarian produk berdasarkan nama, SKU, atau barcode.
-   Scan barcode dengan scanner dan kamera HP.
-   Keranjang belanja.
-   Ubah kuantitas.
-   Diskon item dan transaksi sesuai izin.
-   Pajak sesuai konfigurasi tenant.
-   Catatan transaksi.
-   Pilih pelanggan.
-   Hold/simpan transaksi sementara jika online.
-   Pembayaran penuh dan pembayaran kombinasi.
-   Cetak atau kirim struk.
-   Riwayat transaksi.
-   Pembatalan dan retur sesuai hak akses.
-   Penjualan tunai dan metode pembayaran non-tunai yang dicatat manual.
-   POS offline dengan antrean sinkronisasi.

### Metode pembayaran toko

Metode pembayaran penjualan toko tidak menggunakan integrasi Midtrans.
Sistem menyediakan pencatatan metode pembayaran seperti:

-   Tunai.
-   Transfer bank.
-   QRIS (pencatatan/manual).
-   Kartu debit/kredit (pencatatan/manual).
-   E-wallet (pencatatan/manual).
-   Metode lain yang dibuat tenant.

Satu transaksi dapat menggunakan beberapa metode pembayaran, misalnya
sebagian tunai dan sebagian transfer. Total alokasi pembayaran harus
sama dengan total tagihan sebelum transaksi dianggap lunas.

## 5.8 Modul Shift Kasir dan Absensi

**Wajib untuk setiap kasir.** Kasir tidak dapat melakukan transaksi
sebelum membuka shift dan harus menutup shift setelah selesai bekerja.

### Buka shift

-   Kasir memilih cabang/register sesuai akses.
-   Sistem memeriksa apakah kasir memiliki shift aktif.
-   Kasir mengisi saldo awal kas.
-   Sistem mencatat waktu pembukaan, pengguna, cabang, register, dan
    saldo awal.
-   Setelah shift aktif, kasir dapat melakukan transaksi.

### Selama shift

-   Semua transaksi kasir terhubung dengan shift aktif.
-   Pergerakan kas masuk/keluar dicatat.
-   Transaksi offline tetap dikaitkan dengan shift lokal yang sah.
-   Kasir tidak boleh memiliki shift aktif ganda pada register yang
    sama.
-   Tindakan koreksi atau pembatalan dicatat pada audit log.

### Tutup shift

-   Kasir mengakhiri transaksi.
-   Sistem menghitung saldo kas yang diharapkan.
-   Kasir mengisi jumlah kas fisik aktual.
-   Sistem menghitung selisih kas.
-   Kasir dapat menambahkan catatan selisih.
-   Sistem menyimpan waktu tutup dan ringkasan shift.
-   Owner/Manager dapat meninjau dan menyetujui sesuai kebijakan tenant.

### Absensi

Absensi kasir dicatat melalui aktivitas buka shift dan tutup shift,
sehingga tidak memerlukan check-in/check-out kedua. Data kehadiran
meliputi:

-   Karyawan.
-   Cabang.
-   Register.
-   Waktu mulai kerja (buka shift).
-   Waktu selesai kerja (tutup shift).
-   Durasi kerja.
-   Status shift.
-   Selisih kas dan catatan penutupan jika relevan.

Laporan kehadiran dapat difilter berdasarkan karyawan, cabang, tanggal,
dan status shift. Modul ini mencatat kehadiran berbasis shift kasir;
absensi karyawan nonkasir dapat dikembangkan sebagai modul tersendiri
bila dibutuhkan.

## 5.9 Modul Pelanggan

-   Data pelanggan.
-   Kontak dan alamat.
-   Riwayat pembelian.
-   Total belanja.
-   Segmentasi pelanggan.
-   Piutang pelanggan.
-   Catatan pelanggan.
-   Ekspor data.

## 5.10 Modul Supplier dan Pembelian

-   Data supplier.
-   Purchase request/order.
-   Penerimaan barang.
-   Pembelian tunai/kredit.
-   Retur pembelian.
-   Biaya pembelian.
-   Utang supplier.
-   Pembayaran utang.
-   Riwayat pembelian.
-   Laporan pembelian dan supplier.

## 5.11 Modul Transfer dan Retur

-   Transfer stok antar-cabang.
-   Status draft, dikirim, diterima, dan dibatalkan.
-   Validasi kuantitas transfer.
-   Retur penjualan.
-   Retur pembelian.
-   Pengembalian stok sesuai kondisi barang.
-   Pengembalian dana atau pencatatan kredit pelanggan.
-   Penyesuaian jurnal otomatis sesuai transaksi.

## 5.12 Modul Kas dan Keuangan

-   Kas dan rekening bank.
-   Kas masuk dan kas keluar.
-   Kategori biaya.
-   Penerimaan dan pembayaran.
-   Piutang pelanggan.
-   Utang supplier.
-   Rekonsiliasi kas dan bank.
-   Arus kas.
-   Riwayat pembayaran.
-   Lampiran bukti transaksi.
-   Laporan keuangan dan operasional.

## 5.13 Modul Akuntansi Double-Entry

Akuntansi double-entry termasuk MVP.

### Komponen

-   Chart of Accounts (COA).
-   Jurnal umum.
-   Jurnal otomatis dari transaksi operasional.
-   Buku besar.
-   Neraca saldo.
-   Laporan laba rugi.
-   Neraca.
-   Laporan arus kas.
-   Piutang dan utang.
-   Penilaian persediaan.
-   Harga pokok penjualan (HPP/COGS).
-   Pajak.
-   Periode akuntansi.
-   Tutup buku.
-   Jurnal penyesuaian dan pembalik.
-   Rekonsiliasi kas/bank.

### Aturan akuntansi

1.  Total debit harus sama dengan total kredit pada setiap jurnal.
2.  Jurnal yang sudah diposting tidak boleh diubah atau dihapus
    langsung.
3.  Koreksi dilakukan melalui jurnal pembalik atau jurnal penyesuaian.
4.  Transaksi operasional yang diposting harus memiliki referensi
    sumber.
5.  Sistem harus mencegah jurnal ganda akibat pengiriman ulang atau
    sinkronisasi.
6.  Periode yang sudah ditutup tidak menerima posting biasa tanpa proses
    pembukaan/otorisasi.
7.  Setiap jurnal harus terikat pada tenant dan dapat memiliki dimensi
    cabang.
8.  Laporan konsolidasi tenant harus dapat membedakan laporan per cabang
    dan seluruh cabang.

### Metode penilaian persediaan

Rekomendasi awal adalah Moving Weighted Average (rata-rata tertimbang
bergerak). FIFO dapat disediakan sebagai opsi konfigurasi jika disetujui
dalam desain akuntansi final. Metode yang dipakai harus konsisten dan
perubahan metode memerlukan prosedur akuntansi khusus.

### Contoh jurnal penjualan

Misalnya penjualan tunai Rp100.000 dan HPP Rp60.000:

  Akun               Debit      Kredit
  ------------ ----------- -----------
  Kas            Rp100.000          \-
  Penjualan             \-   Rp100.000
  HPP             Rp60.000          \-
  Persediaan            \-    Rp60.000

Jurnal di atas merupakan dua jurnal terkait: pengakuan penjualan dan
pengakuan HPP.

## 5.14 Modul Laporan

Semua laporan harus mendukung filter periode, cabang, pengguna, produk,
kategori, dan status yang relevan. Laporan penting harus menyediakan
drill-down ke transaksi sumber.

### Laporan penjualan

-   Penjualan harian, mingguan, bulanan, dan rentang tanggal.
-   Penjualan per cabang.
-   Penjualan per kasir.
-   Penjualan per produk, varian, kategori, dan merek.
-   Penjualan per pelanggan.
-   Metode pembayaran.
-   Diskon dan pajak.
-   Pembatalan dan retur.
-   Transaksi offline dan status sinkronisasi.

### Laporan persediaan

-   Stok saat ini.
-   Kartu stok.
-   Stok minimum.
-   Stok mati/slow moving.
-   Fast moving.
-   Nilai persediaan.
-   Stok per cabang.
-   Stok per batch dan tanggal kedaluwarsa.
-   Serial/IMEI.
-   Hasil stock opname dan selisih.

### Laporan pembelian

-   Pembelian per periode.
-   Pembelian per supplier.
-   Pembelian per produk.
-   Utang supplier.
-   Retur pembelian.
-   Riwayat penerimaan barang.

### Laporan kas dan shift

-   Ringkasan shift.
-   Kas awal dan kas akhir.
-   Kas yang diharapkan dan kas aktual.
-   Selisih kas.
-   Kas masuk/keluar.
-   Performa kasir.
-   Riwayat buka/tutup shift.

### Laporan absensi berbasis shift

-   Kehadiran karyawan.
-   Jam mulai dan selesai.
-   Durasi shift.
-   Shift belum ditutup.
-   Rekap per karyawan dan cabang.

### Laporan pelanggan

-   Daftar pelanggan.
-   Riwayat transaksi.
-   Pelanggan aktif.
-   Pelanggan berdasarkan nilai belanja.
-   Piutang pelanggan.

### Laporan keuangan dan akuntansi

-   Buku besar.
-   Jurnal umum.
-   Neraca saldo.
-   Laba rugi.
-   Neraca.
-   Arus kas.
-   Piutang.
-   Utang.
-   HPP.
-   Nilai persediaan.
-   Pajak.
-   Rekonsiliasi kas/bank.
-   Laporan per cabang dan konsolidasi.

Format ekspor minimal: PDF, Excel, dan CSV.

## 5.15 Dashboard Owner Tenant

-   Ringkasan penjualan.
-   Ringkasan laba kotor dan indikator laba/rugi sesuai data akuntansi.
-   Ringkasan stok.
-   Produk terlaris.
-   Penjualan per cabang.
-   Performa kasir.
-   Status shift aktif.
-   Ringkasan kas dan bank.
-   Piutang dan utang.
-   Ringkasan laporan keuangan.
-   Status paket dan langganan.
-   Invoice dan riwayat pembayaran langganan.
-   Pengaturan bisnis dan pengguna.
-   Tiket bantuan.

Dashboard Owner tidak menyediakan pengaturan kredensial Midtrans
platform.

## 5.16 Dashboard Super Admin Platform

-   Dashboard metrik platform.
-   Manajemen tenant.
-   Manajemen demo.
-   Pemberian demo dan perpanjangan demo berkali-kali.
-   Manajemen paket standar.
-   Manajemen paket harga khusus.
-   Manajemen langganan.
-   Manajemen invoice dan pembayaran.
-   Konfigurasi Midtrans platform.
-   Monitoring webhook dan transaksi langganan.
-   Manajemen pengguna platform.
-   Tiket bantuan.
-   Pengumuman.
-   Pengaturan sistem.
-   Monitoring kesehatan aplikasi.
-   Audit log platform.

## 5.17 Dukungan dan Tiket Bantuan

Kanal dukungan resmi:

-   Email.
-   Tiket bantuan di aplikasi atau kanal dukungan platform.

Tiket mencakup nomor tiket, tenant, pembuat, kategori, prioritas,
subjek, deskripsi, lampiran, status, petugas, percakapan, dan riwayat
perubahan. Owner dapat melihat dan membalas tiket. Super Admin/support
dapat mengelola, menugaskan, dan memperbarui status tiket.

Status minimum: Open, In Progress, Waiting for User, Resolved, Closed.

------------------------------------------------------------------------

# 6. Registrasi, Demo, dan Langganan

## 6.1 Alur Registrasi

1.  Pengunjung membuka landing page.
2.  Pengunjung memilih Login atau Register.
3.  Pengunjung mengisi data Owner dan bisnis.
4.  Sistem memvalidasi email dan data wajib.
5.  Sistem membuat akun Owner, tenant, dan cabang utama.
6.  Sistem menyiapkan konfigurasi awal tenant.
7.  Sistem mengaktifkan demo sesuai konfigurasi platform.
8.  Owner masuk ke dashboard dan dapat menggunakan fitur demo.

Data registrasi minimum:

-   Nama Owner.
-   Email.
-   Nomor telepon/WhatsApp.
-   Password.
-   Nama bisnis.
-   Jenis bisnis.
-   Alamat bisnis.

## 6.2 Pengaturan Demo

Super Admin dapat mengatur:

-   Demo aktif/nonaktif.
-   Durasi demo.
-   Paket yang digunakan sebagai basis demo.
-   Fitur yang tersedia.
-   Batas cabang dan pengguna.
-   Kriteria kelayakan demo.
-   Izin pemberian demo ulang.
-   Durasi perpanjangan.
-   Catatan alasan pemberian/perpanjangan.

Durasi default demo awal adalah satu hari, tetapi harus dapat diubah
dari dashboard Super Admin.

Super Admin dapat memberikan demo atau perpanjangan berkali-kali. Setiap
pemberian harus tercatat dalam riwayat audit.

## 6.3 Setelah Demo Berakhir

-   Akses operasional diblokir.
-   Data tenant tidak dihapus otomatis.
-   Owner memperoleh informasi bahwa demo telah berakhir.
-   Owner dapat menghubungi platform melalui email atau tiket/kanal yang
    tersedia.
-   Super Admin dapat menyetujui demo tambahan atau mengarahkan Owner ke
    langganan.
-   Jika Owner melanjutkan ke langganan, platform membuat invoice dan
    menyediakan pembayaran Midtrans.

## 6.4 Paket Langganan

Paket standar:

-   Starter.
-   Business.
-   Enterprise.

Paket harga khusus dikelola terpisah dan dapat memiliki harga, batas,
fitur, serta ketentuan tersendiri. Harga khusus tidak harus ditampilkan
sebagai paket publik.

Setiap paket dapat menentukan:

-   Harga.
-   Periode bulanan/tahunan atau periode yang dikonfigurasi.
-   Batas cabang.
-   Batas pengguna.
-   Batas perangkat/register bila diperlukan.
-   Fitur yang tersedia.
-   Batas kapasitas.
-   Dukungan dan SLA sesuai kebijakan.

## 6.5 Kedaluwarsa Langganan

Ketika langganan berakhir dan tidak diperpanjang:

-   Akses operasional tenant diblokir.
-   Data tenant dipertahankan sesuai kebijakan retensi.
-   Sistem tidak boleh menghapus data secara otomatis hanya karena
    langganan kedaluwarsa.
-   Owner dapat menerima tautan invoice/pembayaran atau menghubungi
    dukungan platform.
-   Akses operasional dipulihkan setelah pembayaran diverifikasi atau
    ada persetujuan resmi Super Admin sesuai kebijakan.

------------------------------------------------------------------------

# 7. Integrasi Midtrans

## 7.1 Batas Penggunaan

Midtrans hanya digunakan untuk pembayaran langganan aplikasi POS oleh
tenant kepada penyelenggara platform.

Midtrans **tidak digunakan** untuk memproses pembayaran pelanggan toko
pada transaksi POS.

Metode pembayaran toko dicatat oleh kasir secara manual di POS, termasuk
tunai, transfer, QRIS, kartu, e-wallet, dan metode lain yang
dikonfigurasi tenant.

## 7.2 Pengelolaan Kredensial

Konfigurasi Midtrans dikelola hanya oleh Super Admin melalui dashboard
platform. Owner Tenant dan Manager tidak memiliki akses untuk mengubah
kredensial Midtrans platform.

Field konfigurasi minimal:

-   Client Key.
-   Server Key.
-   Merchant ID.
-   Environment Sandbox/Production.
-   URL notification/webhook.
-   Status integrasi.
-   Pengaturan koneksi/pengujian.
-   Log notifikasi dan transaksi.

Server Key wajib disimpan secara aman/terenkripsi dan tidak boleh
dikirim ke browser atau ditampilkan secara utuh setelah disimpan.

## 7.3 Alur Pembayaran

1.  Owner memilih paket atau menerima penawaran paket khusus.
2.  Sistem membuat invoice dengan snapshot harga dan ketentuan.
3.  Owner membuka halaman pembayaran.
4.  Sistem membuat transaksi Midtrans dari server.
5.  Owner menyelesaikan pembayaran melalui kanal Midtrans.
6.  Server menerima notifikasi webhook.
7.  Server memverifikasi signature/status transaksi melalui mekanisme
    resmi Midtrans.
8.  Sistem memperbarui status invoice dan langganan secara idempotent.
9.  Sistem mengirim konfirmasi pembayaran.

Status yang ditangani minimal: pending, settlement, capture, expire,
cancel, deny, dan status lain yang didokumentasikan Midtrans.

Webhook harus aman terhadap notifikasi berulang, terlambat, atau tidak
berurutan. Aktivasi langganan tidak boleh hanya bergantung pada redirect
browser dari halaman pembayaran.

------------------------------------------------------------------------

# 8. Persyaratan Fungsional

  -----------------------------------------------------------------------
  ID                                  Persyaratan
  ----------------------------------- -----------------------------------
  FR-001                              Sistem menyediakan registrasi dan
                                      autentikasi pengguna.

  FR-002                              Sistem mendukung multi-tenant
                                      dengan isolasi data.

  FR-003                              Sistem mendukung multi-cabang per
                                      tenant.

  FR-004                              Sistem menyediakan RBAC dan
                                      permission granular.

  FR-005                              Sistem menyediakan paket dan
                                      kontrol langganan SaaS.

  FR-006                              Sistem menyediakan demo dengan
                                      durasi yang dapat dikonfigurasi.

  FR-007                              Super Admin dapat memberikan demo
                                      berulang kali.

  FR-008                              Sistem menyediakan katalog produk
                                      dan varian.

  FR-009                              Sistem mendukung SKU, barcode,
                                      serial, batch, expiry, konversi
                                      unit, dan produk timbang.

  FR-010                              Sistem mendukung scan barcode
                                      melalui scanner fisik dan kamera
                                      HP.

  FR-011                              Sistem mengelola stok per
                                      cabang/lokasi.

  FR-012                              Sistem melarang stok negatif.

  FR-013                              Sistem menyediakan POS online.

  FR-014                              Sistem menyediakan POS offline dan
                                      antrean sinkronisasi.

  FR-015                              Sistem mendukung pembayaran
                                      kombinasi dalam satu transaksi.

  FR-016                              Sistem tidak mengintegrasikan
                                      Midtrans untuk pembayaran toko.

  FR-017                              Sistem mewajibkan buka shift
                                      sebelum transaksi.

  FR-018                              Sistem mewajibkan tutup shift
                                      setelah selesai.

  FR-019                              Sistem mencatat kehadiran kasir
                                      berdasarkan buka/tutup shift.

  FR-020                              Sistem menyediakan data pelanggan
                                      dan riwayat transaksi.

  FR-021                              Sistem menyediakan supplier dan
                                      pembelian.

  FR-022                              Sistem menyediakan transfer stok
                                      dan retur.

  FR-023                              Sistem menyediakan kas, bank,
                                      piutang, dan utang.

  FR-024                              Sistem menyediakan akuntansi
                                      double-entry.

  FR-025                              Sistem menyediakan laporan
                                      operasional dan keuangan
                                      terperinci.

  FR-026                              Sistem menyediakan dashboard Owner
                                      Tenant.

  FR-027                              Sistem menyediakan dashboard Super
                                      Admin.

  FR-028                              Hanya Super Admin yang dapat
                                      mengatur kredensial Midtrans
                                      platform.

  FR-029                              Sistem menyediakan email dan tiket
                                      bantuan.

  FR-030                              Sistem menyediakan audit log.

  FR-031                              Sistem menyediakan ekspor laporan
                                      PDF, Excel, dan CSV.
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 9. Mode Offline POS

## 9.1 Cakupan

Mode offline hanya berlaku untuk POS. Modul lain tetap memerlukan
koneksi internet.

Fungsi offline:

-   Membuka POS pada perangkat yang sudah terautentikasi dan
    dipersiapkan sebelumnya.
-   Membaca cache produk, SKU, barcode, dan harga yang diizinkan.
-   Scan barcode melalui scanner fisik atau kamera jika
    browser/perangkat mendukung.
-   Menambahkan produk ke keranjang.
-   Mencatat transaksi tunai offline.
-   Menyimpan transaksi dalam antrean lokal.
-   Menampilkan status menunggu sinkronisasi.
-   Mengirim antrean ke server saat koneksi pulih.

Fungsi yang tidak tersedia offline:

-   Pembayaran gateway.
-   Verifikasi pembayaran elektronik secara real-time.
-   Manajemen produk.
-   Perubahan stok manual.
-   Pembelian dan penerimaan barang.
-   Transfer stok.
-   Akuntansi dan laporan.
-   Dashboard.
-   Pengelolaan pengguna.
-   Pengelolaan paket/langganan.

## 9.2 Persiapan Perangkat

-   Perangkat harus login dan mendapat otorisasi ketika online.
-   Data produk dan konfigurasi POS yang dibutuhkan harus tersimpan di
    IndexedDB.
-   Service Worker menyimpan aset aplikasi yang diperlukan.
-   Sistem harus memiliki kebijakan masa berlaku sesi dan cache.
-   Perangkat yang belum pernah dipersiapkan online tidak dijamin dapat
    memulai sesi offline.

## 9.3 Sinkronisasi dan Konflik

-   Setiap transaksi offline memiliki UUID unik.
-   Setiap transaksi terikat pada tenant, cabang, perangkat, kasir, dan
    shift.
-   Server melakukan validasi ulang saat sinkronisasi.
-   Server mencegah transaksi yang sama diproses dua kali.
-   Server memeriksa kembali harga, status produk, dan stok.
-   Jika stok tidak cukup, transaksi masuk status konflik/gagal
    sinkronisasi dan tidak boleh diam-diam mengurangi stok hingga
    negatif.
-   Sistem menyediakan daftar transaksi yang perlu ditinjau.
-   Semua hasil sinkronisasi tercatat dalam audit log.

## 9.4 Batasan Pembayaran Offline

Transaksi offline dibatasi pada metode yang dapat dicatat tanpa
verifikasi gateway, terutama tunai. Metode lain hanya dapat digunakan
jika tenant secara eksplisit mengizinkan pencatatan offline dan memahami
bahwa verifikasi pembayarannya dilakukan di luar sistem. Pembayaran
gateway tidak tersedia offline.

------------------------------------------------------------------------

# 10. Persyaratan Perangkat Keras dan Kompatibilitas

Target perangkat:

-   PC Windows.
-   Laptop.
-   Tablet Android.
-   Smartphone Android.
-   iPhone/iPad melalui browser yang kompatibel.

Perangkat pendukung:

-   Scanner barcode USB.
-   Scanner barcode Bluetooth.
-   Kamera HP.
-   Printer thermal 58 mm.
-   Printer thermal 80 mm.
-   Printer USB, LAN, atau Bluetooth sesuai dukungan perangkat.
-   Cash drawer.

### Catatan kompatibilitas

Dukungan perangkat keras bergantung pada browser, sistem operasi,
driver, protokol, dan izin perangkat. Browser web tidak menjamin akses
universal ke semua printer USB/Bluetooth atau cash drawer.

Implementasi harus menguji kombinasi perangkat yang ditetapkan sebagai
perangkat tersertifikasi. Untuk perangkat yang tidak dapat diakses
langsung dari browser, pertimbangkan local print agent atau mekanisme
cetak yang kompatibel. Jangan menyatakan seluruh perangkat pasti
berfungsi tanpa pengujian.

------------------------------------------------------------------------

# 11. Persyaratan Non-Fungsional

## 11.1 Keamanan

-   Password di-hash menggunakan algoritma yang sesuai, bukan disimpan
    plaintext.
-   Seluruh koneksi menggunakan HTTPS.
-   Proteksi CSRF, XSS, SQL injection, session hijacking, dan brute
    force.
-   Validasi dan otorisasi dilakukan di sisi server.
-   Tenant ID tidak boleh dipercaya jika dikirim langsung dari browser;
    server harus menentukan tenant berdasarkan sesi dan hak akses.
-   Semua query data bisnis wajib dibatasi berdasarkan tenant.
-   Akses cabang harus diperiksa pada setiap operasi.
-   Kredensial integrasi dienkripsi dan dibatasi aksesnya.
-   Audit log untuk aktivitas sensitif.
-   Backup dan pemulihan data.
-   Pembatasan percobaan login dan manajemen sesi.

## 11.2 Performa

-   Halaman POS harus responsif dan ringan.
-   Pencarian produk dan scan barcode harus memberi respons cepat.
-   Query laporan besar harus menggunakan indeks, pagination, dan
    agregasi yang sesuai.
-   Proses berat dapat dijalankan secara asynchronous/queue bila
    infrastruktur mendukung.
-   Dashboard tidak boleh memuat seluruh data transaksi mentah
    sekaligus.

## 11.3 Reliabilitas dan Konsistensi

-   Transaksi penjualan dan perubahan stok harus atomik.
-   Posting jurnal dan transaksi sumber harus konsisten.
-   Idempotency untuk pembayaran, webhook, dan sinkronisasi offline.
-   Tidak boleh ada duplikasi transaksi akibat retry.
-   Backup database terjadwal dan prosedur restore diuji.
-   Kegagalan integrasi tidak boleh menghilangkan data transaksi lokal
    yang belum tersinkronisasi.

## 11.4 Kemudahan Penggunaan

-   Antarmuka responsif untuk desktop, tablet, dan HP.
-   POS dioptimalkan untuk penggunaan cepat.
-   Navigasi konsisten.
-   Form memiliki validasi dan pesan kesalahan yang jelas.
-   Status transaksi, stok, shift, dan sinkronisasi mudah dikenali.
-   Bahasa antarmuka MVP adalah Bahasa Indonesia.

## 11.5 Skalabilitas dan Pemeliharaan

-   PHP Native dengan arsitektur modular.
-   MySQL sebagai database utama.
-   Kode dipisahkan berdasarkan domain/modul.
-   Menggunakan Composer bila diperlukan untuk dependency management dan
    autoloading.
-   Routing, controller, service, repository, middleware, validator, dan
    policy dipisahkan secara terstruktur.
-   Migrasi database harus versioned dan dapat dijalankan dengan
    prosedur yang terdokumentasi.
-   Log aplikasi dan monitoring error tersedia.

------------------------------------------------------------------------

# 12. Rekomendasi Arsitektur Teknis

## 12.1 Stack

  -----------------------------------------------------------------------
  Lapisan                             Teknologi
  ----------------------------------- -----------------------------------
  Backend                             PHP Native

  Database                            MySQL

  Frontend                            HTML, CSS, JavaScript

  PWA                                 Web App Manifest dan Service Worker

  Penyimpanan offline POS             IndexedDB

  Integrasi langganan                 Midtrans

  Web server                          Apache/Nginx sesuai hosting

  Export                              Library PDF, Excel, dan CSV yang
                                      sesuai

  Barcode                             Kamera via getUserMedia,
                                      BarcodeDetector API, dan fallback
                                      JavaScript library
  -----------------------------------------------------------------------

## 12.2 Struktur Modular yang Disarankan

-   `Auth`
-   `Tenants`
-   `Branches`
-   `Users`
-   `Roles`
-   `Subscriptions`
-   `Billing`
-   `PlatformPayments`
-   `Products`
-   `Inventory`
-   `POS`
-   `CashShifts`
-   `Attendance`
-   `Customers`
-   `Suppliers`
-   `Purchases`
-   `Transfers`
-   `Returns`
-   `Finance`
-   `Accounting`
-   `Reports`
-   `Support`
-   `Notifications`
-   `AuditLogs`

Struktur direktori final mengikuti implementasi, tetapi pemisahan
tanggung jawab antar-modul harus dipertahankan.

## 12.3 Prinsip Multi-Tenant

-   Shared database/shared schema dapat digunakan pada tahap awal.
-   Tabel bisnis memiliki `tenant_id` jika datanya milik tenant.
-   Data cabang memiliki `branch_id` bila relevan.
-   Tenant context ditentukan oleh server berdasarkan autentikasi.
-   Query harus tenant-scoped secara konsisten.
-   Super Admin memiliki jalur akses platform yang diaudit.
-   Pengujian keamanan wajib membuktikan tenant A tidak dapat membaca
    atau mengubah data tenant B.

------------------------------------------------------------------------

# 13. Rancangan Entitas Database

Daftar berikut adalah rancangan awal entitas, bukan skema SQL final.
Nama kolom, tipe data, indeks, foreign key, dan normalisasi ditetapkan
pada tahap ERD dan desain database.

## 13.1 Platform dan Tenant

-   `platform_users`
-   `tenants`
-   `tenant_profiles`
-   `branches`
-   `tenant_settings`
-   `users`
-   `roles`
-   `permissions`
-   `role_permissions`
-   `user_roles`
-   `user_branch_access`

## 13.2 Paket, Demo, dan Langganan

-   `subscription_plans`
-   `plan_features`
-   `plan_limits`
-   `custom_plans`
-   `custom_plan_assignments`
-   `demo_settings`
-   `tenant_demos`
-   `demo_requests`
-   `demo_approvals`
-   `subscriptions`
-   `billing_invoices`
-   `billing_payments`
-   `billing_notifications`

## 13.3 Produk dan Persediaan

-   `categories`
-   `brands`
-   `products`
-   `product_variants`
-   `product_attributes`
-   `product_attribute_values`
-   `product_barcodes`
-   `product_serial_numbers`
-   `product_batches`
-   `product_units`
-   `unit_conversions`
-   `warehouses`
-   `inventory_balances`
-   `inventory_movements`
-   `inventory_batches`
-   `stock_opnames`
-   `stock_opname_items`
-   `stock_transfers`
-   `stock_transfer_items`

## 13.4 POS dan Shift

-   `sales`
-   `sale_items`
-   `sale_payments`
-   `payment_methods`
-   `cash_registers`
-   `cash_shifts`
-   `cash_shift_movements`
-   `offline_devices`
-   `offline_sync_logs`
-   `offline_conflicts`
-   `receipts`

## 13.5 Pelanggan, Supplier, dan Pembelian

-   `customers`
-   `customer_addresses`
-   `suppliers`
-   `purchase_orders`
-   `purchase_order_items`
-   `goods_receipts`
-   `goods_receipt_items`
-   `purchase_returns`
-   `purchase_return_items`

## 13.6 Retur dan Keuangan

-   `sales_returns`
-   `sales_return_items`
-   `cash_accounts`
-   `bank_accounts`
-   `cash_transactions`
-   `expenses`
-   `customer_receivables`
-   `supplier_payables`
-   `payment_allocations`
-   `bank_reconciliations`

## 13.7 Akuntansi

-   `chart_of_accounts`
-   `journal_entries`
-   `journal_entry_lines`
-   `financial_periods`
-   `accounting_settings`
-   `tax_rates`
-   `inventory_cost_layers` (jika dibutuhkan metode costing)
-   `journal_source_links`

## 13.8 Absensi dan Dukungan

-   `attendance_records` (rekap berbasis shift atau view/materialisasi
    yang sesuai)
-   `work_schedules` (opsional untuk pengembangan jadwal kerja)
-   `support_tickets`
-   `support_ticket_replies`
-   `support_ticket_attachments`

## 13.9 Sistem dan Audit

-   `notifications`
-   `announcements`
-   `audit_logs`
-   `system_settings`
-   `platform_payment_settings`

### Catatan desain database

-   Tabel tenant harus memiliki foreign key dan indeks yang sesuai.
-   Unique key untuk SKU/barcode/serial harus mempertimbangkan tenant
    dan aturan produk.
-   Transaksi penjualan, pembayaran, stok, dan jurnal harus diproses
    dalam transaksi database yang konsisten.
-   Data invoice menyimpan snapshot harga dan paket saat invoice dibuat.
-   Kredensial Midtrans tidak boleh disimpan sebagai teks biasa.
-   Data offline menggunakan UUID untuk idempotency.
-   Penghapusan data finansial harus dibatasi; gunakan status/soft
    delete sesuai kebutuhan audit.

------------------------------------------------------------------------

# 14. Edge Cases dan Penanganan Kegagalan

  -----------------------------------------------------------------------
  Kondisi                             Perilaku sistem yang diharapkan
  ----------------------------------- -----------------------------------
  Kasir belum membuka shift           Transaksi POS ditolak dan diarahkan
                                      untuk membuka shift.

  Kasir mencoba membuka shift kedua   Sistem menolak jika masih ada shift
                                      aktif yang bertentangan.

  Kasir lupa menutup shift            Shift ditandai masih terbuka dan
                                      ditampilkan kepada Manager/Owner
                                      untuk tindak lanjut.

  Saldo kas aktual berbeda            Sistem mencatat selisih dan
                                      mewajibkan catatan sesuai
                                      kebijakan.

  Stok tidak cukup                    Transaksi ditolak atau kuantitas
                                      disesuaikan; stok negatif tidak
                                      boleh terjadi.

  Barcode tidak dikenal               Tampilkan pesan dan opsi
                                      pencarian/input manual.

  Kamera tidak tersedia/ditolak       Sediakan scanner fisik atau input
                                      barcode manual.

  Browser tidak mendukung API barcode Gunakan library fallback atau
                                      metode scanner keyboard-wedge.

  Internet terputus saat POS          Simpan transaksi pada antrean lokal
                                      sesuai batasan offline.

  Transaksi offline dikirim dua kali  UUID/idempotency mencegah transaksi
                                      ganda.

  Stok berubah sebelum sinkronisasi   Server menandai konflik; tidak
                                      membuat stok negatif.

  Produk dihapus/nonaktif saat        Server memvalidasi ulang dan
  offline                             menandai konflik bila transaksi
                                      tidak lagi valid.

  Pembayaran Midtrans pending         Invoice tetap pending dan langganan
                                      belum aktif kecuali kebijakan
                                      khusus.

  Webhook Midtrans dikirim ulang      Pemrosesan idempotent, tidak
                                      menggandakan perpanjangan.

  Webhook tidak valid                 Abaikan/tolak dan catat log
                                      keamanan.

  Langganan kedaluwarsa               Akses operasional diblokir, data
                                      dipertahankan.

  Tenant mencoba mengakses tenant     Server menolak akses dan mencatat
  lain                                kejadian sesuai kebijakan keamanan.

  Jurnal tidak seimbang               Posting ditolak.

  Periode akuntansi sudah ditutup     Posting ditolak kecuali ada
                                      otorisasi dan prosedur pembukaan.

  Printer tidak kompatibel            Tampilkan opsi cetak browser atau
                                      mekanisme alternatif yang didukung.

  Demo berakhir                       Akses operasional diblokir dan
                                      Owner diarahkan ke kanal
                                      perpanjangan/dukungan.
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 15. Dashboard dan Navigasi

## 15.1 Navigasi Owner Tenant

Menu yang dapat ditampilkan berdasarkan permission:

-   Dashboard
-   POS
-   Shift Kasir
-   Produk
-   Kategori dan Brand
-   Inventori
-   Pembelian
-   Supplier
-   Pelanggan
-   Transfer Stok
-   Retur
-   Kas dan Keuangan
-   Akuntansi
-   Laporan
-   Cabang
-   Karyawan dan Pengguna
-   Paket dan Langganan
-   Tiket Bantuan
-   Pengaturan

Menu harus disesuaikan dengan paket dan hak akses pengguna.

## 15.2 Navigasi Super Admin

-   Dashboard Platform
-   Tenant
-   Demo
-   Paket Langganan
-   Paket Harga Khusus
-   Langganan
-   Invoice dan Pembayaran
-   Pengaturan Midtrans
-   Tiket Bantuan
-   Pengguna Platform
-   Pengumuman
-   Monitoring
-   Audit Log
-   Pengaturan Sistem

------------------------------------------------------------------------

# 16. Metrik Keberhasilan

Metrik yang disarankan untuk mengukur keberhasilan produk:

-   Jumlah tenant terdaftar.
-   Persentase tenant yang menyelesaikan onboarding.
-   Konversi demo menjadi langganan.
-   Tingkat perpanjangan langganan.
-   Jumlah transaksi POS per tenant.
-   Waktu rata-rata menyelesaikan transaksi.
-   Tingkat keberhasilan sinkronisasi offline.
-   Jumlah konflik sinkronisasi.
-   Akurasi stok.
-   Jumlah selisih kas per shift.
-   Waktu respons halaman POS.
-   Tingkat keberhasilan pembayaran langganan.
-   Jumlah tiket bantuan dan waktu penyelesaiannya.
-   Jumlah insiden isolasi data/keamanan (target nol).

Target numerik ditentukan setelah baseline penggunaan dan uji coba pilot
tersedia.

------------------------------------------------------------------------

# 17. Kriteria Penerimaan dan Definition of Done

Fitur dianggap selesai jika:

1.  Kebutuhan fungsional terkait telah diimplementasikan.
2.  Validasi input dan otorisasi server berjalan.
3.  Tenant dan cabang tidak dapat mengakses data di luar kewenangannya.
4.  Alur berhasil dan gagal telah diuji.
5.  Perubahan stok dan transaksi finansial konsisten.
6.  Jurnal double-entry seimbang.
7.  Shift wajib sebelum transaksi dan dapat ditutup dengan rekonsiliasi
    kas.
8.  Absensi kasir tercatat dari buka/tutup shift.
9.  Scan barcode kamera diuji pada perangkat/browser yang didukung.
10. POS offline diuji pada kondisi internet terputus dan pulih.
11. Sinkronisasi offline idempotent dan konflik ditangani.
12. Midtrans hanya memproses pembayaran langganan platform.
13. Kredensial Midtrans hanya dapat diatur Super Admin.
14. Webhook pembayaran diverifikasi dan aman terhadap duplikasi.
15. Laporan sesuai data transaksi sumber.
16. Audit log tersedia untuk aktivitas penting.
17. Dokumentasi teknis dan cara konfigurasi diperbarui.
18. Tidak ada error kritis atau kebocoran data pada pengujian.

------------------------------------------------------------------------

# 18. Risiko dan Mitigasi

  -----------------------------------------------------------------------
  Risiko                              Mitigasi
  ----------------------------------- -----------------------------------
  Kebocoran data antar-tenant         Tenant scoping di server, pengujian
                                      otorisasi, indeks dan constraint
                                      yang tepat.

  Konflik stok transaksi offline      Validasi server saat sinkronisasi,
                                      antrean konflik, larangan stok
                                      negatif.

  Duplikasi pembayaran langganan      Webhook signature validation dan
                                      idempotency.

  Ketidakcocokan scanner/printer      Matriks perangkat tersertifikasi
                                      dan fallback.

  Kesalahan jurnal akuntansi          Posting melalui service terpusat,
                                      validasi debit-kredit, pengujian
                                      skenario transaksi.

  Performa laporan lambat             Indeks, pagination, query agregat,
                                      dan proses asynchronous.

  Penyalahgunaan akun                 MFA dapat dipertimbangkan, rate
                                      limit, audit log, dan kebijakan
                                      sesi.

  Kehilangan data                     Backup terjadwal, pemantauan, dan
                                      uji restore.
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 19. Asumsi dan Keputusan yang Menjadi Baseline

Keputusan berikut telah ditetapkan dalam PRD ini:

1.  Produk digunakan untuk retail UMKM sampai perusahaan menengah dan
    besar.
2.  Mendukung multi-tenant dan multi-cabang.
3.  Platform berupa Web App + PWA.
4.  Backend menggunakan PHP Native dan database MySQL.
5.  Bahasa aplikasi MVP adalah Bahasa Indonesia.
6.  Semua kelompok fitur utama yang ditetapkan masuk ruang lingkup MVP.
7.  Semua jenis produk yang dibahas didukung: produk biasa, varian,
    serial/IMEI, batch/expiry, konversi unit, dan produk timbang.
8.  Paket standar adalah Starter, Business, dan Enterprise.
9.  Paket harga khusus dikelola secara terpisah.
10. Demo awal default satu hari dan durasinya dapat diubah Super Admin.
11. Super Admin dapat memberikan demo/perpanjangan berkali-kali.
12. Setelah demo/langganan berakhir, akses operasional diblokir dan data
    tidak otomatis dihapus.
13. Pembayaran transaksi toko mendukung split/multiple payment.
14. Stok negatif dilarang.
15. Offline hanya untuk POS.
16. Perangkat target meliputi PC, laptop, tablet, dan smartphone.
17. Barcode dapat dipindai menggunakan scanner fisik maupun kamera HP.
18. Midtrans hanya digunakan untuk langganan platform.
19. Pembayaran pelanggan toko tidak menggunakan integrasi Midtrans.
20. Kredensial Midtrans platform hanya dapat dikelola Super Admin
    melalui dashboard platform.
21. Akuntansi double-entry dan laporan terperinci masuk MVP.
22. Absensi kasir dicatat melalui aktivitas buka dan tutup shift.
23. Kasir wajib membuka shift sebelum transaksi dan menutup shift
    setelah selesai.
24. Dukungan platform menggunakan Email dan Tiket Bantuan.
25. Satu brand platform digunakan bersama; tenant dapat menyesuaikan
    logo/identitas pada profil dan struk, bukan white-label penuh.

------------------------------------------------------------------------

# 20. Hal yang Perlu Diputuskan pada Tahap Desain Teknis

PRD ini telah memiliki keputusan produk yang cukup untuk menjadi
baseline. Hal-hal berikut merupakan detail implementasi yang harus
dipastikan pada ERD, spesifikasi teknis, dan pengujian, bukan penghalang
penyusunan PRD:

-   Detail batas fitur dan kuota setiap paket Starter, Business,
    Enterprise.
-   Harga dan aturan penawaran paket khusus.
-   Kebijakan pajak dan format faktur sesuai kebutuhan bisnis.
-   Detail COA default dan pemetaan akun untuk setiap jenis transaksi.
-   Finalisasi metode costing persediaan (rekomendasi awal Moving
    Weighted Average).
-   Kebijakan retur, refund, dan pembatalan setelah jurnal diposting.
-   Detail hak akses untuk persetujuan tutup shift dan koreksi kas.
-   Masa berlaku cache dan sesi perangkat POS offline.
-   Daftar kombinasi browser, printer, scanner, dan cash drawer yang
    diuji.
-   Kebijakan retensi dan penghapusan data tenant.
-   Detail SLA, backup, dan target pemulihan layanan.

------------------------------------------------------------------------

## Penutup

Dokumen PRD versi 1.3 ini menjadi baseline kebutuhan produk POS Retail
Web Application. Perubahan terhadap ruang lingkup, aturan bisnis,
integrasi, atau hak akses setelah dokumen ini disetujui harus dicatat
sebagai revisi PRD agar implementasi, pengujian, dan pengembangan tetap
konsisten.
