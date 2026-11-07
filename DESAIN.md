# Design Brief --- POS Retail Web Application

## Dasar Dokumen

-   **Dokumen acuan:** `PRD_POS_Retail_Web_Application_v1.3.md`
-   **Platform:** Web App + Progressive Web App (PWA)
-   **Bahasa antarmuka:** Bahasa Indonesia
-   **Stack yang ditetapkan PRD:** PHP Native, MySQL, HTML, CSS,
    JavaScript
-   **Model produk:** SaaS multi-tenant dan multi-cabang
-   **Arah brand dari klarifikasi:** Friendly & Approachable
-   **Referensi visual:** Linear --- minimal, rapi, fokus pada konten
    dan alur kerja
-   **Perangkat:** Responsive seimbang untuk desktop, laptop, tablet,
    dan smartphone
-   **Tema:** Dual theme (light dan dark)
-   **Aksesibilitas:** Advanced; memperhatikan kebutuhan visual dan
    motorik

Dokumen ini menerjemahkan kebutuhan PRD menjadi arahan visual dan
interaksi. Dokumen ini **tidak menambahkan halaman, modul, atau fitur
produk baru**. Jika PRD belum menetapkan keputusan visual atau perilaku
tertentu, hal tersebut ditandai pada bagian **Open Questions** dan tidak
dianggap sebagai keputusan final.

------------------------------------------------------------------------

## 1. Tujuan Desain dan Kesan yang Ingin Dibangun

### 1.1 Tujuan desain

1.  Membuat aplikasi POS retail yang mudah dipahami oleh pengguna dengan
    tingkat pengalaman digital yang beragam, dari kasir hingga owner dan
    pengelola platform.
2.  Membantu kasir menyelesaikan transaksi dengan cepat, minim langkah
    yang tidak perlu, serta tetap memahami status shift, pembayaran, dan
    sinkronisasi.
3.  Menyajikan data operasional dan keuangan yang kompleks secara
    terstruktur tanpa membuat layar terasa padat.
4.  Menjaga konsistensi antarmuka di desktop, laptop, tablet, dan
    smartphone.
5.  Memastikan pengguna dapat mengenali status, tindakan, validasi, dan
    konsekuensi tindakan penting.
6.  Menyediakan tema terang dan gelap dengan hierarki visual serta
    keterbacaan yang setara.
7.  Menerapkan aksesibilitas tingkat lanjut, terutama kontras, fokus
    keyboard, target sentuh, struktur informasi, dan dukungan pengguna
    dengan keterbatasan visual atau motorik.

### 1.2 Kesan brand

**Friendly & Approachable** menjadi karakter utama. Aplikasi harus
terasa ramah, tenang, jelas, dan tidak mengintimidasi, tetapi tetap
profesional untuk mengelola transaksi serta data bisnis.

Karakter visual: - Sederhana dan tidak dekoratif berlebihan. - Ramah
melalui bahasa yang jelas, label yang mudah dimengerti, dan umpan balik
yang membantu. - Terstruktur dan konsisten seperti referensi Linear. -
Mengutamakan informasi dan tugas utama daripada ornamen. - Menggunakan
aksen warna secara terukur untuk membantu orientasi dan status.

### 1.3 Prinsip pengalaman

-   **Task first:** tindakan utama pada halaman harus mudah ditemukan.
-   **Progressive disclosure:** detail lanjutan ditampilkan saat
    dibutuhkan, bukan seluruhnya sekaligus.
-   **Clear feedback:** setiap proses penting memiliki status yang
    terlihat.
-   **Predictable interaction:** pola tombol, form, tabel, dialog, dan
    navigasi konsisten.
-   **Safe operation:** tindakan yang berdampak pada transaksi, stok,
    kas, dan jurnal harus memiliki konfirmasi atau perlindungan sesuai
    aturan PRD.
-   **Responsive by design:** layout menyesuaikan ruang layar tanpa
    menghilangkan fungsi yang diwajibkan PRD.

------------------------------------------------------------------------

## 2. Palet Warna dan Penggunaannya

Palet berikut adalah **usulan awal desain**, bukan warna brand yang
telah ditetapkan dalam PRD. Warna harus diuji kontrasnya pada kedua tema
sebelum implementasi final.

### 2.1 Light theme

  ------------------------------------------------------------------------
  Token                                          HEX Penggunaan
  --------------------- ---------------------------- ---------------------
  `primary`                                `#3659D9` Aksi utama, tautan
                                                     penting, elemen
                                                     navigasi aktif

  `primary-hover`                          `#2948BD` Hover/pressed pada
                                                     aksi utama

  `primary-subtle`                         `#EEF2FF` Latar seleksi ringan
                                                     dan sorotan informasi
                                                     terkait primary

  `background`                             `#F7F8FA` Latar area aplikasi

  `surface`                                `#FFFFFF` Kartu, panel, tabel,
                                                     form, dialog

  `surface-muted`                          `#F1F3F6` Area sekunder, baris
                                                     terpilih ringan,
                                                     field nonaktif

  `text-primary`                           `#182230` Teks utama dan
                                                     heading

  `text-secondary`                         `#536174` Deskripsi, label
                                                     sekunder

  `text-muted`                             `#68778A` Caption dan metadata;
                                                     tetap wajib lolos
                                                     kontras

  `border`                                 `#DCE2EA` Garis pembatas,
                                                     border input, pemisah
                                                     tabel

  `success`                                `#16804A` Berhasil, aktif,
                                                     lunas, sinkron

  `success-subtle`                         `#EAF7EF` Latar status berhasil

  `warning`                                `#A85B08` Perlu perhatian, stok
                                                     menipis, status
                                                     menunggu

  `warning-subtle`                         `#FFF4E5` Latar peringatan

  `danger`                                 `#C33737` Error, gagal, stok
                                                     tidak cukup, tindakan
                                                     destruktif

  `danger-subtle`                          `#FDECEC` Latar error

  `info`                                   `#1769AA` Informasi dan status
                                                     netral yang
                                                     membutuhkan perhatian

  `info-subtle`                            `#EAF4FC` Latar informasi
  ------------------------------------------------------------------------

### 2.2 Dark theme

  Token                      HEX Penggunaan
  ------------------ ----------- ----------------------------------
  `primary`            `#91A7FF` Aksi utama dan tautan
  `primary-hover`      `#B0BEFF` Hover/pressed pada aksi utama
  `primary-subtle`     `#26345F` Sorotan dan seleksi ringan
  `background`         `#10141C` Latar utama
  `surface`            `#191F2A` Kartu, panel, tabel, dialog
  `surface-muted`      `#222B38` Area sekunder dan field nonaktif
  `text-primary`       `#F1F4F9` Teks utama dan heading
  `text-secondary`     `#C0CAD8` Deskripsi dan label sekunder
  `text-muted`         `#A3B0C2` Caption dan metadata
  `border`             `#344052` Garis pembatas dan border
  `success`            `#65D695` Status berhasil
  `success-subtle`     `#1D3A2B` Latar status berhasil
  `warning`            `#FFC078` Status peringatan
  `warning-subtle`     `#49351F` Latar peringatan
  `danger`             `#FF8585` Error dan tindakan destruktif
  `danger-subtle`      `#482729` Latar error
  `info`               `#83C5F4` Status informasi
  `info-subtle`        `#1D3548` Latar informasi

### 2.3 Aturan penggunaan warna

-   Warna primary hanya untuk aksi utama, tautan penting, dan penanda
    navigasi aktif. Jangan mewarnai semua tombol dengan warna primary.
-   Warna success, warning, danger, dan info menunjukkan makna status;
    jangan menggunakannya sebagai dekorasi.
-   Status tidak boleh dibedakan melalui warna saja. Sertakan teks, ikon
    yang bermakna, atau label status.
-   Warna teks sekunder dan muted tetap harus memenuhi kontras terhadap
    latar.
-   Hindari penggunaan warna status sebagai latar penuh halaman.
-   Grafik laporan harus memiliki pembeda yang tetap terbaca pada kedua
    tema dan tidak bergantung pada warna saja.
-   Jangan menggunakan nilai warna di luar token tanpa alasan dan
    pencatatan pada sistem desain.

------------------------------------------------------------------------

## 3. Tipografi

### 3.1 Font

**Usulan font utama: Inter**, dengan fallback
`system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.

Alasan: bentuk huruf netral, mudah dibaca pada ukuran antarmuka, dan
sesuai dengan karakter minimalis. Pemilihan ini merupakan rekomendasi
desain, bukan ketentuan PRD.

### 3.2 Skala tipografi

  Elemen                              Ukuran desktop   Ukuran mobile   Weight   Line height
  --------------------------------- ---------------- --------------- -------- -------------
  Display / angka utama dashboard              32 px           28 px      600           1.2
  H1 / judul halaman                           28 px           24 px      600          1.25
  H2 / judul bagian                            22 px           20 px      600           1.3
  H3 / judul panel                             18 px           18 px      600          1.35
  Body utama                               14--16 px           16 px      400           1.5
  Body sekunder                                14 px       14--16 px      400           1.5
  Label form                                   14 px       14--16 px      500           1.4
  Caption / metadata                       12--13 px       13--14 px      400          1.45
  Angka POS / total transaksi              24--32 px       24--28 px      600           1.2

### 3.3 Aturan tipografi

-   Gunakan sentence case untuk judul, label, dan tombol.
-   Hindari paragraf panjang pada halaman operasional; pecah menjadi
    bagian yang mudah dipindai.
-   Angka uang, kuantitas, SKU, dan nomor transaksi menggunakan
    alignment yang konsisten; angka tabel sebaiknya rata kanan.
-   Gunakan angka tabular jika font mendukung agar angka pada tabel dan
    total tidak bergeser.
-   Jangan menggunakan caption kecil untuk informasi yang dibutuhkan
    dalam pengambilan keputusan.
-   Teks tombol harus menyebut tindakan, misalnya "Simpan perubahan",
    bukan label yang ambigu.
-   Format mata uang, tanggal, waktu, dan nomor mengikuti pengaturan
    tenant sebagaimana ditetapkan PRD.

------------------------------------------------------------------------

## 4. Spacing, Grid, dan Radius Sudut

### 4.1 Spacing scale

Gunakan basis 4 px:

  Token          Nilai Penggunaan umum
  ------------ ------- -----------------------------------
  `space-1`       4 px Jarak ikon dan teks
  `space-2`       8 px Jarak antar elemen rapat
  `space-3`      12 px Jarak label dan kontrol
  `space-4`      16 px Padding kontrol dan jarak standar
  `space-5`      20 px Jarak antar kelompok kecil
  `space-6`      24 px Padding panel dan kelompok konten
  `space-8`      32 px Jarak antarbagian
  `space-10`     40 px Jarak section besar
  `space-12`     48 px Pemisah area utama

### 4.2 Grid dan layout

-   Gunakan grid 12 kolom pada desktop untuk dashboard dan halaman data
    yang membutuhkan beberapa panel.
-   Gunakan layout fleksibel pada halaman POS; area katalog/hasil
    pencarian dan keranjang mengikuti prioritas transaksi yang
    ditetapkan PRD.
-   Pada tablet, sederhanakan kolom menjadi layout yang lebih ringkas
    tanpa menyembunyikan aksi utama.
-   Pada smartphone, gunakan satu kolom untuk form dan data yang tidak
    cocok ditampilkan berdampingan.
-   Tabel lebar dapat menggunakan scroll horizontal atau tampilan
    ringkas yang tetap mempertahankan akses ke data dan tindakan
    penting.
-   Pertahankan padding konten yang konsisten: 24 px desktop, 20 px
    tablet, 16 px smartphone sebagai titik awal.
-   Lebar maksimum konten bukan keputusan produk; sesuaikan dengan jenis
    halaman dan kepadatan data.

### 4.3 Radius sudut

  ------------------------------------------------------------------------
  Token                                        Nilai Penggunaan
  --------------------- ---------------------------- ---------------------
  `radius-sm`                                   6 px Badge, elemen kecil

  `radius-md`                                   8 px Input, tombol,
                                                     dropdown

  `radius-lg`                                  12 px Card, panel

  `radius-xl`                                  16 px Dialog atau panel
                                                     besar

  `radius-full`                               999 px Avatar dan chip
                                                     berbentuk pil jika
                                                     diperlukan
  ------------------------------------------------------------------------

Gunakan radius secara konsisten. Hindari radius terlalu besar pada
setiap elemen karena dapat membuat aplikasi operasional terasa kurang
padat dan tidak efisien.

------------------------------------------------------------------------

## 5. Komponen UI Beserta State

Daftar ini hanya memvisualisasikan kontrol dan pola interaksi yang
dibutuhkan modul dalam PRD; bukan penambahan fitur baru.

### 5.1 Tombol

  ---------------------------------------------------------------------------------
  Jenis       Penggunaan     Default     Hover / pressed Disabled    Error
  ----------- -------------- ----------- --------------- ----------- --------------
  Primary     Aksi utama     Latar       Warna primary   Kontras     Error
              halaman/form   primary,    lebih kuat;     tetap       disampaikan
                             teks        pressed         terbaca,    melalui pesan
                             kontras     memiliki        tanpa       terpisah;
                                         feedback        interaksi   tombol tidak
                                                                     berubah
                                                                     menjadi
                                                                     satu-satunya
                                                                     indikator

  Secondary   Aksi           Surface     Latar           Teks dan    Tidak berlaku
              alternatif     dengan      surface-muted   border      sebagai state
                             border                      tetap       mandiri
                                                         terbaca     

  Tertiary /  Aksi ringan    Teks        Latar subtle    Teks muted  Tidak berlaku
  text                       primary                     dengan      sebagai state
                                                         kontras     mandiri
                                                         memadai     

  Danger      Hapus,         Latar       Warna danger    Tidak dapat Konfirmasi dan
              batalkan, atau danger      lebih kuat      ditekan     alasan
              tindakan                                               kegagalan
              destruktif                                             dijelaskan
              yang memang                                            dengan teks
              ada di PRD                                             
  ---------------------------------------------------------------------------------

Aturan: - Satu area tindakan utama sebaiknya memiliki satu tombol
primary. - Jangan mengandalkan ikon tanpa label untuk tindakan yang
berisiko atau tidak umum. - State loading menampilkan indikator proses
dan mencegah pengiriman berulang. - Tombol disabled harus tetap dapat
dibaca; jelaskan syarat yang belum terpenuhi jika tidak jelas.

### 5.2 Input dan form

Termasuk input teks, angka, nominal uang, pencarian, tanggal, pilihan,
textarea, dan unggah lampiran bila diperlukan oleh modul PRD.

  -----------------------------------------------------------------------
  State                               Perlakuan
  ----------------------------------- -----------------------------------
  Default                             Label terlihat, border netral,
                                      placeholder hanya sebagai contoh

  Focus                               Border/focus ring jelas dan tidak
                                      hanya perubahan warna tipis

  Filled                              Nilai mudah dibaca; label tetap
                                      terlihat

  Hover                               Perubahan halus pada border jika
                                      pointer tersedia

  Disabled                            Latar muted, teks tetap terbaca,
                                      tidak dapat diedit

  Read-only                           Tampilan berbeda dari disabled dan
                                      tetap dapat dipilih/disalin bila
                                      relevan

  Error                               Border danger, pesan spesifik di
                                      dekat field, hubungan pesan dengan
                                      field secara programatis

  Success / valid                     Indikator validasi hanya jika
                                      bermanfaat; jangan memberi dekorasi
                                      berlebihan
  -----------------------------------------------------------------------

Aturan: - Label tidak boleh digantikan placeholder. - Tampilkan format
atau satuan nominal secara konsisten. - Validasi menjelaskan cara
memperbaiki input, bukan hanya "Tidak valid". - Pesan error tidak
menghapus input yang sudah benar. - Kelompokkan field berdasarkan
konteks bisnis yang memang ada dalam PRD.

### 5.3 Navigasi dan menu

-   Menu Owner Tenant dan Super Admin mengikuti daftar navigasi pada
    PRD.
-   Menu ditampilkan berdasarkan role, permission, paket, dan konteks
    akses.
-   Item aktif memiliki penanda visual dan teks yang jelas.
-   Menu yang tidak berhak diakses tidak ditampilkan sebagai pilihan
    aktif.
-   Pada layar kecil, navigasi mengikuti pola responsif yang
    mempertahankan akses ke modul; bentuk akhir drawer/menu perlu diuji.
-   State: default, hover/focus, active, expanded, dan disabled bila
    relevan.

### 5.4 Tabel dan daftar data

Digunakan untuk produk, varian, inventori, transaksi, pelanggan,
supplier, pembelian, transfer, retur, kas, jurnal, laporan, pengguna,
tenant, invoice, tiket, dan audit log sesuai PRD.

State: - Default dengan header kolom jelas. - Hover baris hanya sebagai
bantuan orientasi, bukan satu-satunya penanda. - Selected dengan penanda
yang dapat dikenali. - Loading dengan skeleton atau indikator proses
yang tidak mengubah struktur tabel secara drastis. - Empty dengan
penjelasan bahwa belum ada data dan tindakan yang memang tersedia pada
modul tersebut. - Error dengan pesan dan opsi mencoba kembali jika
sesuai. - Disabled untuk aksi yang tidak diizinkan atau tidak memenuhi
aturan bisnis.

Aturan: - Informasi penting tidak boleh hanya muncul ketika hover. -
Nominal dan angka rata kanan; teks rata kiri. - Status ditampilkan
dengan label teks dan warna status. - Tindakan per baris harus tetap
mudah dijangkau pada layar sentuh. - Gunakan pagination/filter hanya
sesuai kebutuhan laporan atau daftar dalam PRD; detail pola kontrol
perlu konsisten.

### 5.5 Status dan badge

Status yang perlu divisualisasikan sesuai PRD antara lain status shift,
transaksi, stok, sinkronisasi offline, invoice/langganan, tiket bantuan,
transfer, dan retur.

-   Gunakan teks status eksplisit.
-   Warna success untuk status berhasil/selesai yang sesuai.
-   Warning untuk pending, menunggu, atau perlu perhatian.
-   Danger untuk gagal, ditolak, atau kondisi kritis.
-   Info untuk status informatif.
-   Status tidak boleh bergantung pada warna saja.

### 5.6 Dialog, konfirmasi, dan notifikasi

Gunakan dialog untuk konfirmasi tindakan penting yang memang ada dalam
alur PRD, seperti pembatalan/retur, koreksi, atau tindakan sensitif.
Jangan menambahkan konfirmasi pada setiap tindakan sederhana.

-   Dialog memiliki judul yang menjelaskan tindakan.
-   Dampak tindakan dijelaskan singkat.
-   Tombol konfirmasi menyebut tindakan spesifik.
-   Tombol batal mudah ditemukan.
-   Fokus keyboard masuk ke dialog dan dikembalikan ke pemicu saat
    dialog ditutup.
-   Notifikasi singkat tidak menggantikan pesan error yang perlu dibaca
    atau ditindaklanjuti.

### 5.7 POS, shift, pembayaran, dan sinkronisasi

-   Area transaksi mengutamakan pencarian/scan produk, keranjang, total,
    dan tindakan pembayaran.
-   Status shift aktif harus mudah dikenali.
-   Jika shift belum dibuka, tampilkan arahan untuk membuka shift
    sebelum transaksi; jangan memberi kesan transaksi dapat dilanjutkan.
-   Pembayaran kombinasi memperlihatkan alokasi per metode dan sisa
    tagihan secara jelas.
-   Metode pembayaran toko ditampilkan sebagai pencatatan manual sesuai
    PRD; jangan menampilkan Midtrans pada checkout POS.
-   Status offline dan jumlah antrean sinkronisasi harus terlihat dan
    dapat dibedakan dari status transaksi berhasil tersinkron.
-   Konflik sinkronisasi harus disampaikan sebagai kondisi yang perlu
    ditinjau, bukan disamarkan sebagai sukses.

### 5.8 Komponen visualisasi data

Dashboard dan laporan menggunakan kartu ringkasan, tabel, dan
visualisasi yang relevan dengan data PRD. Jenis grafik, pemetaan metrik
ke grafik, dan rentang data default belum ditetapkan PRD; lihat Open
Questions.

------------------------------------------------------------------------

## 6. Layout Tiap Halaman Utama dalam PRD

Bagian ini memetakan halaman berdasarkan alur, modul, dan navigasi yang
disebutkan dalam PRD. Nama di bawah merupakan kelompok halaman/area yang
diperlukan untuk menyajikan modul tersebut, bukan usulan penambahan
halaman produk di luar PRD. Struktur URL, pemecahan subhalaman, dan
komposisi final perlu dikonfirmasi pada desain teknis.

### 6.1 Landing page, Login, dan Register

**Landing page** - Menyajikan informasi produk POS, pilihan
Login/Register, dan informasi paket jika ditampilkan oleh platform. -
Hierarki konten: identitas platform, ringkasan manfaat, informasi
paket/CTA yang memang disediakan, dan akses akun. - Detail section
landing page tidak dijabarkan dalam PRD; jangan menambah section
pemasaran tanpa persetujuan.

**Login** - Form autentikasi ringkas. - Akses reset password. - Pesan
validasi dan kegagalan autentikasi yang jelas. - Jangan menampilkan
kontrol atau informasi internal platform yang tidak diperlukan pengguna.

**Register** - Form data Owner dan bisnis: nama Owner, email,
telepon/WhatsApp, password, nama bisnis, jenis bisnis, alamat bisnis. -
Susun dalam kelompok yang mudah dipahami. - Jelaskan kesalahan validasi
pada field terkait. - Setelah berhasil, tampilkan umpan balik bahwa
tenant, cabang utama, dan demo sedang/disiapkan sesuai alur PRD.

### 6.2 Area aplikasi Tenant

**Kerangka aplikasi** - Navigasi modul Owner Tenant mengikuti daftar
PRD. - Area header/konteks menampilkan identitas bisnis dan cabang aktif
sesuai akses. - Area konten utama menampilkan judul halaman,
konteks/filter yang relevan, konten, dan aksi utama. - Identitas
tenant/cabang dan permission harus jelas agar pengguna memahami ruang
lingkup data yang sedang dilihat.

**Dashboard Owner Tenant** - Susun ringkasan penjualan, laba
kotor/indikator laba-rugi, stok, produk terlaris, penjualan cabang,
performa kasir, shift aktif, kas/bank, piutang/utang, laporan keuangan,
dan status langganan. - Prioritaskan ringkasan yang paling sering
dipantau, kemudian detail melalui tautan/drill-down yang memang didukung
PRD. - Jangan menampilkan kredensial Midtrans platform di area Owner. -
Susunan, urutan, dan visualisasi tiap metrik belum ditetapkan PRD.

**POS** - Halaman berorientasi tugas dengan pencarian/scan produk, hasil
produk, keranjang, pelanggan, diskon/pajak sesuai izin, catatan, total,
dan pembayaran. - Tampilkan konteks cabang, register, kasir, dan status
shift. - Tampilkan status koneksi dan antrean offline saat relevan. -
Pembayaran kombinasi memperlihatkan nominal setiap metode serta sisa
tagihan. - Struk/aksi cetak atau kirim mengikuti kemampuan yang
ditetapkan PRD. - Pada smartphone, susun ulang area agar tindakan
transaksi tetap mudah dijangkau; jangan menghilangkan informasi wajib.

**Shift Kasir** - Tampilkan status shift (belum dibuka/aktif/ditutup)
dan ringkasan yang relevan. - Buka shift: pilihan cabang/register sesuai
akses dan input saldo awal. - Shift aktif: ringkasan aktivitas kas dan
transaksi terkait. - Tutup shift: kas yang diharapkan, kas aktual,
selisih, dan catatan. - Informasi absensi berasal dari aktivitas
buka/tutup shift, bukan form check-in/check-out tambahan.

**Produk, Kategori dan Brand** - Area daftar data dengan
pencarian/filter yang relevan, status, dan aksi yang diizinkan. - Form
produk harus mengakomodasi data produk dan tipe khusus dalam PRD:
varian, SKU/barcode, serial/IMEI, batch/expiry, konversi unit, produk
timbang, dan bundle jika diaktifkan. - Jangan memaksa semua field tampil
sekaligus untuk setiap tipe produk; tampilkan field yang relevan dengan
tipe/konfigurasi produk. - Detail urutan field dan pola form bertahap
belum ditentukan PRD.

**Inventori** - Daftar stok per cabang/lokasi, kartu stok, pergerakan,
penyesuaian, stock opname, stok minimum, batch/serial, dan nilai
persediaan. - Bedakan stok tersedia, stok minimum, dan kondisi stok lain
yang memang didefinisikan sistem. - Penyesuaian atau transaksi yang
mengurangi stok harus menonjolkan validasi stok dan larangan stok
negatif.

**Pembelian dan Supplier** - Daftar supplier dan proses purchase
request/order, penerimaan barang, pembelian tunai/kredit, retur
pembelian, biaya, utang, dan pembayaran utang. - Alur visual perlu
memperjelas status dokumen dan keterkaitan order dengan penerimaan. -
Status rinci selain yang secara eksplisit ada di PRD perlu dikonfirmasi.

**Pelanggan** - Daftar pelanggan dan detail pelanggan dengan
kontak/alamat, riwayat pembelian, total belanja, segmentasi, piutang,
dan catatan. - Hindari menampilkan data sensitif secara berlebihan pada
daftar ringkas.

**Transfer Stok dan Retur** - Daftar transfer dengan status draft,
dikirim, diterima, dan dibatalkan. - Detail transfer menampilkan
asal/tujuan, item, kuantitas, dan status sesuai data PRD. - Retur
penjualan dan pembelian memperlihatkan item, kondisi pengembalian stok,
serta pengembalian dana/kredit pelanggan sesuai proses yang dipilih. -
Aturan detail approval dan refund yang belum diputuskan tetap menjadi
Open Question.

**Kas dan Keuangan** - Kelompokkan kas/rekening bank, kas masuk/keluar,
kategori biaya, penerimaan/pembayaran, piutang/utang, rekonsiliasi, arus
kas, bukti transaksi, dan riwayat pembayaran. - Ringkasan nominal harus
memiliki periode dan ruang lingkup cabang yang jelas.

**Akuntansi** - Navigasi konten mengikuti komponen PRD: COA, jurnal
umum, buku besar, neraca saldo, laba rugi, neraca, arus kas,
piutang/utang, HPP, pajak, periode, tutup buku, jurnal
penyesuaian/pembalik, dan rekonsiliasi. - Jurnal menampilkan debit dan
kredit secara jelas serta total keseimbangan. - Jurnal yang sudah
diposting ditampilkan sebagai data yang tidak dapat diedit langsung;
koreksi melalui mekanisme jurnal pembalik/penyesuaian. - Hindari
menyederhanakan data akuntansi sampai kehilangan konteks debit-kredit
atau referensi sumber.

**Laporan** - Sediakan konteks periode dan filter yang relevan untuk
tiap jenis laporan. - Ringkasan laporan mengarah ke tabel/detail dan
drill-down transaksi sumber sesuai PRD. - Ekspor PDF, Excel, dan CSV
ditempatkan pada konteks laporan terkait. - Laporan penjualan,
persediaan, pembelian, kas/shift, absensi, pelanggan, keuangan, dan
akuntansi harus dapat dibedakan dengan jelas.

**Cabang, Karyawan dan Pengguna** - Daftar cabang/pengguna dengan
informasi status dan cakupan akses. - Form cabang memuat informasi
cabang sesuai PRD. - Pengelolaan pengguna menampilkan role, permission,
dan akses cabang sesuai kewenangan. - Jangan menampilkan permission yang
tidak dapat diberikan oleh pengguna saat ini.

**Paket dan Langganan** - Menampilkan paket aktif, periode, batas/fitur
yang relevan, invoice, riwayat pembayaran, dan status demo/langganan. -
Saat demo/langganan berakhir, tampilkan status pemblokiran operasional
dan kanal/tautan pembayaran atau dukungan yang ditentukan platform. -
Pembayaran langganan menggunakan Midtrans sesuai alur PRD. - Detail
komparasi paket dan informasi harga mengikuti konfigurasi aktual, bukan
angka contoh yang dibuat desainer.

**Tiket Bantuan** - Daftar tiket dan detail percakapan dengan nomor,
kategori, prioritas, subjek, deskripsi, lampiran, status, petugas, dan
riwayat. - Status minimum: Open, In Progress, Waiting for User,
Resolved, Closed. - Owner dapat melihat dan membalas tiket; Super
Admin/support mengelola dan menugaskan tiket sesuai izin.

### 6.3 Area aplikasi Super Admin

**Kerangka Super Admin** - Navigasi mengikuti daftar Super Admin pada
PRD. - Bedakan secara jelas konteks platform dengan konteks tenant. -
Aksi platform yang berdampak luas harus memiliki penjelasan ruang
lingkup.

**Dashboard Platform** - Menyajikan metrik platform sesuai data yang
tersedia. - Detail metrik, urutan kartu, grafik, dan filter belum
ditentukan PRD.

**Tenant** - Daftar dan detail tenant; informasi status akun dan konteks
pengelolaan yang relevan. - Detail tindakan administratif harus
mengikuti permission dan audit log.

**Demo** - Form pengaturan demo, durasi, paket basis, fitur/batas,
kriteria, pemberian/perpanjangan, dan catatan alasan. - Riwayat
pemberian demo harus dapat ditelusuri.

**Paket Langganan dan Paket Harga Khusus** - Daftar paket standar
Starter, Business, Enterprise, dan paket khusus terpisah. - Form harga,
periode, batas, fitur, kapasitas, dukungan/SLA sesuai konfigurasi. -
Jangan menampilkan paket khusus sebagai paket publik kecuali keputusan
bisnis menyatakannya.

**Langganan, Invoice dan Pembayaran** - Menampilkan status langganan,
invoice, pembayaran, dan riwayat sesuai PRD. - Status pembayaran harus
dibedakan secara tekstual dan visual. - Pembayaran pending tidak boleh
terlihat sebagai langganan aktif.

**Pengaturan Midtrans** - Hanya untuk Super Admin. - Form Client Key,
Server Key, Merchant ID, environment, URL webhook, status, pengujian
koneksi, log notifikasi/transaksi. - Server Key harus disamarkan setelah
disimpan dan tidak pernah ditampilkan utuh. - Status integrasi dan
webhook perlu dibedakan dari status pembayaran.

**Tiket Bantuan** - Daftar, detail, percakapan, penugasan, prioritas,
dan perubahan status sesuai kewenangan support/Super Admin.

**Pengguna Platform, Pengumuman, Monitoring, Audit Log, Pengaturan
Sistem** - Susun daftar dan detail sesuai data/aksi yang ditetapkan
PRD. - Monitoring kesehatan aplikasi dan audit log harus mengutamakan
keterbacaan informasi dan waktu kejadian. - Detail layout dan komponen
tiap area yang belum diuraikan PRD harus ditetapkan saat desain rinci,
tanpa menambah fungsi baru.

### 6.4 Halaman/keadaan sistem yang mengikuti alur PRD

-   **Reset password:** akses dari Login; rincian tahapan dan metode
    verifikasi belum dijelaskan PRD.
-   **Invoice/pembayaran langganan:** halaman yang diperlukan untuk
    menyelesaikan alur pembayaran subscription; detail layout metode
    pembayaran mengikuti integrasi Midtrans.
-   **Demo/langganan berakhir:** keadaan akses operasional diblokir,
    data tetap tersimpan, dan pengguna diarahkan ke kanal
    pembayaran/dukungan yang ditetapkan.
-   **POS offline:** keadaan POS ketika koneksi terputus, transaksi
    masuk antrean lokal, dan proses sinkronisasi saat koneksi pulih.
-   **Konflik sinkronisasi:** daftar transaksi yang perlu ditinjau
    dengan alasan konflik dan status yang jelas.

------------------------------------------------------------------------

## 7. Empty State, Loading, dan Pesan Error

### 7.1 Empty state

Empty state harus menjelaskan kondisi yang terjadi dan tindakan
berikutnya yang memang tersedia di modul tersebut.

  -----------------------------------------------------------------------
  Konteks                             Pesan/arah empty state
  ----------------------------------- -----------------------------------
  Daftar produk kosong                "Belum ada produk." Arahkan ke
                                      tindakan tambah produk hanya jika
                                      pengguna memiliki izin.

  Daftar pelanggan kosong             "Belum ada pelanggan." Jelaskan
                                      bahwa data pelanggan akan muncul
                                      setelah ditambahkan atau tercatat
                                      sesuai proses.

  Riwayat transaksi kosong            "Belum ada transaksi pada periode
                                      ini." Periksa filter/periode bila
                                      relevan.

  Laporan tanpa hasil                 "Tidak ada data untuk filter yang
                                      dipilih." Berikan konteks filter
                                      agar pengguna dapat mengubahnya.

  Belum ada shift aktif               Jelaskan bahwa kasir perlu membuka
                                      shift sebelum transaksi.

  Tidak ada tiket bantuan             "Belum ada tiket bantuan."
                                      Tampilkan tindakan membuat tiket
                                      hanya jika tersedia dan diizinkan.

  Antrean offline kosong              "Tidak ada transaksi yang menunggu
                                      sinkronisasi."

  Tidak ada hasil pencarian barcode   Jelaskan barcode tidak ditemukan
                                      dan sediakan pencarian/input manual
                                      sesuai PRD.
  -----------------------------------------------------------------------

Jangan menampilkan CTA tambah/buat pada empty state jika role pengguna
tidak memiliki izin atau PRD tidak menyediakan tindakan tersebut.

### 7.2 Loading

-   Gunakan skeleton untuk struktur halaman, kartu, dan tabel saat data
    awal dimuat.
-   Gunakan spinner/progress pada aksi singkat yang sedang diproses.
-   Untuk proses yang menunggu server, cegah pengiriman ganda dan
    tampilkan status proses.
-   Untuk sinkronisasi offline, tampilkan status antrean dan hasil per
    transaksi jika tersedia.
-   Untuk laporan/ekspor, jelaskan bahwa proses sedang berjalan; jangan
    mengarang estimasi waktu.
-   Pertahankan struktur konten saat loading agar layout tidak meloncat
    drastis.
-   Hormati preferensi reduced motion; animasi tidak boleh menjadi
    satu-satunya penanda loading.

### 7.3 Pesan error

Gunakan bahasa Indonesia yang langsung, spesifik, dan menyebut tindakan
yang dapat dilakukan.

Contoh pola: - **Validasi:** "Nomor telepon wajib diisi." -
**Autorisasi:** "Anda tidak memiliki izin untuk melakukan tindakan
ini." - **Shift:** "Transaksi belum dapat dilakukan. Buka shift terlebih
dahulu." - **Stok:** "Stok tidak mencukupi untuk jumlah yang diminta." -
**Barcode:** "Barcode tidak ditemukan. Coba cari produk atau masukkan
barcode secara manual." - **Koneksi:** "Koneksi terputus. POS berjalan
dalam mode offline untuk fungsi yang didukung." - **Sinkronisasi:**
"Transaksi belum berhasil disinkronkan. Periksa detail konflik sebelum
mencoba kembali." - **Pembayaran langganan:** "Pembayaran masih menunggu
konfirmasi. Langganan belum aktif." - **Jurnal:** "Jurnal tidak
seimbang. Periksa kembali total debit dan kredit." - **Sesi:** "Sesi
Anda berakhir. Silakan login kembali."

Aturan: - Jangan menampilkan stack trace, query database, kredensial,
token, atau detail teknis internal. - Jangan mengklaim transaksi
berhasil sebelum server mengonfirmasi hasilnya. - Bedakan gagal,
pending, offline queued, dan berhasil tersinkron. - Jika data belum
tersimpan, jelaskan apakah input masih dapat dipulihkan. - Pesan error
harus dibaca screen reader dan tidak hanya ditandai warna.

------------------------------------------------------------------------

## 8. Aturan Aksesibilitas

Arah aksesibilitas yang dipilih adalah **Advanced**. PRD mensyaratkan
kemudahan penggunaan dan responsivitas, tetapi tidak menetapkan standar
WCAG tertentu. Ketentuan di bawah adalah target desain yang diusulkan
untuk validasi dan implementasi.

### 8.1 Kontras dan warna

-   Targetkan WCAG 2.2 AA sebagai baseline terukur untuk teks normal dan
    komponen antarmuka.
-   Teks normal: rasio kontras minimal 4.5:1.
-   Teks besar: minimal 3:1.
-   Batas komponen dan indikator fokus: minimal 3:1 terhadap warna
    sekitar.
-   Status dan grafik tidak boleh mengandalkan warna saja.
-   Uji seluruh pasangan warna pada light dan dark theme, termasuk
    disabled, hover, focus, error, dan badge.

### 8.2 Ukuran target sentuh

-   Target sentuh utama minimal 44 × 44 CSS px sebagai standar desain.
-   Jarak antar target harus cukup agar tidak mudah salah tekan.
-   Aksi penting pada POS dan kontrol yang sering dipakai harus mudah
    dijangkau pada tablet dan smartphone.
-   Jika ukuran kontrol harus lebih kecil karena kepadatan tabel,
    sediakan area interaksi yang cukup dan alternatif akses yang tidak
    mengandalkan presisi motorik tinggi.

### 8.3 Keyboard dan fokus

-   Semua fungsi inti dapat dioperasikan dengan keyboard.
-   Urutan Tab mengikuti urutan visual dan logis.
-   Focus ring terlihat jelas pada kedua tema.
-   Jangan menghilangkan outline tanpa pengganti yang setara.
-   Dialog, dropdown, menu, dan komponen overlay harus memiliki perilaku
    fokus yang benar.
-   Scanner keyboard-wedge tidak boleh merusak navigasi keyboard atau
    input yang sedang aktif.
-   POS perlu mendukung alur keyboard yang efisien tanpa menjadikan
    shortcut sebagai satu-satunya cara.

### 8.4 Screen reader dan struktur

-   Gunakan heading bertingkat secara logis.
-   Semua input memiliki label programatis.
-   Ikon informatif memiliki nama aksesibel; ikon dekoratif diabaikan
    screen reader.
-   Tabel memiliki header kolom yang benar.
-   Perubahan status penting seperti pembayaran, shift, dan sinkronisasi
    diumumkan secara aksesibel.
-   Error dikaitkan dengan field yang bermasalah.
-   Jangan menggunakan teks placeholder sebagai satu-satunya label.

### 8.5 Penglihatan dan motorik

-   Jangan gunakan teks sangat kecil untuk data penting.
-   Sediakan pembesaran browser tanpa memotong fungsi.
-   Hindari informasi yang hanya muncul saat hover.
-   Jangan gunakan animasi berkedip atau gerakan berlebihan.
-   Hormati `prefers-reduced-motion`.
-   Gunakan ikon bersama label pada aksi penting.
-   Pastikan layout tetap dapat digunakan dengan zoom dan pada layar
    kecil.
-   Jangan mengunci orientasi perangkat.

### 8.6 Bahasa dan pemahaman

-   Gunakan Bahasa Indonesia yang sederhana dan konsisten.
-   Hindari singkatan teknis tanpa penjelasan, kecuali istilah domain
    yang umum seperti SKU, POS, dan HPP.
-   Tampilkan format tanggal, waktu, angka, dan mata uang secara
    konsisten sesuai pengaturan tenant.
-   Jelaskan dampak tindakan yang berisiko sebelum pengguna
    mengonfirmasi.

------------------------------------------------------------------------

## 9. Do & Don't

### Do

-   Gunakan layout minimalis, konsisten, dan berorientasi tugas.
-   Prioritaskan kecepatan dan keterbacaan pada POS.
-   Tampilkan konteks tenant, cabang, kasir, register, dan shift saat
    relevan.
-   Bedakan status transaksi, pembayaran, shift, stok, langganan, dan
    sinkronisasi secara jelas.
-   Gunakan label dan pesan yang spesifik.
-   Pertahankan pola komponen yang sama di semua modul.
-   Sediakan light dan dark theme dengan kontras yang sama-sama layak.
-   Buat form panjang mudah dipindai dan dikelompokkan berdasarkan
    kebutuhan PRD.
-   Pastikan fungsi tetap tersedia pada perangkat yang lebih kecil.
-   Tampilkan aksi sesuai permission.
-   Lindungi tindakan yang berdampak pada stok, kas, transaksi, jurnal,
    dan langganan.
-   Tampilkan error tanpa membocorkan informasi internal.
-   Uji aksesibilitas dengan keyboard, screen reader, zoom, dan
    perangkat sentuh.

### Don't

-   Jangan menambahkan halaman, modul, atau fitur yang tidak ada dalam
    PRD.
-   Jangan membuat Midtrans sebagai metode pembayaran pelanggan toko
    pada POS.
-   Jangan mengizinkan transaksi sebelum shift dibuka.
-   Jangan memberi kesan stok negatif diperbolehkan.
-   Jangan menampilkan transaksi offline sebagai transaksi yang sudah
    tersinkron.
-   Jangan menggunakan warna sebagai satu-satunya penanda status.
-   Jangan menyembunyikan aksi penting hanya di hover.
-   Jangan membuat semua kartu, tombol, dan panel menggunakan warna
    aksen.
-   Jangan memenuhi dashboard dengan grafik yang tidak memiliki
    kebutuhan atau definisi data.
-   Jangan membuat tabel terlalu padat hingga teks, status, dan tindakan
    sulit dibaca.
-   Jangan memakai placeholder sebagai pengganti label.
-   Jangan menggunakan animasi yang mengganggu atau tidak dapat
    dikurangi.
-   Jangan menampilkan kredensial Server Key Midtrans secara utuh.
-   Jangan menampilkan menu/aksi yang tidak diizinkan role pengguna
    sebagai kontrol aktif.
-   Jangan mengarang harga paket, batas paket, metrik, status, atau
    kebijakan yang belum ditetapkan PRD.

------------------------------------------------------------------------

## 10. Open Questions

Pertanyaan berikut berasal dari detail yang belum ditetapkan PRD atau
belum dapat diputuskan hanya dari preferensi visual. Jawabannya
diperlukan sebelum desain UI rinci atau implementasi final untuk area
terkait.

  --------------------------------------------------------------------------
  ID                      Pertanyaan terbuka         Dampak desain
  ----------------------- -------------------------- -----------------------
  OQ-01                   Apakah logo, nama final,   Header, login/register,
                          dan aset brand platform    landing page, dan
                          sudah tersedia?            identitas visual.

  OQ-02                   Apakah palet warna usulan  Finalisasi design
                          dapat digunakan sebagai    tokens dan status
                          identitas visual awal,     warna.
                          atau ada warna brand yang  
                          wajib dipakai?             

  OQ-03                   Apakah font Inter dapat    Tipografi dan
                          digunakan, atau ada font   konsistensi lintas
                          brand/ketentuan lisensi    perangkat.
                          tertentu?                  

  OQ-04                   Bagaimana preferensi       Perilaku tema
                          penyimpanan tema:          light/dark dan
                          mengikuti sistem, pilihan  persistensi preferensi.
                          pengguna, atau tema        
                          default tenant?            

  OQ-05                   Apa halaman awal setelah   Routing awal dan
                          login untuk tiap role      prioritas navigasi
                          selain Owner, Kasir,       role.
                          Manager, dan Super Admin?  

  OQ-06                   Apakah landing page perlu  Konten landing page dan
                          menampilkan harga paket    penyajian paket.
                          secara publik, atau hanya  
                          informasi paket dan ajakan 
                          registrasi/kontak?         

  OQ-07                   Apa definisi metrik,       Hierarki dashboard,
                          periode default, dan       kartu ringkasan,
                          prioritas urutan widget    grafik, dan filter.
                          Dashboard Owner serta      
                          Dashboard Super Admin?     

  OQ-08                   Apakah ada kebutuhan       Ukuran kontrol, urutan
                          spesifik untuk layout POS  fokus, dan komposisi
                          kasir: layar sentuh,       area POS.
                          keyboard-first, atau       
                          kombinasi?                 

  OQ-09                   Bagaimana aturan pemilihan Alur buka shift dan
                          register ketika satu       konteks POS.
                          cabang memiliki beberapa   
                          register?                  

  OQ-10                   Apakah perlu mendukung     Kerangka halaman POS
                          mode POS layar             dan navigasi.
                          penuh/kiosk, atau cukup    
                          layout aplikasi responsif  
                          biasa?                     

  OQ-11                   Berapa jumlah kolom/field  Responsivitas tabel
                          minimum yang harus selalu  data.
                          terlihat pada tabel di     
                          smartphone, dan kolom mana 
                          yang boleh                 
                          disembunyikan/diringkas?   

  OQ-12                   Apa aturan default filter  Header laporan, filter,
                          periode pada masing-masing dan ringkasan.
                          laporan?                   

  OQ-13                   Apakah grafik tertentu     Jenis visualisasi dan
                          sudah ditentukan untuk     legenda.
                          laporan/dashboard, atau    
                          pemilihan grafik dilakukan 
                          setelah definisi metrik    
                          tersedia?                  

  OQ-14                   Apa kebijakan approval     Dialog, status menunggu
                          untuk tutup shift, selisih persetujuan, dan aksi
                          kas, pembatalan transaksi, role.
                          retur, penyesuaian stok,   
                          dan jurnal?                

  OQ-15                   Bagaimana aturan detail    Form retur dan
                          retur/refund dan pilihan   ringkasan dampak
                          kredit pelanggan dibanding transaksi.
                          pengembalian dana?         

  OQ-16                   Apa masa berlaku           Peringatan, empty/error
                          sesi/cache offline POS dan state offline, dan
                          bagaimana pengguna diberi  pemulihan sesi.
                          tahu ketika otorisasi      
                          offline kedaluwarsa?       

  OQ-17                   Pada perangkat/browser apa Aksi cetak, pesan
                          printer thermal dan cash   kompatibilitas, dan
                          drawer akan                fallback.
                          diuji/ditetapkan sebagai   
                          perangkat yang didukung?   

  OQ-18                   Apakah pengguna dapat      Pengaturan
                          mengatur ukuran teks atau  aksesibilitas tambahan;
                          hanya menggunakan zoom     jangan membuat kontrol
                          browser dan pengaturan     sebelum disetujui.
                          sistem?                    

  OQ-19                   Apakah ada kebutuhan       Sistem bahasa dan
                          bahasa selain Bahasa       panjang label.
                          Indonesia pada tahap MVP?  

  OQ-20                   Apakah standar             Checklist audit
                          aksesibilitas WCAG 2.2 AA  aksesibilitas dan
                          dapat ditetapkan sebagai   Definition of Done UI.
                          kriteria penerimaan formal 
                          untuk desain dan QA?       
  --------------------------------------------------------------------------

### Catatan keputusan

-   Sampai pertanyaan di atas dijawab, gunakan rekomendasi visual dalam
    brief sebagai baseline desain sementara.
-   Jangan mengisi nilai paket, aturan bisnis, data dashboard, approval,
    atau perilaku teknis yang belum ditentukan.
-   Setiap keputusan yang mengubah alur atau ruang lingkup harus
    dikembalikan ke PRD untuk persetujuan, bukan diselesaikan diam-diam
    melalui desain.

------------------------------------------------------------------------

**Akhir dokumen --- Design Brief POS Retail Web Application**
