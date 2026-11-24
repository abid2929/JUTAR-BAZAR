<?php
// Jutar Bazar core: database, settings, helpers. PHP 8 + SQLite (no setup needed).
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Dhaka');
mb_internal_encoding('UTF-8');

define('JB_ROOT', dirname(__DIR__));
define('JB_VERSION', '2.6.3');

/** Private data folder: ~/jutarbazar-data (outside public_html) if possible, else ./data (blocked by .htaccess). */
function jb_data_dir(): string {
    static $d = null;
    if ($d !== null) return $d;
    $home = getenv('HOME') ?: (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '') : '');
    $cands = [];
    if ($home && is_dir($home) && strpos(JB_ROOT, $home) === 0 && realpath($home) !== realpath(JB_ROOT)) $cands[] = rtrim($home, '/') . '/jutarbazar-data';
    $cands[] = JB_ROOT . '/data';
    foreach ($cands as $c) {
        if (!is_dir($c)) @mkdir($c, 0755, true);
        if (is_dir($c) && is_writable($c)) {
            if (!file_exists("$c/.htaccess")) @file_put_contents("$c/.htaccess", "Require all denied\nDeny from all\n");
            return $d = $c;
        }
    }
    return $d = JB_ROOT . '/data';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $file = jb_data_dir() . '/shop.sqlite';
    $new = !file_exists($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=4000;');
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT);
    CREATE TABLE IF NOT EXISTS collections (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, name_en TEXT DEFAULT '', slug TEXT UNIQUE NOT NULL,
        tagline TEXT DEFAULT '', image TEXT DEFAULT '', sort INTEGER DEFAULT 0, show_home INTEGER DEFAULT 1, active INTEGER DEFAULT 1);
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, collection_id INTEGER,
        brand TEXT DEFAULT '', price INTEGER DEFAULT 0, old_price INTEGER DEFAULT 0, images TEXT DEFAULT '[]',
        sizes TEXT DEFAULT '', colors TEXT DEFAULT '', stock INTEGER DEFAULT 10, short TEXT DEFAULT '', description TEXT DEFAULT '',
        fb_post TEXT DEFAULT '', tags TEXT DEFAULT '', featured INTEGER DEFAULT 0, is_new INTEGER DEFAULT 1, active INTEGER DEFAULT 1,
        sort INTEGER DEFAULT 0, views INTEGER DEFAULT 0, created TEXT, updated TEXT);
    CREATE INDEX IF NOT EXISTS ix_prod_col ON products(collection_id, active);
    CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE, name TEXT, phone TEXT, address TEXT, area TEXT, note TEXT,
        items TEXT, subtotal INTEGER, delivery INTEGER, total INTEGER, status TEXT DEFAULT 'new', admin_note TEXT DEFAULT '',
        seen INTEGER DEFAULT 0, ip TEXT, created TEXT, updated TEXT);
    CREATE INDEX IF NOT EXISTS ix_ord_status ON orders(status);
    CREATE TABLE IF NOT EXISTS hits (ip TEXT, kind TEXT, t INTEGER);
    CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, phone TEXT UNIQUE, email TEXT DEFAULT '', pass TEXT,
        address TEXT DEFAULT '', area TEXT DEFAULT 'in', blocked INTEGER DEFAULT 0, created TEXT, last_login TEXT);
    ");
    $cols = array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
    if (!in_array('customer_id', $cols, true)) $pdo->exec('ALTER TABLE orders ADD COLUMN customer_id INTEGER');
    // v1.3: Bangla copies for the two-language storefront
    $pc = array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(), 'name');
    if (!in_array('short_bn', $pc, true)) $pdo->exec("ALTER TABLE products ADD COLUMN short_bn TEXT DEFAULT ''");
    if (!in_array('description_bn', $pc, true)) $pdo->exec("ALTER TABLE products ADD COLUMN description_bn TEXT DEFAULT ''");
    if (!in_array('fb_post_en', $pc, true)) $pdo->exec("ALTER TABLE products ADD COLUMN fb_post_en TEXT DEFAULT ''");
    // v2.1: Men / Women / Kids sections
    if (!in_array('gender', $pc, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN gender TEXT DEFAULT 'men'");
        $pdo->exec("UPDATE products SET gender='women' WHERE slug IN ('crochet-fringe-slide','cross-strap-platform-sandal','embroidered-slip-on')");
        $pdo->exec("UPDATE products SET gender='unisex' WHERE slug IN ('red-canvas-low','clean-white-court','canvas-high-top','color-pop-runner','urban-court-classic')");
    }
    $cc = array_column($pdo->query('PRAGMA table_info(collections)')->fetchAll(), 'name');
    if (!in_array('tagline_bn', $cc, true)) $pdo->exec("ALTER TABLE collections ADD COLUMN tagline_bn TEXT DEFAULT ''");
    if ($new) { require_once __DIR__ . '/seed.php'; jb_seed($pdo); }
    else jb_three_cats($pdo);
    jb_fee_75($pdo);
    jb_v25($pdo);
    return $pdo;
}

/* v2.4.7: owner wants only 3 categories (Running, Sneakers, Fashion).
   Loafers, Formal and Sandals are merged into one "Fashion" collection, once. */
function jb_three_cats(PDO $pdo): void {
    $done = $pdo->query("SELECT v FROM settings WHERE k='mig_3cats'")->fetchColumn();
    if ($done) return;
    $old = $pdo->query("SELECT id, slug FROM collections WHERE slug IN ('loafers','formal','sandals')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($old) {
        $pdo->beginTransaction();
        $fid = $pdo->query("SELECT id FROM collections WHERE slug='fashion'")->fetchColumn();
        if (!$fid) {
            $pdo->prepare('INSERT INTO collections(name,name_en,slug,tagline,tagline_bn,image,sort) VALUES(?,?,?,?,?,?,?)')
                ->execute(['ফ্যাশন', 'Fashion', 'fashion', 'Loafers, formal shoes and sandals', 'লোফার, ফরমাল জুতা আর স্যান্ডেল', 'assets/img/demo/real/col-loafers.webp', 3]);
            $fid = (int)$pdo->lastInsertId();
        }
        $in = implode(',', array_map('intval', array_keys($old)));
        $pdo->exec("UPDATE products SET collection_id=" . (int)$fid . " WHERE collection_id IN ($in)");
        $pdo->exec("DELETE FROM collections WHERE id IN ($in)");
        $pdo->exec("UPDATE settings SET v='c/fashion' WHERE v IN ('c/loafers','c/formal','c/sandals')");
        $pdo->commit();
    }
    $pdo->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute(['mig_3cats', '1']);
}
/* v2.4.7: owner's new delivery charge (Nov 23): Dhaka 75, outside 135. Applied once to saved settings too. */
function jb_fee_75(PDO $pdo): void {
    if ($pdo->query("SELECT v FROM settings WHERE k='mig_fee75'")->fetchColumn()) return;
    $up = $pdo->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v');
    $up->execute(['delivery_in', '75']); $up->execute(['delivery_out', '135']);
    $pdo->exec("UPDATE settings SET v='৳75' WHERE k LIKE 'of%_big' AND v='৳70'");
    $up->execute(['mig_fee75', '1']);
}

/* v2.5: new admin panel (same as the owner's other shops). Extra columns, visitor counter. Safe to run on every start. */
function jb_v25(PDO $pdo): void {
    $pc = array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(), 'name');
    foreach (['label' => "TEXT DEFAULT ''", 'flash' => 'INTEGER DEFAULT 0', 'best' => 'INTEGER DEFAULT 0', 'gal_colors' => "TEXT DEFAULT '[]'"] as $c => $t)
        if (!in_array($c, $pc, true)) $pdo->exec("ALTER TABLE products ADD COLUMN $c $t");
    $oc = array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
    foreach (['pay' => "TEXT DEFAULT 'cod'", 'trx' => "TEXT DEFAULT ''", 'paid' => 'INTEGER DEFAULT 0', 'src' => "TEXT DEFAULT 'website'", 'courier' => "TEXT DEFAULT ''", 'hist' => "TEXT DEFAULT '[]'"] as $c => $t)
        if (!in_array($c, $oc, true)) $pdo->exec("ALTER TABLE orders ADD COLUMN $c $t");
    $pdo->exec('CREATE TABLE IF NOT EXISTS visits (d TEXT, h TEXT, PRIMARY KEY(d, h)) WITHOUT ROWID; CREATE TABLE IF NOT EXISTS live (h TEXT PRIMARY KEY, t INTEGER, page TEXT DEFAULT \'\')');
}
/** JSON value kept in the settings table (admin users, keys, reviews, catalog collections). */
function jget(string $k, $def = []) { $v = db()->prepare('SELECT v FROM settings WHERE k=?'); $v->execute([$k]); $j = json_decode((string)$v->fetchColumn(), true); return $j ?? $def; }
function jset(string $k, $v): void { db()->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]); }
/** Count one visitor per day (hashed IP + browser, nothing personal stored) and who is browsing now. */
function jb_count_visit(string $page = ''): void {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || preg_match('~bot|crawl|spider|slurp|lighthouse|headless|facebookexternalhit|preview~i', $ua)) return;
    try {
        $h = substr(hash('sha256', client_ip() . '|' . $ua . '|' . date('Y-m-d')), 0, 20); $now = time();
        $pdo = db();
        $pdo->prepare('INSERT OR IGNORE INTO visits(d,h) VALUES(?,?)')->execute([date('Y-m-d'), $h]);
        $pdo->prepare('INSERT OR REPLACE INTO live(h,t,page) VALUES(?,?,?)')->execute([$h, $now, mb_substr($page, 0, 80)]);
        if (mt_rand(1, 50) === 1) { $pdo->prepare('DELETE FROM live WHERE t<?')->execute([$now - 900]); $pdo->prepare('DELETE FROM visits WHERE d<?')->execute([date('Y-m-d', $now - 120 * 86400)]); }
    } catch (Throwable $e) { }
}
function jb_http(string $url, array $opt = []): array {
    if (!function_exists('curl_init')) return ['code' => 0, 'body' => '', 'err' => 'curl missing'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $opt['timeout'] ?? 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => $opt['headers'] ?? []]);
    if (isset($opt['post'])) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['post']); }
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    return ['code' => $code, 'body' => (string)$body, 'err' => $err];
}
/** SMS through any Bangladesh SMS gateway: the URL uses {number} and {message}. */
function jb_sms(string $number, string $msg): array {
    $tpl = (string)(jget('secrets')['sms_url'] ?? '');
    if ($tpl === '' || !preg_match('~^https?://~', $tpl)) return ['ok' => false, 'msg' => 'SMS gateway not set'];
    $num = norm_phone($number); if (strlen($num) === 11) $num = '88' . $num;
    $r = jb_http(str_replace(['{number}', '{message}'], [rawurlencode($num), rawurlencode($msg)], $tpl), ['timeout' => 12]);
    return ['ok' => $r['code'] >= 200 && $r['code'] < 300, 'msg' => substr($r['body'] ?: $r['err'], 0, 200)];
}
/** Meta Conversions API Purchase (server side). Shares the order code as event_id with the browser pixel. */
function jb_capi(string $event, array $o): void {
    $pid = preg_replace('/\D/', '', S('pixel_id')); $sec = jget('secrets'); $tok = (string)($sec['capi_token'] ?? '');
    if ($pid === '' || $tok === '') return;
    $ph = norm_phone((string)($o['phone'] ?? '')); if (strlen($ph) === 11) $ph = '88' . $ph;
    $ev = ['event_name' => $event, 'event_time' => time(), 'action_source' => 'website', 'event_id' => (string)($o['code'] ?? uniqid()),
        'event_source_url' => isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . base_url() : '',
        'user_data' => array_filter(['ph' => [hash('sha256', $ph)], 'client_ip_address' => client_ip(), 'client_user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'fbp' => $_COOKIE['_fbp'] ?? null, 'fbc' => $_COOKIE['_fbc'] ?? null, 'country' => [hash('sha256', 'bd')]]),
        'custom_data' => ['currency' => 'BDT', 'value' => (float)($o['total'] ?? 0), 'content_type' => 'product',
            'contents' => array_map(fn($i) => ['id' => (string)$i['id'], 'quantity' => (int)$i['qty'], 'item_price' => (float)$i['price']], $o['items'] ?? [])]];
    $payload = ['data' => [$ev], 'access_token' => $tok];
    if (!empty($sec['capi_test'])) $payload['test_event_code'] = (string)$sec['capi_test'];
    $r = jb_http("https://graph.facebook.com/v19.0/$pid/events", ['post' => json_encode($payload), 'headers' => ['Content-Type: application/json'], 'timeout' => 8]);
    $log = jget('capi_log'); $log[] = ['t' => time(), 'e' => $event, 'code' => $r['code']]; jset('capi_log', array_slice($log, -200));
}

/* ---------- settings ---------- */
function jb_defaults(): array {
    return [
        'shop_name' => 'জুতার বাজার বিডি',
        'shop_name_en' => 'JUTAR BAZAR BD',
        'logo' => '',
        'tagline' => 'Step into more',
        'announce' => 'Cash on delivery all over Bangladesh  •  Easy size exchange  •  Every pair checked before dispatch',
        'hero_on' => '0',
        'hero_kicker' => 'NEW SEASON 2026',
        'hero_title' => 'প্রতিটি পদক্ষেপে আভিজাত্য',
        'hero_title_en' => 'Walk in Style',
        'hero_sub' => 'Running, sneakers, loafers, formal and sandals. Handpicked pairs that look sharp and feel right, delivered to your door.',
        'hero_btn' => 'Shop now',
        'hero_image' => '',
        'promo_image' => '',
        'promo_title' => 'The new collection is here',
        'promo_sub' => 'From running to formal, new designs land every week. Find your pair today.',
        'promo_btn' => 'Explore new arrivals',
        'free_over' => '0',
        'about' => 'We believe a good pair of shoes starts a good day. So we pick every pair for quality, help you get the right size, and keep our prices honest.',
        'phone' => '01624-244920',
        'whatsapp' => '8801624244920',
        'email' => '',
        'address' => 'Opposite Jamuna Future Park, Nandha Bus Stand (Life BD Shop), Dhaka',
        'facebook' => 'https://www.facebook.com/share/1A5UHxTjZH/',
        'instagram' => '',
        'tiktok' => '',
        'youtube' => '',
        'delivery_in' => '75',
        'delivery_out' => '135',
        'delivery_in_label' => 'ঢাকা সিটির ভেতরে',
        'delivery_out_label' => 'ঢাকা সিটির বাইরে',
        'currency' => '৳',
        'accent' => '#e5131d',
        'gemini_key' => '',
        'notify_email' => '',
        'meta_desc' => 'JUTAR BAZAR BD: running shoes, sneakers, loafers, formal shoes and sandals. Cash on delivery all over Bangladesh.',
        'pixel_id' => '',
        'ga_id' => '',
        'wa_popup' => 'Looking for a pair? Ask us anything about size, price or your order on WhatsApp.',
        'wa_popup_sec' => '6',
        'footer_note' => 'Comfort, style and trust in every pair.',
        // Bangla versions (used when the visitor switches to বাংলা)
        'hero_kicker_bn' => 'নতুন সিজন ২০২৬',
        'hero_sub_bn' => 'রানিং, স্নিকার্স, লোফার, ফরমাল আর স্যান্ডেল। বাছাই করা প্রতিটি জোড়া দেখতে দারুণ, পরতে আরাম, পৌঁছে যাবে আপনার দরজায়।',
        'hero_btn_bn' => 'এখনই কিনুন',
        'promo_title_bn' => 'নতুন কালেকশন চলে এসেছে',
        'promo_sub_bn' => 'রানিং থেকে ফরমাল, প্রতি সপ্তাহে নতুন ডিজাইন। আজই বেছে নিন আপনার জোড়া।',
        'promo_btn_bn' => 'নতুন জুতা দেখুন',
        'about_bn' => 'আমরা বিশ্বাস করি, ভালো একজোড়া জুতা দিয়েই ভালো দিনের শুরু। তাই প্রতিটি জোড়া মান দেখে বাছাই করি, সঠিক সাইজ পেতে সাহায্য করি, আর দাম রাখি সৎ।',
        'address_bn' => 'যমুনা ফিউচার পার্কের বিপরীতে, নন্দা বাস স্ট্যান্ড (Life BD Shop), ঢাকা',
        'meta_desc_bn' => 'জুতার বাজার বিডি: অনলাইনে কেনাকাটার বিশ্বস্ত প্ল্যাটফর্ম। রানিং শু, স্নিকার্স, লোফার, ফরমাল শু আর স্যান্ডেল। সারা বাংলাদেশে ক্যাশ অন ডেলিভারি।',
        'wa_popup_bn' => 'জুতা খুঁজছেন? সাইজ, দাম বা অর্ডার নিয়ে যেকোনো প্রশ্ন হোয়াটসঅ্যাপে করুন।',
        'footer_note_bn' => 'প্রতিটি জোড়ায় আরাম, স্টাইল আর আস্থা।',
        'size_guide' => '1',
        'of1_on' => '1',
        'of1_l1' => 'RUNNING',
        'of1_l2' => 'SHOES',
        'of1_top' => 'UP TO',
        'of1_big' => 'auto',
        'of1_end' => 'OFF',
        'of1_theme' => 'purple',
        'ht_on' => '1',
        'ht_title' => 'কোনটা খুঁজছেন?',
        'ht_title_en' => 'Shop by style',
        'ht1_bn' => 'রানিং শু', 'ht1_en' => 'Running', 'ht1_link' => 'c/running', 'ht1_color' => 'purple', 'ht1_img' => 'assets/img/demo/offer/ow-run.webp',
        'ht2_bn' => 'স্নিকার্স', 'ht2_en' => 'Sneakers', 'ht2_link' => 'c/sneakers', 'ht2_color' => 'teal', 'ht2_img' => 'assets/img/demo/offer/ow-snk.webp',
        'ht3_bn' => 'ফ্যাশন', 'ht3_en' => 'Fashion', 'ht3_link' => 'c/fashion', 'ht3_color' => 'red', 'ht3_img' => 'assets/img/demo/offer/lof-1.webp',
        'of1_img' => 'assets/img/demo/offer/ow-run.webp',
        'of1_link' => 'c/running',
        'of2_on' => '1',
        'of2_l1' => 'SNEAKERS',
        'of2_l2' => 'COLLECTION',
        'of2_top' => 'UP TO',
        'of2_big' => 'auto',
        'of2_end' => 'OFF',
        'of2_theme' => 'teal',
        'of2_img' => 'assets/img/demo/offer/ow-snk.webp',
        'of2_link' => 'c/sneakers',
        'of3_on' => '1',
        'of3_l1' => 'FASHION',
        'of3_l2' => 'SHOES',
        'of3_top' => 'UP TO',
        'of3_big' => 'auto',
        'of3_end' => 'OFF',
        'of3_theme' => 'red',
        'of3_img' => 'assets/img/demo/offer/lof-4.webp',
        'of3_link' => 'c/fashion',
        'of4_on' => '1',
        'of4_l1' => 'CASH ON',
        'of4_l2' => 'DELIVERY',
        'of4_top' => 'ALL OVER',
        'of4_big' => 'BD',
        'of4_end' => '',
        'of4_theme' => 'navy',
        'of4_img' => 'assets/img/demo/offer/run-4.webp',
        'of4_link' => 'shop',
        'of5_on' => '1',
        'of5_l1' => 'DHAKA',
        'of5_l2' => 'DELIVERY',
        'of5_top' => 'ONLY',
        'of5_big' => '৳75',
        'of5_end' => '',
        'of5_theme' => 'green',
        'of5_img' => 'assets/img/demo/offer/snk-5.webp',
        'of5_link' => 'shop',
        'of6_on' => '1',
        'of6_l1' => 'EASY SIZE',
        'of6_l2' => 'EXCHANGE',
        'of6_top' => '',
        'of6_big' => '',
        'of6_end' => '',
        'of6_theme' => 'maroon',
        'of6_img' => 'assets/img/demo/offer/frm-2.webp',
        'of6_link' => 'shop',
        'of7_on' => '0',
        'of7_l1' => 'LOAFERS',
        'of7_l2' => 'COLLECTION',
        'of7_top' => 'UP TO',
        'of7_big' => 'auto',
        'of7_end' => 'OFF!',
        'of7_theme' => 'maroon',
        'of7_img' => 'assets/img/demo/offer/lof-4.webp',
        'of7_link' => 'c/fashion',
        'of8_on' => '0',
        'of8_l1' => 'RUNNING',
        'of8_l2' => 'SHOES',
        'of8_top' => 'UP TO',
        'of8_big' => 'auto',
        'of8_end' => 'OFF!',
        'of8_theme' => 'navy',
        'of8_img' => 'assets/img/demo/offer/run-4.webp',
        'of8_link' => 'c/running',
        'of9_on' => '0',
        'of9_l1' => 'SANDALS',
        'of9_l2' => 'COLLECTION',
        'of9_top' => 'UP TO',
        'of9_big' => 'auto',
        'of9_end' => 'OFF!',
        'of9_theme' => 'green',
        'of9_img' => 'assets/img/demo/offer/snd-2.webp',
        'of9_link' => 'c/fashion',
        'of_note' => '',
        // v2.5 new admin
        'announce_on' => '0', 'announce_bn' => '', 'delivery_note_bn' => '', 'pay_note_bn' => '',
        'pay_cod' => '1', 'pay_bkash' => '', 'pay_nagad' => '', 'pay_note' => '',
        'seo_title' => '', 'meta_keys' => '', 'gsv' => '', 'hours' => '', 'map' => '', 'delivery_note' => '',
    ];
}
function settings(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = jb_defaults();
    foreach (db()->query('SELECT k,v FROM settings') as $r) $s[$r['k']] = $r['v'];
    return $s;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute([$k, $v]);
}
function S(string $k): string { return (string)(settings()[$k] ?? ''); }

/* ---------- helpers ---------- */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function base_url(): string {
    static $b = null;
    if ($b !== null) return $b;
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (basename($dir) === 'admin') $dir = dirname($dir);
    return $b = rtrim($dir, '/') . '/';
}
function url(string $path = ''): string { return base_url() . ltrim($path, '/'); }
/* ---------- language (storefront: English by default, বাংলা on switch; admin is always Bangla) ---------- */
function lang(): string {
    static $l = null;
    if ($l !== null) return $l;
    if (!defined('JB_EN')) return $l = 'bn';
    $q = (string)($_GET['lang'] ?? '');
    if ($q === 'en' || $q === 'bn') { if (!headers_sent()) setcookie('jb_lang', $q, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']); return $l = $q; }
    $c = (string)($_COOKIE['jb_lang'] ?? '');
    return $l = ($c === 'en' ? 'en' : 'bn'); // Bangla first (most orders come from outside Dhaka); English only when chosen
}
function is_bn(): bool { return lang() === 'bn'; }
/** Pick the English or Bangla text for the current visitor. */
function t(string $en, string $bn): string { return is_bn() ? $bn : $en; }
/** Setting in the visitor's language: uses KEY_bn when Bangla is on and it is filled in. */
function SL(string $k): string { if (is_bn()) { $v = S($k . '_bn'); if (trim($v) !== '') return $v; } return S($k); }
function col_name(array $c): string { return is_bn() ? ($c['name'] ?: $c['name_en']) : (($c['name_en'] ?? '') ?: $c['name']); }
function col_tag(array $c): string { return is_bn() && trim((string)($c['tagline_bn'] ?? '')) !== '' ? $c['tagline_bn'] : (string)($c['tagline'] ?? ''); }
function p_short(array $p): string { return is_bn() && trim((string)($p['short_bn'] ?? '')) !== '' ? $p['short_bn'] : (string)($p['short'] ?? ''); }
function p_desc(array $p): string { return is_bn() && trim((string)($p['description_bn'] ?? '')) !== '' ? $p['description_bn'] : (string)($p['description'] ?? ''); }
/** Same page in the other language. */
function lang_url(string $to): string {
    $u = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $q = $_GET; unset($q['lang'], $q['route'], $q['r']); $q['lang'] = $to;
    return $u . '?' . http_build_query($q);
}
function bn_num($n): string { if (defined('JB_EN') && !is_bn()) return (string)$n; return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']); }
function money($n): string { return S('currency') . bn_num(number_format((int)$n)); }
function slugify(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? mb_substr($s, 0, 80) : 'item-' . substr(md5((string)microtime(true)), 0, 6);
}
function unique_slug(string $table, string $base, int $ignoreId = 0): string {
    $slug = slugify($base); $try = $slug; $i = 2;
    $st = db()->prepare("SELECT id FROM $table WHERE slug=? AND id<>?");
    while (true) { $st->execute([$try, $ignoreId]); if (!$st->fetch()) return $try; $try = $slug . '-' . $i++; }
}
function img_url(string $p): string {
    if ($p === '') return url('assets/img/placeholder.svg');
    if (preg_match('~^https?://~', $p)) return $p;
    return url($p);
}
function p_images(array $p): array { $a = json_decode($p['images'] ?: '[]', true); return is_array($a) ? array_values(array_filter($a)) : []; }
function p_first_image(array $p): string { $a = p_images($p); return img_url($a[0] ?? ''); }
function list_csv(string $s): array { return array_values(array_filter(array_map('trim', preg_split('/[,،\n]+/u', $s)), 'strlen')); }
function discount_pct(array $p): int { return ($p['old_price'] > $p['price'] && $p['old_price'] > 0) ? (int)round(100 - $p['price'] * 100 / $p['old_price']) : 0; }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function rate_ok(string $kind, int $max, int $secs): bool {
    $pdo = db(); $now = time();
    $pdo->prepare('DELETE FROM hits WHERE t<?')->execute([$now - 86400]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM hits WHERE ip=? AND kind=? AND t>?');
    $st->execute([client_ip(), $kind, $now - $secs]);
    if ((int)$st->fetchColumn() >= $max) return false;
    $pdo->prepare('INSERT INTO hits(ip,kind,t) VALUES(?,?,?)')->execute([client_ip(), $kind, $now]);
    return true;
}
function collections(bool $activeOnly = true): array {
    return db()->query('SELECT * FROM collections' . ($activeOnly ? ' WHERE active=1' : '') . ' ORDER BY sort, id')->fetchAll();
}
function json_out($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
const ORDER_STATUS = [
    'new' => 'নতুন', 'confirmed' => 'কনফার্মড', 'shipped' => 'পাঠানো হয়েছে',
    'delivered' => 'ডেলিভারি সম্পন্ন', 'cancelled' => 'বাতিল', 'returned' => 'ফেরত',
];

/* ---------- customer accounts ---------- */
function shop_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('jbshop');
    session_set_cookie_params(['lifetime' => 86400 * 30, 'path' => base_url(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    ini_set('session.gc_maxlifetime', (string)(86400 * 30));
    session_start();
}
function customer(): ?array {
    static $c = false;
    if ($c !== false) return $c;
    if (empty($_COOKIE['jbshop'])) return $c = null;
    shop_session();
    $id = (int)($_SESSION['cid'] ?? 0);
    if (!$id) return $c = null;
    $st = db()->prepare('SELECT * FROM customers WHERE id=? AND blocked=0'); $st->execute([$id]);
    return $c = ($st->fetch() ?: null);
}
function norm_phone(string $p): string {
    $p = strtr($p, ['০'=>'0','১'=>'1','২'=>'2','৩'=>'3','৪'=>'4','৫'=>'5','৬'=>'6','৭'=>'7','৮'=>'8','৯'=>'9']);
    $d = preg_replace('/\D/', '', $p);
    if (strlen($d) === 13 && str_starts_with($d, '880')) $d = substr($d, 2);
    return $d;
}
function shop_csrf(): string { shop_session(); if (empty($_SESSION['scsrf'])) $_SESSION['scsrf'] = bin2hex(random_bytes(16)); return $_SESSION['scsrf']; }
function shop_csrf_ok(): bool { shop_session(); $t = $_POST['csrf'] ?? ''; return is_string($t) && !empty($_SESSION['scsrf']) && hash_equals($_SESSION['scsrf'], $t); }

/* ---------- Men / Women / Kids ---------- */
function genders(): array {
    return ['men' => ['Men', 'ছেলেদের'], 'women' => ['Women', 'মেয়েদের'], 'kids' => ['Kids', 'বাচ্চাদের']];
}

