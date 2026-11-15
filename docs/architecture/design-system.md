# Sistem desain

Sumber otoritatif: `DESAIN.md` (bagian 2 warna, 3 tipografi, 4 spasi dan radius). Token ada di
`resources/css/tokens.css`; Tailwind 4 hanya dipakai lewat pustaka lokal (`scripts/build-css.mjs`),
tanpa CDN, tanpa `@import url()`, tanpa CLI.

## Build

```
node scripts/build-css.mjs        # resources/css/app.css -> public/assets/css/app.css (minify; --no-minify opsional)
node scripts/check-contrast.mjs   # tabel kontras; --json untuk mesin
```

`build-css.mjs` mencari paket `tailwindcss` lewat `require.resolve` (cwd dan proyek), `TAILWIND_MODULES`,
`NODE_PATH`, `npm root -g`, lalu `/opt/npm-tools/node_modules`; bila tak ada, mencetak daftar lokasi yang dicoba.
Kandidat kelas dipindai dari `resources/views/**/*.php` dan `resources/js/**/*.js`. CSS hasil build dikomit;
jalankan ulang build setiap mengubah `resources/css/**` atau kelas utilitas di view (tes `CssBuildTest` mendeteksi build usang).

## Token

Tema gelap: `:root[data-theme="dark"]`, dan `@media (prefers-color-scheme: dark)` dengan `:root:not([data-theme="light"])`
(isi identik; dijaga oleh `check-contrast.mjs`).

| Token | Terang | Gelap |
|---|---|---|
| `--color-primary` | `#3659D9` | `#91A7FF` |
| `--color-primary-hover` | `#2948BD` | `#B0BEFF` |
| `--color-primary-subtle` | `#EEF2FF` | `#26345F` |
| `--color-background` | `#F7F8FA` | `#10141C` |
| `--color-surface` | `#FFFFFF` | `#191F2A` |
| `--color-surface-muted` | `#F1F3F6` | `#222B38` |
| `--color-text-primary` | `#182230` | `#F1F4F9` |
| `--color-text-secondary` | `#536174` | `#C0CAD8` |
| `--color-text-muted` | `#68778A` | `#A3B0C2` |
| `--color-border` | `#DCE2EA` | `#344052` |
| `--color-success` | `#16804A` | `#65D695` |
| `--color-success-subtle` | `#EAF7EF` | `#1D3A2B` |
| `--color-warning` | `#A85B08` | `#FFC078` |
| `--color-warning-subtle` | `#FFF4E5` | `#49351F` |
| `--color-danger` | `#C33737` | `#FF8585` |
| `--color-danger-subtle` | `#FDECEC` | `#482729` |
| `--color-info` | `#1769AA` | `#83C5F4` |
| `--color-info-subtle` | `#EAF4FC` | `#1D3548` |
| `--color-on-primary` | `var(--color-surface)` | `var(--color-background)` |
| `--color-on-danger` | `var(--color-surface)` | `var(--color-background)` |
| `--color-focus-ring` | `var(--color-primary)` | `(sama)` |

Turunan tanpa nilai baru: `--color-on-primary`/`--color-on-danger` (terang: surface, gelap: background), `--color-focus-ring` (= primary).
Spasi `--space-1..12` (4 px basis: 1,2,3,4,5,6,8,10,12), radius `--radius-sm/md/lg/xl/full` (6/8/12/16/999 px),
tipografi `--text-display|h1|h2|h3|body|body-sm|label|caption|amount` (+ `--line-height`), bobot 400/500/600,
`--touch-target: 44px`. Font: Inter 4.0 (WOFF lokal di `public/assets/fonts/`, lihat README di sana) dengan fallback tumpukan sistem.

## Komponen (`resources/css/components/`)

- `button.css`: `btn`, `btn-primary`, `btn-secondary`, `btn-tertiary`, `btn-danger`, status `aria-busy`
- `form.css`: `field`, `field-group`, `field-label`, `field-hint`, `field-error`, `error-summary`, `check`
- `badge.css`: `badge`, `badge-success|warning|danger|info|neutral`
- `table.css`: `table-wrap`, `table-data` (+ `.num`)
- `overlay.css`: `dialog-panel` (+ `__title`, `__body`, `__actions`)
- `feedback.css`: `toast`, `toast-region`, `alert-*`, `skeleton`, `empty-state`
- `nav.css`: `nav-item`
- `skip-link.css`: `skip-link`
- `layout.css`: `app-shell`, `sidebar`, `topbar`, `card`, `page-header`, `stack`, `cluster`, `auth-page`, `auth-card`, `visually-hidden`

Dasar aksesibilitas di `app.css`: cincin fokus 3 px (`:focus-visible`), target sentuh 44 px, `prefers-reduced-motion`.

## Laporan kontras (keluaran verbatim `node scripts/check-contrast.mjs`)

```
Laporan kontras WCAG (nilai dari resources/css/tokens.css)

tema   status  rasio  min  pasangan
light  PASS    15.08  4.5  text-primary on background
light  PASS    16.03  4.5  text-primary on surface
light  PASS    14.42  4.5  text-primary on surface-muted
light  PASS     5.93  4.5  text-secondary on background
light  PASS     6.31  4.5  text-secondary on surface
light  PASS     5.67  4.5  text-secondary on surface-muted
light  KNOWN    4.30  4.5  text-muted on background
light  PASS     4.57  4.5  text-muted on surface
light  KNOWN    4.11  4.5  text-muted on surface-muted
light  PASS     5.64  4.5  text-secondary on primary-subtle
light  PASS    14.33  4.5  text-primary on primary-subtle
light  PASS     5.85  4.5  on-primary on primary
light  PASS     7.62  4.5  on-primary on primary-hover
light  PASS     5.35  4.5  on-danger on danger
light  PASS     6.76  4.5  on-danger on danger (hover, campuran 18% text-primary)
light  PASS     5.51  4.5  primary (tautan/nav aktif) on background
light  PASS     5.85  4.5  primary (tautan/nav aktif) on surface
light  PASS     5.23  4.5  primary (tautan/nav aktif) on primary-subtle
light  PASS     5.27  4.5  primary (tautan/nav aktif) on surface-muted
light  PASS     4.51  4.5  success on success-subtle
light  PASS     4.68  4.5  success on background
light  PASS     4.98  4.5  success on surface
light  KNOWN    4.48  4.5  success on surface-muted
light  PASS    14.54  4.5  text-primary on success-subtle
light  PASS     5.72  4.5  text-secondary on success-subtle
light  PASS     4.64  4.5  warning on warning-subtle
light  PASS     4.74  4.5  warning on background
light  PASS     5.04  4.5  warning on surface
light  PASS     4.54  4.5  warning on surface-muted
light  PASS    14.75  4.5  text-primary on warning-subtle
light  PASS     5.80  4.5  text-secondary on warning-subtle
light  PASS     4.69  4.5  danger on danger-subtle
light  PASS     5.04  4.5  danger on background
light  PASS     5.35  4.5  danger on surface
light  PASS     4.82  4.5  danger on surface-muted
light  PASS    14.03  4.5  text-primary on danger-subtle
light  PASS     5.52  4.5  text-secondary on danger-subtle
light  PASS     5.18  4.5  info on info-subtle
light  PASS     5.43  4.5  info on background
light  PASS     5.77  4.5  info on surface
light  PASS     5.19  4.5  info on surface-muted
light  PASS    14.38  4.5  text-primary on info-subtle
light  PASS     5.66  4.5  text-secondary on info-subtle
light  KNOWN    1.23    3  border on background
light  KNOWN    1.30    3  border on surface
light  KNOWN    1.17    3  border on surface-muted
light  PASS     5.51    3  focus-ring on background
light  PASS     5.85    3  focus-ring on surface
light  PASS     5.27    3  focus-ring on surface-muted
light  PASS     5.23    3  focus-ring on primary-subtle
dark   PASS    16.73  4.5  text-primary on background
dark   PASS    14.99  4.5  text-primary on surface
dark   PASS    12.95  4.5  text-primary on surface-muted
dark   PASS    11.13  4.5  text-secondary on background
dark   PASS     9.98  4.5  text-secondary on surface
dark   PASS     8.62  4.5  text-secondary on surface-muted
dark   PASS     8.38  4.5  text-muted on background
dark   PASS     7.51  4.5  text-muted on surface
dark   PASS     6.49  4.5  text-muted on surface-muted
dark   PASS     7.29  4.5  text-secondary on primary-subtle
dark   PASS    10.95  4.5  text-primary on primary-subtle
dark   PASS     8.06  4.5  on-primary on primary
dark   PASS    10.23  4.5  on-primary on primary-hover
dark   PASS     7.85  4.5  on-danger on danger
dark   PASS     8.92  4.5  on-danger on danger (hover, campuran 18% text-primary)
dark   PASS     8.06  4.5  primary (tautan/nav aktif) on background
dark   PASS     7.22  4.5  primary (tautan/nav aktif) on surface
dark   PASS     5.28  4.5  primary (tautan/nav aktif) on primary-subtle
dark   PASS     6.24  4.5  primary (tautan/nav aktif) on surface-muted
dark   PASS     6.86  4.5  success on success-subtle
dark   PASS    10.19  4.5  success on background
dark   PASS     9.13  4.5  success on surface
dark   PASS     7.89  4.5  success on surface-muted
dark   PASS    11.26  4.5  text-primary on success-subtle
dark   PASS     7.49  4.5  text-secondary on success-subtle
dark   PASS     7.21  4.5  warning on warning-subtle
dark   PASS    11.47  4.5  warning on background
dark   PASS    10.28  4.5  warning on surface
dark   PASS     8.88  4.5  warning on surface-muted
dark   PASS    10.51  4.5  text-primary on warning-subtle
dark   PASS     7.00  4.5  text-secondary on warning-subtle
dark   PASS     5.60  4.5  danger on danger-subtle
dark   PASS     7.85  4.5  danger on background
dark   PASS     7.04  4.5  danger on surface
dark   PASS     6.08  4.5  danger on surface-muted
dark   PASS    11.92  4.5  text-primary on danger-subtle
dark   PASS     7.94  4.5  text-secondary on danger-subtle
dark   PASS     6.80  4.5  info on info-subtle
dark   PASS     9.89  4.5  info on background
dark   PASS     8.86  4.5  info on surface
dark   PASS     7.65  4.5  info on surface-muted
dark   PASS    11.51  4.5  text-primary on info-subtle
dark   PASS     7.66  4.5  text-secondary on info-subtle
dark   KNOWN    1.76    3  border on background
dark   KNOWN    1.57    3  border on surface
dark   KNOWN    1.36    3  border on surface-muted
dark   PASS     8.06    3  focus-ring on background
dark   PASS     7.22    3  focus-ring on surface
dark   PASS     6.24    3  focus-ring on surface-muted
dark   PASS     5.28    3  focus-ring on primary-subtle

!!! KEGAGALAN TERCATAT (menunggu keputusan pemilik proyek) !!!
  [light] text-muted on background: 4.30 < 4.5 (tercatat 4.3)  -- Token text-muted (#68778A) di bawah 4.5:1; DESAIN.md 2.1 mewajibkan caption/metadata lolos kontras. Usulan: gelapkan text-muted (mis. #5E6C80).
  [light] text-muted on surface-muted: 4.11 < 4.5 (tercatat 4.11)  -- Token text-muted (#68778A) di bawah 4.5:1; DESAIN.md 2.1 mewajibkan caption/metadata lolos kontras. Usulan: gelapkan text-muted (mis. #5E6C80).
  [light] success on surface-muted: 4.48 < 4.5 (tercatat 4.48)  -- Token success terang (#16804A) 4.48:1 di atas surface-muted (#F1F3F6), tepat di bawah 4.5:1; terlihat bila teks/ikon status ditaruh di permukaan muted (mis. baris tabel selang-seling). Usulan: gelapkan success sedikit (mis. #157A46).
  [light] border on background: 1.23 < 3 (tercatat 1.23)  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [light] border on surface: 1.30 < 3 (tercatat 1.3)  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [light] border on surface-muted: 1.17 < 3 (tercatat 1.17)  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [dark] border on background: 1.76 < 3 (tercatat 1.76)  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [dark] border on surface: 1.57 < 3 (tercatat 1.57)  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [dark] border on surface-muted: 1.36 < 3 (tercatat 1.36)  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).

Total 100: 91 lulus, 9 gagal tercatat, 0 memburuk, 0 gagal BARU
```

## Pasangan yang gagal (nilai langsung dari DESAIN.md, BELUM diubah, menunggu keputusan pemilik)

Dicatat di `scripts/contrast-known-failures.json` (status "awaiting owner decision") dengan `ratioAtListing`.
Skrip exit 1 bila ada kegagalan baru, atau bila rasio kegagalan tercatat sekarang LEBIH RENDAH dari `ratioAtListing`
(memburuk); entri usang (pasangan sudah lulus) dicetak sebagai PERINGATAN dan tes `CssBuildTest` gagal sampai dihapus.

Cakupan pasangan: teks (primary/secondary/muted) x background|surface|surface-muted, teks primary/secondary x setiap
`*-subtle` status, tombol, `primary` x background|surface|surface-muted|primary-subtle, setiap warna status x
background|surface|surface-muted|`*-subtle`-nya sendiri, `border` dan `focus-ring` (non-teks 3:1), di kedua tema.

Daftar lengkap (9 kegagalan tercatat):

1. light `text-muted` #68778A on background: 4.30 (butuh 4.5). Usulan: gelapkan, mis. #5E6C80.
2. light `text-muted` on surface-muted: 4.11 (butuh 4.5). Usulan sama.
3. light `success` #16804A on surface-muted: 4.48 (butuh 4.5; BARU di putaran 1). Usulan: mis. #157A46 (lulus di surface-muted dan success-subtle).
4. light `border` #DCE2EA on background: 1.23 (butuh 3 untuk batas kontrol, WCAG 1.4.11).
5. light `border` on surface: 1.30.
6. light `border` on surface-muted: 1.17.
7. dark `border` #344052 on background: 1.76.
8. dark `border` on surface: 1.57.
9. dark `border` on surface-muted: 1.36.

Usulan untuk 4 sampai 9: token `border-strong` untuk kontrol input; `border` tetap untuk pemisah dekoratif.
Hingga diputuskan, komponen memakai `border` apa adanya; `.field:hover` memakai `text-muted` dan `aria-invalid` memakai `danger` 2 px.

## Catatan teknis dan batas yang diketahui

- Lisensi font: `public/assets/fonts/LICENSE-OFL.txt` berisi pemberitahuan hak cipta Inter dan teks SIL OFL 1.1 baku
  (paket Debian tidak menyertakan LICENSE.txt hulu, jadi ditulis dari teks terbitan). Font: Inter 4.0 (WOFF, subset Latin).
- `@theme inline` di `app.css` memetakan nama Tailwind ke token dengan nama yang sama (`--color-primary: var(--color-primary)`).
  Tailwind menulis pemetaan itu ke `@layer theme` (referensi melingkar, tidak valid), dan nilai sebenarnya datang dari
  `tokens.css` yang tidak berlapis, yang selalu menang atas lapisan mana pun. Jadi benar tanpa bergantung urutan sumber,
  tetapi bergantung pada token tetap berada di luar `@layer`. Mengganti nama variabel Tailwind akan mengubah nama kelas
  (`bg-primary`, dst.), jadi dibiarkan; jangan memindahkan `tokens.css` ke dalam `@layer`.
- Parity tema gelap: `check-contrast.mjs` membandingkan SEMUA properti kustom dan `color-scheme` antara
  `[data-theme="dark"]` dan blok `prefers-color-scheme`.
- Badge status punya glyph `::before` (centang, segitiga, silang, "i") agar status tidak hanya dibedakan warna.
- `.check` (label yang membungkus checkbox/radio) menjadi area sentuh 44 px; `.tabular` / `.num` untuk angka tabular
  (tidak lagi di seluruh body, sesuai DESAIN.md 3.3). Font memakai berkas bundel lebih dulu (tanpa `local("Inter")`).
- `build-css.mjs --out=<berkas>` menulis ke jalur lain (dipakai tes kesegaran, yang membandingkan byte hasil build ulang).
  Penjaga URL eksternal hanya memeriksa `url(...)` dan `@import`, bukan komentar.
- DITUNDA: minifier regex tidak memahami string CSS (spasi/pemisah di dalam literal string ikut dirapatkan; hindari pola itu
  atau pakai minifier sungguhan); `findTailwind` memeriksa cwd lebih dulu sebelum proyek (sebaiknya proyek dulu).
