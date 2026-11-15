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
`--touch-target: 44px`. Font: Inter (WOFF lokal di `public/assets/fonts/`, lihat README di sana) dengan fallback tumpukan sistem.

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
light  PASS     4.51  4.5  success on success-subtle
light  PASS     4.98  4.5  success on surface
light  PASS     4.64  4.5  warning on warning-subtle
light  PASS     5.04  4.5  warning on surface
light  PASS     4.69  4.5  danger on danger-subtle
light  PASS     5.35  4.5  danger on surface
light  PASS     5.18  4.5  info on info-subtle
light  PASS     5.77  4.5  info on surface
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
dark   PASS     6.86  4.5  success on success-subtle
dark   PASS     9.13  4.5  success on surface
dark   PASS     7.21  4.5  warning on warning-subtle
dark   PASS    10.28  4.5  warning on surface
dark   PASS     5.60  4.5  danger on danger-subtle
dark   PASS     7.04  4.5  danger on surface
dark   PASS     6.80  4.5  info on info-subtle
dark   PASS     8.86  4.5  info on surface
dark   KNOWN    1.76    3  border on background
dark   KNOWN    1.57    3  border on surface
dark   KNOWN    1.36    3  border on surface-muted
dark   PASS     8.06    3  focus-ring on background
dark   PASS     7.22    3  focus-ring on surface
dark   PASS     6.24    3  focus-ring on surface-muted
dark   PASS     5.28    3  focus-ring on primary-subtle

!!! KEGAGALAN TERCATAT (menunggu keputusan pemilik proyek) !!!
  [light] text-muted on background: 4.30 < 4.5  -- Token text-muted (#68778A) di bawah 4.5:1; DESAIN.md 2.1 mewajibkan caption/metadata lolos kontras. Usulan: gelapkan text-muted (mis. #5E6C80).
  [light] text-muted on surface-muted: 4.11 < 4.5  -- Token text-muted (#68778A) di bawah 4.5:1; DESAIN.md 2.1 mewajibkan caption/metadata lolos kontras. Usulan: gelapkan text-muted (mis. #5E6C80).
  [light] border on background: 1.23 < 3  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [light] border on surface: 1.30 < 3  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [light] border on surface-muted: 1.17 < 3  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [dark] border on background: 1.76 < 3  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [dark] border on surface: 1.57 < 3  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).
  [dark] border on surface-muted: 1.36 < 3  -- Token border dipakai juga untuk border input/kontrol; WCAG 1.4.11 butuh 3:1. Usulan: token terpisah border-strong untuk kontrol (divider dekoratif boleh tetap).

Total 66: 58 lulus, 8 gagal tercatat, 0 gagal BARU
```

## Pasangan yang gagal (nilai langsung dari DESAIN.md, BELUM diubah, menunggu keputusan pemilik)

Dicatat di `scripts/contrast-known-failures.json`; hanya kegagalan baru yang membuat skrip exit 1.

1. `text-muted` terang #68778A: 4.30:1 di atas background dan 4.11:1 di atas surface-muted (butuh 4.5:1). Usulan: gelapkan, mis. #5E6C80.
2. `border` terang (#DCE2EA) 1.17 sampai 1.30:1 dan gelap (#344052) 1.36 sampai 1.76:1 terhadap latar (butuh 3:1 bila dipakai sebagai batas kontrol, WCAG 1.4.11).
   Usulan: token `border-strong` untuk kontrol input; `border` tetap untuk pemisah dekoratif.

Hingga diputuskan, komponen memakai `border` apa adanya; `.field:hover` memakai `text-muted` dan `aria-invalid` memakai `danger` 2 px.
