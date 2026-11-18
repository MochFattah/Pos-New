<?php

declare(strict_types=1);

/**
 * Merender setiap jenis halaman lewat harness tes (SQLite memori, tanpa MySQL) ke HTML statis untuk
 * scripts/screenshots.mjs dan scripts/test-js.mjs. Keluaran: <outDir>/manifest.json + <outDir>/html/*.html.
 * Batas: bukan server nyata berbasis MySQL; tautan/formulir dilayani server statis (lihat scripts/lib/static-server.mjs).
 *
 * Pakai: php scripts/dump-pages.php [outDir]   (bawaan: storage/cache/screens)
 */

require __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Response;
use App\Core\Security\SecurityHeaders;
use Tests\Support\PageFixtures;

$out = rtrim($argv[1] ?? dirname(__DIR__) . '/storage/cache/screens', '/');

$dumper = new class extends PageFixtures {
    /** @var array<string, array{method: string, path: string, status: int, file: string}> */
    private array $manifest = [];
    private string $dir = '';

    public function run(string $out): void
    {
        $this->dir = $out . '/html';
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Tidak bisa membuat ' . $this->dir);
        }
        foreach ((array) glob($this->dir . '/*.html') as $old) {
            unlink($old);
        }
        $this->runSetUp();
        $pages = $this->renderAllPages();
        foreach ($pages as $name => $html) {
            if (str_starts_with($name, '/')) {
                $this->add('GET', $name, $html);
            } elseif (preg_match('~^POST (/\S+)~', $name, $m) === 1) {
                $this->add('POST', $m[1], $html);
            } else {
                $this->add('NAME', '/__page/' . preg_replace('~[^a-z0-9]+~', '-', strtolower($name)), $html);
            }
        }

        // Data lebih realistis untuk menilai tampilan daftar (nama panjang menguji pembungkusan/overflow).
        $t = (int) $this->db->fetchOne('SELECT id FROM tenants ORDER BY id LIMIT 1')['id'];
        $this->db->execute('UPDATE tenants SET name = ? WHERE id = ?', ['Toko Sumber Rejeki Makmur Jaya', $t]);
        $this->seedBranch($t, 'BDG', 'Cabang Bandung Dago');
        $this->seedBranch($t, 'SBY', 'Cabang Surabaya Pusat Distribusi Wilayah Timur dan Kepulauan');
        $this->db->execute("UPDATE branches SET address = ?, phone = ? WHERE code = 'BDG'", ['Jl. Ir. H. Juanda No. 120, Bandung', '022-5551234']);
        $this->makeUser($t, 'kasir.panjang.sekali.alamat.emailnya@contoh-perusahaan-ritel.co.id', ['branches.view']);
        $this->makeUser($t, 'gudang@example.com', ['branches.view']);
        $this->db->execute("UPDATE users SET status = 'inactive' WHERE email = 'gudang@example.com'");
        foreach (['/cabang', '/pengguna', '/beranda', '/role'] as $uri) {
            $this->add('GET', $uri, $this->get($uri));
        }

        // Pesan flash: buat cabang lalu ikuti redirect.
        $r = $this->send('POST', '/cabang', ['code' => 'MLG', 'name' => 'Cabang Malang', 'address' => '', 'phone' => '']);
        $this->assertSame(302, $r->status(), 'POST /cabang valid harus redirect');
        $this->add('GET', '/cabang?flash=1', $this->get('/cabang'));

        // Demo berakhir: majukan jam melewati masa demo.
        $this->advanceKeepingSessionAlive(60 * 86400);
        $r = $this->send('GET', '/demo-berakhir');
        $this->add('GET', '/demo-berakhir', $r->body(), $r->status());

        $csp = (string) SecurityHeaders::apply(Response::html(''), false)->header('Content-Security-Policy');
        file_put_contents($out . '/manifest.json', json_encode(['csp' => $csp, 'pages' => $this->manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->runTearDown();
        fwrite(STDOUT, 'OK ' . count($this->manifest) . " halaman dirender ke $out\n");
    }

    private function add(string $method, string $path, string $html, int $status = 200): void
    {
        $file = substr(md5($method . ' ' . $path), 0, 10) . '.html';
        file_put_contents($this->dir . '/' . $file, $html);
        $this->manifest[$method . ' ' . $path] = ['method' => $method, 'path' => $path, 'status' => $status, 'file' => 'html/' . $file];
    }
};

$dumper->run($out);
