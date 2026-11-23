<?php

declare(strict_types=1);

namespace Tests\Feature\Views;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\Csrf;
use App\Core\Security\RateLimiter;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Tenants\Controllers\SettingsController;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Services\LogoUploader;
use App\Modules\Tenants\Services\TenantSettingsService;
use App\Core\Security\SecurityHeaders;
use App\Core\View;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Tests\Support\PageFixtures;

/** Kontrak markup lintas halaman: escape, nav, label, ARIA, tanpa inline, kerangka dasar, dan drift kelas CSS. */
final class ViewTest extends PageFixtures
{
    

    /** style="" yang dibenarkan, per berkas view (kosong: tidak ada yang dibenarkan). @var array<string, list<string>> */
    private const STYLE_ALLOWLIST = [];

    /**
     * Kelas tanpa aturan CSS: pengait JS/ARIA murni. Kosong = tiap kelas wajib punya aturan di app.css.
     * @var list<string>
     */
    private const CLASS_ALLOWLIST = [];

    // --- Pembantu ---

    /** @return list<string> */
    private function viewFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/resources/views', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (str_ends_with($f->getFilename(), '.php')) {
                $files[] = $f->getPathname();
            }
        }
        sort($files);
        $this->assertTrue(count($files) > 40, 'Pemindai harus menemukan seluruh view');
        return $files;
    }

    /** Mengganti <?= expr ?> menjadi {{expr}} dan membuang tag <?php ... ?> agar HTML bisa dipindai dengan regex. */
    private function stripPhp(string $src): string
    {
        $src = (string) preg_replace_callback('/<\?=\s*(.*?)\s*\?>/s', static fn (array $m): string => '{{' . str_replace(['<', '>'], ['&lt;', '&gt;'], (string) preg_replace('/\s+/', ' ', $m[1])) . '}}', $src);
        $src = (string) preg_replace('/<\?php.*?\?>/s', ' ', $src);
        return (string) preg_replace('/<\?php.*\z/s', '', $src);
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return new DOMXPath($doc);
    }

    // --- Escape ---

    public function testViewEscapesUserInputByDefault(): void
    {
        $t = $this->loggedInOwner();
        $this->db->execute('UPDATE tenants SET name = ? WHERE id = ?', ['<script>alert(1)</script>', $t['tenantId']]);
        $body = $this->get('/beranda');
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertFalse(str_contains($body, '<script>alert(1)'), 'Nama bisnis tidak boleh lolos sebagai HTML');
    }

    // --- Navigasi ---

    public function testNavHidesItemsWithoutPermission(): void
    {
        $t = $this->loggedInOwner();
        $ownerNav = $this->get('/beranda');
        foreach (['/beranda', '/cabang', '/pengguna', '/role', '/pengaturan'] as $href) {
            $this->assertContains('class="nav-item" href="' . $href . '"', $ownerNav, "Owner harus melihat $href");
        }
        $this->send('POST', '/keluar');
        $this->makeUser($t['tenantId'], 'staf@example.com', ['branches.view']);
        $this->assertSame(302, $this->login('staf@example.com')->status());
        $nav = $this->get('/beranda');
        $this->assertContains('class="nav-item" href="/beranda"', $nav);
        $this->assertContains('class="nav-item" href="/cabang"', $nav);
        foreach (['/pengguna', '/role', '/pengaturan'] as $href) {
            $this->assertFalse(str_contains($nav, 'class="nav-item" href="' . $href . '"'), "$href harus tersembunyi tanpa izin");
        }
    }

    public function testActiveNavItemHasAriaCurrent(): void
    {
        $this->loggedInOwner();
        $x = $this->dom($this->get('/cabang'));
        $current = $x->query('//nav//a[@aria-current]');
        $this->assertSame(1, $current->length, 'Tepat satu item aktif');
        $this->assertSame('page', $current->item(0)->getAttribute('aria-current'));
        $this->assertSame('/cabang', $current->item(0)->getAttribute('href'));
        // Subhalaman tetap menandai induknya.
        $x = $this->dom($this->get('/cabang/baru'));
        $this->assertSame('/cabang', $x->query('//nav//a[@aria-current="page"]')->item(0)?->getAttribute('href'));
    }

    public function testAppLayoutHasLogoutFormWithCsrf(): void
    {
        $this->loggedInOwner();
        $x = $this->dom($this->get('/beranda'));
        $form = $x->query('//form[@action="/keluar"]');
        $this->assertSame(1, $form->length);
        $this->assertSame('post', strtolower($form->item(0)->getAttribute('method')));
        $token = $x->query('.//input[@name="_csrf"]', $form->item(0))->item(0)?->getAttribute('value');
        $this->assertTrue($token !== null && $token !== '' && $token === Csrf::token($this->session), 'Token CSRF di form keluar harus token sesi');
        $this->assertSame(1, $x->query('//form[@action="/keluar"]//button[@type="submit"]')->length);
    }

    // --- Label dan ARIA ---

    public function testEveryInputHasAssociatedLabel(): void
    {
        foreach ($this->viewFiles() as $file) {
            $src = $this->stripPhp((string) file_get_contents($file));
            preg_match_all('/<label\b[^>]*\bfor="([^"]*)"/i', $src, $m);
            $labelFor = $m[1];
            preg_match_all('/<(input|select|textarea)\b([^>]*)>/is', $src, $tags, PREG_SET_ORDER);
            foreach ($tags as $tag) {
                $attrs = $tag[2];
                if ($tag[1] === 'input' && preg_match('/\btype="(hidden|submit|button|reset|image)"/i', $attrs) === 1) {
                    continue;
                }
                $name = basename(dirname($file)) . '/' . basename($file);
                if (preg_match('/\baria-label(ledby)?="[^"]+"/i', $attrs) === 1) {
                    continue;
                }
                $this->assertTrue(preg_match('/\bid="([^"]+)"/', $attrs, $id) === 1, "$name: kontrol tanpa id: " . trim($tag[0]));
                $this->assertTrue(in_array($id[1], $labelFor, true), "$name: tidak ada <label for=\"{$id[1]}\">");
            }
        }
        // Pemeriksaan hasil render (id dinamis): setiap kontrol punya label[for] yang cocok.
        foreach ($this->renderAllPages() as $name => $html) {
            $x = $this->dom($html);
            foreach ($x->query('//input[not(@type="hidden" or @type="submit" or @type="button")]|//select|//textarea') as $el) {
                /** @var DOMElement $el */
                if ($el->hasAttribute('aria-label') || $el->hasAttribute('aria-labelledby')) {
                    continue;
                }
                $id = $el->getAttribute('id');
                $this->assertTrue($id !== '' && $x->query('//label[@for="' . $id . '"]')->length >= 1, "$name: kontrol '" . $el->getAttribute('name') . "' tanpa label");
            }
        }
    }

    public function testErrorMessageLinkedViaAriaDescribedby(): void
    {
        $this->loggedInOwner();
        $pages = [
            'cabang' => $this->send('POST', '/cabang', ['code' => '', 'name' => ''])->body(),
            'undang' => $this->send('POST', '/pengguna/undang', ['name' => '', 'email' => 'bukan-email'])->body(),
            'daftar' => $this->guestRegister(),
        ];
        foreach ($pages as $name => $html) {
            $x = $this->dom($html);
            $errors = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " field-error ")]');
            $this->assertTrue($errors->length > 0, "$name: harus menampilkan pesan kesalahan field");
            foreach ($errors as $err) {
                /** @var DOMElement $err */
                $id = $err->getAttribute('id');
                $this->assertTrue($id !== '', "$name: pesan kesalahan tanpa id");
                $ref = $x->query('//*[@aria-describedby][contains(concat(" ", normalize-space(@aria-describedby), " "), " ' . $id . ' ")]');
                $this->assertTrue($ref->length === 1, "$name: #$id harus dirujuk satu kontrol lewat aria-describedby");
                $this->assertSame('true', $ref->item(0)->getAttribute('aria-invalid'), "$name: kontrol #$id perlu aria-invalid");
            }
            $summary = $x->query('//*[@id="error-summary"][@role="alert"][@tabindex="-1"]');
            $this->assertSame(1, $summary->length, "$name: ringkasan kesalahan role=alert tabindex=-1");
            $this->assertTrue($x->query('//*[@id="error-summary"]//a[starts-with(@href,"#")]')->length > 0, "$name: ringkasan menautkan ke field");
        }
    }

    private function guestRegister(): string
    {
        $this->send('POST', '/keluar');
        return $this->send('POST', '/daftar', ['owner_name' => '', 'email' => 'x', 'password' => '', 'business_name' => ''])->body();
    }

    /** Setiap tautan #x di ringkasan galat harus menunjuk elemen ber-id di dokumen yang sama (kalau tidak, tautan mati). */
    public function testEveryErrorSummaryLinkResolvesToAnElementId(): void
    {
        $pages = $this->renderAllPages();
        $keys = ['name' => 'a', 'phone' => 'b', 'role_ids' => 'c', 'branch_ids' => 'd', 'status' => 'e', 'form' => 'f'];
        $pages['users/edit (semua kunci galat)'] = View::render('users/edit', [
            'title' => 'Ubah', 'target' => ['id' => 5, 'is_owner' => 0, 'status' => 'active', 'email' => 'a@b.c', 'name' => 'A', 'email_verified_at' => '2026-01-01'],
            'roles' => [['id' => 1, 'name' => 'Kasir', 'locked' => false, 'checked' => true]],
            'branches' => [['id' => 2, 'name' => 'Pusat', 'code' => 'PST', 'locked' => false, 'checked' => false]],
            'isSelf' => false, 'errors' => $keys, 'old' => ['name' => 'A', 'phone' => ''], 'csrf' => 'x', 'notice' => null, 'error' => null,
        ]);
        $checked = 0;
        foreach ($pages as $name => $html) {
            $x = $this->dom($html);
            foreach ($x->query('//*[@id="error-summary"]//a[starts-with(@href,"#")]') as $a) {
                /** @var DOMElement $a */
                $id = rawurldecode(substr($a->getAttribute('href'), 1));
                $this->assertTrue($x->query('//*[@id="' . $id . '"]')->length === 1, "$name: tautan ringkasan galat #$id tidak punya elemen ber-id");
                $checked++;
            }
        }
        $this->assertTrue($checked >= 15, "Terlalu sedikit tautan galat yang diperiksa ($checked)");
    }

    public function testTablesHaveCaptionAndScopedHeaders(): void
    {
        $tables = 0;
        foreach ($this->renderAllPages() as $name => $html) {
            $x = $this->dom($html);
            foreach ($x->query('//table') as $table) {
                $tables++;
                $this->assertTrue($x->query('./caption[normalize-space()!=""]', $table)->length === 1, "$name: tabel tanpa <caption>");
                foreach ($x->query('.//th', $table) as $th) {
                    /** @var DOMElement $th */
                    $this->assertTrue(in_array($th->getAttribute('scope'), ['col', 'row'], true), "$name: <th> tanpa scope: " . trim($th->textContent));
                }
            }
        }
        $this->assertTrue($tables >= 3, 'Tabel harus ikut dirender');
    }

    public function testHeadingLevelsDoNotSkip(): void
    {
        foreach ($this->renderAllPages() as $name => $html) {
            $prev = 0;
            foreach ($this->dom($html)->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
                /** @var DOMElement $h */
                $level = (int) substr($h->tagName, 1);
                $this->assertTrue($prev === 0 ? $level === 1 : $level <= $prev + 1, "$name: urutan heading melompat ke <{$h->tagName}> setelah h$prev: " . trim($h->textContent));
                $prev = $level;
            }
        }
    }

    public function testScrollableTableWrapIsKeyboardReachable(): void
    {
        $found = 0;
        foreach ($this->renderAllPages() as $name => $html) {
            foreach ($this->dom($html)->query('//*[contains(concat(" ", normalize-space(@class), " "), " table-wrap ")]') as $w) {
                /** @var DOMElement $w */
                $found++;
                $this->assertSame('0', $w->getAttribute('tabindex'), "$name: .table-wrap tabindex=0");
                $this->assertSame('region', $w->getAttribute('role'), "$name: .table-wrap role=region");
                $this->assertTrue(trim($w->getAttribute('aria-label')) !== '', "$name: .table-wrap perlu aria-label");
            }
        }
        $this->assertTrue($found >= 3, '.table-wrap harus dirender');
    }

    // --- Tanpa inline ---

    public function testNoInlineScriptOrInlineEventHandlers(): void
    {
        foreach ($this->viewFiles() as $file) {
            $src = $this->stripPhp((string) file_get_contents($file));
            $name = basename(dirname($file)) . '/' . basename($file);
            preg_match_all('/<script\b([^>]*)>/i', $src, $scripts);
            foreach ($scripts[1] as $attrs) {
                $this->assertTrue(preg_match('/\bsrc="[^"]+"/i', $attrs) === 1, "$name: <script> tanpa src");
            }
            $this->assertSame(0, preg_match('/<[a-z][^>]*\son[a-z]+\s*=/i', $src), "$name: atribut on* inline");
            $this->assertSame(0, preg_match('/<style\b/i', $src), "$name: <style> inline");
            $hasStyle = preg_match('/<[a-z][^>]*\sstyle\s*=/i', $src) === 1;
            $this->assertTrue(!$hasStyle || isset(self::STYLE_ALLOWLIST[$name]), "$name: atribut style= tanpa pembenaran");
            $this->assertSame(0, preg_match('/javascript:/i', $src), "$name: URL javascript:");
        }
        foreach ($this->renderAllPages() as $name => $html) {
            $x = $this->dom($html);
            foreach ($x->query('//script') as $s) {
                /** @var DOMElement $s */
                $this->assertTrue($s->getAttribute('src') !== '' && trim($s->textContent) === '', "$name: script inline");
            }
            $this->assertSame(0, $x->query('//@*[starts-with(name(), "on")]')->length, "$name: atribut on* pada hasil render");
            $this->assertSame(0, $x->query('//@style')->length, "$name: style= pada hasil render");
            $this->assertSame(0, $x->query('//style')->length, "$name: <style> pada hasil render");
        }
    }

    public function testCspIsCompatibleWithLayouts(): void
    {
        $csp = (string) SecurityHeaders::apply(Response::html(''), false)->header('Content-Security-Policy');
        $this->assertContains("script-src 'self'", $csp);
        $this->assertContains("style-src 'self'", $csp);
        $this->assertFalse(str_contains($csp, 'unsafe-inline') || str_contains($csp, 'unsafe-eval'), 'CSP tanpa unsafe-*');
        foreach ($this->renderAllPages() as $name => $html) {
            $x = $this->dom($html);
            foreach ($x->query('//script/@src|//link/@href') as $attr) {
                $this->assertTrue(str_starts_with($attr->nodeValue, '/assets/'), "$name: sumber eksternal tidak diizinkan CSP: " . $attr->nodeValue);
            }
        }
    }

    public function testLayoutsLoadJsModuleAndStubExists(): void
    {
        $this->assertTrue(is_file(self::ROOT . '/public/assets/js/app.js'), 'public/assets/js/app.js harus ada');
        $this->assertTrue(is_file(self::ROOT . '/public/assets/js/theme.js'), 'theme.js harus ada');
        foreach (['public', 'app', 'platform'] as $layout) {
            $html = View::render('home/landing', ['title' => 'x', 'shell' => \App\Core\Layout\AppShell::minimal('a', 'b'), 'name' => 'x', 'csrf' => 'c'], $layout);
            $x = $this->dom($html);
            $this->assertSame(1, $x->query('//script[@type="module"][@src="/assets/js/app.js"]')->length, "layout $layout memuat app.js sebagai modul");
            $this->assertSame(1, $x->query('//link[@rel="stylesheet"][@href="/assets/css/app.css"]')->length, "layout $layout memuat CSS");
        }
    }

    // --- Kerangka dasar ---

    public function testPagesDeclareLangIdAndSkipLink(): void
    {
        $pages = $this->renderAllPages();
        $this->assertTrue(count($pages) >= 30, 'Seluruh jenis halaman dirender');
        foreach ($pages as $name => $html) {
            $x = $this->dom($html);
            $this->assertSame('id', $x->query('/html')->item(0)->getAttribute('lang'), "$name: html lang=id");
            $this->assertSame(1, $x->query('//meta[@name="viewport"]')->length, "$name: meta viewport");
            $this->assertTrue(trim($x->query('//title')->item(0)?->textContent ?? '') !== '', "$name: title");
            $first = $x->query('//body/*[1]')->item(0);
            $this->assertTrue($first instanceof DOMElement && $first->tagName === 'a' && $first->getAttribute('href') === '#main-content' && str_contains($first->getAttribute('class'), 'skip-link'), "$name: skip-link harus elemen pertama body");
            $main = $x->query('//main[@id="main-content"]');
            $this->assertSame(1, $main->length, "$name: tepat satu main#main-content");
            $this->assertSame('-1', $main->item(0)->getAttribute('tabindex'), "$name: main tabindex=-1");
            $this->assertSame(1, $x->query('//h1')->length, "$name: tepat satu h1");
        }
    }

    public function testErrorPagesRenderThroughLayoutWithPlainFallback(): void
    {
        $router = new Router(static fn (string $c): object => new \stdClass());
        $router->get('/boom', static fn (): never => throw new \RuntimeException('rahasia-internal'));
        $log = sys_get_temp_dir() . '/view-' . bin2hex(random_bytes(4)) . '.log';
        try {
            $this->assertErrorPages(new Application($router, $log));
        } finally {
            @unlink($log);
        }
    }

    private function assertErrorPages(Application $app): void
    {
        $res = $app->handle(Request::create('GET', '/boom'));
        $this->assertSame(500, $res->status());
        $this->assertContains('skip-link', $res->body());
        $this->assertContains('/assets/css/app.css', $res->body());
        $this->assertContains('ID permintaan', $res->body());
        $this->assertFalse(str_contains($res->body(), 'rahasia-internal'), 'Pesan internal tidak bocor');
        $nf = $app->handle(Request::create('GET', '/tidak-ada'));
        $this->assertSame(404, $nf->status());
        $this->assertContains('skip-link', $nf->body());
        $this->assertContains("script-src 'self'", (string) $nf->header('Content-Security-Policy'));
    }

    // --- Drift kelas ---

    public function testEveryClassUsedInViewsExistsInBuiltCss(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/css/app.css');
        preg_match_all('/\.((?:\\\\.|[A-Za-z_-])(?:\\\\.|[A-Za-z0-9_-])*)/', $css, $m);
        $known = [];
        foreach ($m[1] as $sel) {
            $known[(string) preg_replace('/\\\\(.)/', '$1', $sel)] = true;
        }
        $this->assertTrue(count($known) > 50, 'CSS terbangun harus terbaca');
        $unknown = [];
        $check = function (string $where, string $classAttr) use (&$unknown, $known): void {
            foreach (preg_split('/\s+/', trim($classAttr)) ?: [] as $tok) {
                if ($tok === '' || str_contains($tok, '{{') || str_contains($tok, '}}') || in_array($tok, self::CLASS_ALLOWLIST, true)) {
                    continue;
                }
                if (!isset($known[$tok])) {
                    $unknown[$where . ' => ' . $tok] = true;
                }
            }
        };
        foreach ($this->viewFiles() as $file) {
            $src = $this->stripPhp((string) file_get_contents($file));
            preg_match_all('/\bclass="([^"]*)"/', $src, $attrs);
            foreach ($attrs[1] as $value) {
                // Token dinamis ({{...}}) dipecah: bagian statis tetap diperiksa.
                $check(basename(dirname($file)) . '/' . basename($file), (string) preg_replace('/\{\{.*?\}\}/', ' ', $value));
            }
        }
        // Kelas yang dipakai modul JS: classList.add/remove/toggle/contains('..'), className = '..', selektor '.kelas'.
        foreach ((array) glob(self::ROOT . '/public/assets/js/*.js') as $jsFile) {
            $js = (string) file_get_contents($jsFile);
            $found = [];
            preg_match_all('/classList\.(?:add|remove|toggle|contains)\(([^)]*)\)/', $js, $calls);
            foreach ($calls[1] as $args) {
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $args, $lits);
                $found = [...$found, ...$lits[1]];
            }
            preg_match_all('/className\s*=\s*[\'"]([^\'"]+)[\'"]/', $js, $cn);
            $found = [...$found, ...$cn[1]];
            // Nilai peta/objek yang dipakai sebagai kelas (mis. TOAST_CLASS) ditulis sebagai literal 'toast-...'.
            preg_match_all('/[\'"](toast[a-z-]*|btn[a-z-]*|nav-item[a-z-]*)[\'"]/', $js, $named);
            $found = [...$found, ...$named[1]];
            preg_match_all('/querySelector(?:All)?\([\'"]([^\'"]+)[\'"]\)/', $js, $sel);
            foreach ($sel[1] as $selector) {
                preg_match_all('/\.([A-Za-z_][A-Za-z0-9_-]*)/', (string) preg_replace('/\[[^\]]*\]/', '', $selector), $dots);
                $found = [...$found, ...$dots[1]];
            }
            foreach ($found as $value) {
                $check('js/' . basename($jsFile), $value);
            }
        }
        foreach ($this->renderAllPages() as $name => $html) {
            foreach ($this->dom($html)->query('//@class') as $attr) {
                $check($name, $attr->nodeValue);
            }
        }
        $this->assertSame([], array_keys($unknown), 'Kelas tanpa aturan CSS (drift): ' . implode('; ', array_keys($unknown)));
    }
}
