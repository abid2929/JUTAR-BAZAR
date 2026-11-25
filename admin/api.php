<?php
/* JUTAR BAZAR BD admin API. Same menus and data shape as the owner's other shop admins,
   but everything is read from and saved to the shop's own SQLite database (inc/core.php),
   so the storefront, old orders and products keep working exactly as before. */
declare(strict_types=1);
require dirname(__DIR__) . '/inc/core.php';
require dirname(__DIR__) . '/inc/caption.php';
require dirname(__DIR__) . '/inc/images.php';
session_name('jbadmin2');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'path' => base_url(), 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function out($a, int $code = 200): never { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function body(): array { static $b = null; if ($b === null) { $j = json_decode((string)file_get_contents('php://input'), true); $b = is_array($j) ? $j : []; } return $b; }
function me(): ?array { return $_SESSION['u'] ?? null; }
const ROLES = [
  'super'   => ['*'],
  'manager' => ['dashboard', 'products', 'inventory', 'categories', 'collections', 'orders', 'courier', 'customers', 'reviews', 'banners', 'cms', 'reports', 'settings'],
  'staff'   => ['dashboard', 'products', 'inventory', 'orders', 'courier', 'customers'],
];
function can(string $perm): bool { $u = me(); if (!$u) return false; $p = ROLES[$u['role']] ?? []; return in_array('*', $p, true) || in_array($perm, $p, true); }
function need(string $perm): void { if (!me()) out(['error' => 'login'], 401); if (!can($perm)) out(['error' => 'You do not have permission for this.'], 403); }
function audit(string $what): void { $l = jget('admin_log'); $l[] = ['t' => time(), 'u' => me()['u'] ?? '?', 'w' => $what]; jset('admin_log', array_slice($l, -300)); }
function cs($v, int $n = 300): string { return trim(mb_substr((string)$v, 0, $n)); }
function img_ok(string $s): bool { return (bool)preg_match('~^(https://\S+|(uploads|assets)/[A-Za-z0-9_\-/.]+\.(jpe?g|png|webp|gif|svg))$~i', $s) && strpos($s, '..') === false; }

/* ---------- admin users (the old single password becomes user "admin") ---------- */
/* Forgot the password? Make an empty file "reset-admin.txt" in public_html (cPanel File Manager > + File).
   The login page then lets you make a new main admin; the file is deleted right after. Other admins stay. */
function reset_mode(): bool { return is_file(JB_ROOT . '/reset-admin.txt'); }
function users(): array {
  if (reset_mode()) return [];
  $u = jget('admin_users');
  if (!$u && S('admin_hash') !== '') { $u = [['u' => 'admin', 'name' => 'Owner', 'role' => 'super', 'hash' => S('admin_hash'), 'on' => 1]]; jset('admin_users', $u); }
  return $u;
}
/* v2.6.1: one-time password from inc/set-pass.txt (a password_hash, never plain text). Sets main admin "admin", then the file is deleted. */
if (is_file(JB_ROOT . '/inc/set-pass.txt')) {
  $h = trim((string)file_get_contents(JB_ROOT . '/inc/set-pass.txt'));
  if (strlen($h) > 50 && $h[0] === '$') {
    $l = array_values(array_filter(jget('admin_users'), fn($x) => $x['u'] !== 'admin'));
    array_unshift($l, ['u' => 'admin', 'name' => 'Owner', 'role' => 'super', 'hash' => $h, 'on' => 1]);
    jset('admin_users', $l); set_setting('admin_hash', $h);
    if (is_file(JB_ROOT . '/reset-admin.txt')) @unlink(JB_ROOT . '/reset-admin.txt');
  }
  @unlink(JB_ROOT . '/inc/set-pass.txt');
}
$a = (string)($_GET['a'] ?? '');
$users = users();

if ($a === 'state') out(['setup' => !$users, 'me' => me(), 'csrf' => me() ? $_SESSION['csrf'] : null, 'roles' => array_keys(ROLES), 'shop' => ['name' => S('shop_name'), 'en' => S('shop_name_en'), 'v' => JB_VERSION]]);

if ($a === 'login') {
  $b = body(); $un = strtolower(cs($b['user'] ?? '', 40)); $pw = (string)($b['pass'] ?? '');
  if (!rate_ok('login', 8, 900)) out(['error' => 'Too many tries. Please wait 15 minutes and try again.'], 429);
  if ($un === '') {   // v2.6.1: password-only sign-in = the main admin
    $un = 'admin';
    if ($users && !array_filter($users, fn($x) => $x['u'] === 'admin')) foreach ($users as $x) if ($x['role'] === 'super' && !empty($x['on'])) { $un = $x['u']; break; }
  }
  if (!$users) {   // first run: create the super admin
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $un) || strlen($pw) < 6) out(['error' => 'Password must be 6+ characters.'], 400);
    $users = [['u' => $un, 'name' => cs($b['name'] ?? 'Owner', 60) ?: 'Owner', 'role' => 'super', 'hash' => password_hash($pw, PASSWORD_DEFAULT), 'on' => 1]];
    $keep = reset_mode() ? array_values(array_filter(jget('admin_users'), fn($x) => $x['u'] !== $un)) : [];
    jset('admin_users', array_merge($users, $keep)); set_setting('admin_hash', $users[0]['hash']);
    if (reset_mode()) @unlink(JB_ROOT . '/reset-admin.txt');
    $users = jget('admin_users');
  }
  $found = null; foreach ($users as $u) if ($u['u'] === $un && !empty($u['on'])) $found = $u;
  if (!$found || !password_verify($pw, $found['hash'])) { usleep(600000); out(['error' => 'Wrong username or password.'], 403); }
  db()->prepare('DELETE FROM hits WHERE ip=? AND kind=?')->execute([client_ip(), 'login']);
  session_regenerate_id(true);
  $_SESSION['u'] = ['u' => $found['u'], 'name' => $found['name'], 'role' => $found['role']]; $_SESSION['csrf'] = bin2hex(random_bytes(16));
  foreach ($users as &$x) if ($x['u'] === $un) $x['last'] = time(); unset($x); jset('admin_users', $users);
  out(['ok' => 1, 'me' => $_SESSION['u'], 'csrf' => $_SESSION['csrf']]);
}
if ($a === 'logout') { $_SESSION = []; session_destroy(); out(['ok' => 1]); }

if (!me()) out(['error' => 'login'], 401);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', (string)($_SERVER['HTTP_X_PV'] ?? ''))) out(['error' => 'Session expired. Please log in again.'], 403);
$pdo = db(); $now = date('Y-m-d H:i:s');

/* ---------- mapping: SQLite <-> admin JSON ---------- */
const ST_IN = ['new' => 'pending', 'confirmed' => 'processing', 'shipped' => 'shipped', 'delivered' => 'delivered', 'cancelled' => 'cancelled', 'returned' => 'returned'];
function st_out(string $s): string { return array_flip(ST_IN)[$s] ?? 'new'; }
function cols_by_id(): array { return array_column(collections(false), null, 'id'); }
function prod_json(array $p): array {
  $imgs = p_images($p); $gc = json_decode((string)($p['gal_colors'] ?? '[]'), true) ?: [];
  $x = ['id' => 'j' . $p['id'], 'n' => $p['name'], 'b' => (string)$p['brand'], 'p' => (int)$p['price'], 'st' => (int)$p['stock'], 'slug' => $p['slug'], 'gx' => $p['gender'] ?: 'men', 'views' => (int)$p['views']];
  if ((int)$p['old_price'] > (int)$p['price']) $x['o'] = (int)$p['old_price'];
  if ($imgs) $x['i'] = $imgs[0];
  foreach (array_slice($imgs, 1) as $k => $s) $x['g'][] = ['s' => $s, 'c' => (string)($gc[$k + 1] ?? '')];
  if (!empty($gc[0])) $x['ic'] = (string)$gc[0];
  foreach (['t' => 'label', 'sz' => 'sizes', 'cl' => 'colors', 'sd' => 'short', 'd' => 'description', 'sdb' => 'short_bn', 'db' => 'description_bn', 'fb' => 'fb_post', 'fbe' => 'fb_post_en', 'tg' => 'tags'] as $k => $c) if (trim((string)($p[$c] ?? '')) !== '') $x[$k] = (string)$p[$c];
  foreach (['ft' => 'featured', 'nw' => 'is_new', 'fs' => 'flash', 'bs' => 'best'] as $k => $c) if (!empty($p[$c])) $x[$k] = 1;
  if (empty($p['active'])) $x['off'] = 1;
  return $x;
}
function get_products(): array {
  $cols = cols_by_id(); $out = [];
  foreach ($cols as $c) $out[$c['slug']] = [];
  foreach (db()->query('SELECT * FROM products ORDER BY sort, id DESC') as $p) { $k = $cols[$p['collection_id']]['slug'] ?? 'other'; $out[$k][] = prod_json($p); }
  return $out;
}
function get_categories(): array {
  $n = []; foreach (db()->query('SELECT collection_id, COUNT(*) c FROM products GROUP BY collection_id') as $r) $n[(int)$r['collection_id']] = (int)$r['c'];
  return array_map(fn($c) => ['k' => $c['slug'], 'name' => $c['name'], 'en' => $c['name_en'], 'tag' => $c['tagline'], 'tagb' => $c['tagline_bn'] ?? '', 'img' => $c['image'], 'order' => (int)$c['sort'], 'on' => (int)$c['active'], 'home' => (int)$c['show_home'], 'n' => $n[(int)$c['id']] ?? 0], collections(false));
}
function order_json(array $r): array {
  $items = array_map(fn($i) => ['id' => 'j' . (int)($i['id'] ?? 0), 'n' => (string)($i['name'] ?? ''), 'q' => (int)($i['qty'] ?? 1), 'p' => (int)($i['price'] ?? 0),
    'v' => trim(implode(' · ', array_filter([($i['size'] ?? '') !== '' ? 'Size ' . $i['size'] : '', (string)($i['color'] ?? '')])))], json_decode((string)$r['items'], true) ?: []);
  $o = ['no' => $r['code'] ?: 'JB-' . (1000 + (int)$r['id']), 'id' => (int)$r['id'], 't' => strtotime((string)$r['created']) ?: time(), 'name' => (string)$r['name'], 'phone' => (string)$r['phone'], 'address' => (string)$r['address'],
    'area' => $r['area'] === 'out' ? 'out' : 'in', 'note' => (string)$r['note'], 'pay' => (string)($r['pay'] ?: 'cod'), 'trx' => (string)($r['trx'] ?? ''), 'paid' => (int)($r['paid'] ?? 0), 'items' => $items,
    'sub' => (int)$r['subtotal'], 'ship' => (int)$r['delivery'], 'total' => (int)$r['total'], 'status' => ST_IN[$r['status']] ?? 'pending', 'admin_note' => (string)$r['admin_note'], 'src' => (string)($r['src'] ?: 'website'),
    'hist' => json_decode((string)($r['hist'] ?? '[]'), true) ?: []];
  $c = json_decode((string)($r['courier'] ?? ''), true); if ($c) $o['courier'] = $c;
  if (!empty($r['customer_id'])) $o['acc'] = (int)$r['customer_id'];
  return $o;
}
function find_order(string $no): ?array { $st = db()->prepare('SELECT * FROM orders WHERE code=?'); $st->execute([$no]); return $st->fetch() ?: null; }
/* settings groups shown in the admin -> settings keys */
const SITE_MAP = [
  'store' => ['name' => 'shop_name', 'name_en' => 'shop_name_en', 'tagline' => 'tagline', 'phone' => 'phone', 'whatsapp' => 'whatsapp', 'email' => 'email', 'address' => 'address', 'address_bn' => 'address_bn',
    'facebook' => 'facebook', 'instagram' => 'instagram', 'tiktok' => 'tiktok', 'youtube' => 'youtube', 'hours' => 'hours', 'map' => 'map', 'logo' => 'logo', 'notify_email' => 'notify_email', 'accent' => 'accent'],
  'delivery' => ['inside' => 'delivery_in', 'outside' => 'delivery_out', 'free_over' => 'free_over', 'in_label' => 'delivery_in_label', 'out_label' => 'delivery_out_label', 'note' => 'delivery_note', 'note_bn' => 'delivery_note_bn'],
  'payment' => ['cod' => 'pay_cod', 'bkash' => 'pay_bkash', 'nagad' => 'pay_nagad', 'note' => 'pay_note', 'note_bn' => 'pay_note_bn'],
  'seo' => ['title' => 'seo_title', 'desc' => 'meta_desc', 'desc_bn' => 'meta_desc_bn', 'keys' => 'meta_keys', 'ga' => 'ga_id', 'gsv' => 'gsv'],
  'pixel' => ['id' => 'pixel_id'],
  'cms' => ['announce_on' => 'announce_on', 'announce' => 'announce', 'announce_bn' => 'announce_bn', 'about' => 'about', 'about_bn' => 'about_bn', 'wa_popup' => 'wa_popup', 'wa_popup_bn' => 'wa_popup_bn', 'wa_popup_sec' => 'wa_popup_sec', 'footer_note' => 'footer_note', 'footer_note_bn' => 'footer_note_bn',
    'size_guide' => 'size_guide', 'ht_on' => 'ht_on', 'ht_title' => 'ht_title', 'ht_title_en' => 'ht_title_en',
    'ht1_bn' => 'ht1_bn', 'ht1_en' => 'ht1_en', 'ht1_link' => 'ht1_link', 'ht1_color' => 'ht1_color', 'ht1_img' => 'ht1_img', 'ht2_bn' => 'ht2_bn', 'ht2_en' => 'ht2_en', 'ht2_link' => 'ht2_link', 'ht2_color' => 'ht2_color', 'ht2_img' => 'ht2_img',
    'ht3_bn' => 'ht3_bn', 'ht3_en' => 'ht3_en', 'ht3_link' => 'ht3_link', 'ht3_color' => 'ht3_color', 'ht3_img' => 'ht3_img',
    'promo_title' => 'promo_title', 'promo_title_bn' => 'promo_title_bn', 'promo_sub' => 'promo_sub', 'promo_sub_bn' => 'promo_sub_bn', 'promo_btn' => 'promo_btn', 'promo_btn_bn' => 'promo_btn_bn', 'promo_image' => 'promo_image'],
];
const IMG_KEYS = ['logo', 'ht1_img', 'ht2_img', 'ht3_img', 'promo_image'];
function get_site(): array { $o = []; foreach (SITE_MAP as $g => $m) foreach ($m as $k => $sk) $o[$g][$k] = S($sk); $o['shop'] = ['v' => JB_VERSION]; return $o; }
const BAN_F = ['on', 'l1', 'l2', 'top', 'big', 'end', 'theme', 'img', 'link'];
function get_banners(): array { $o = []; for ($i = 1; $i <= 9; $i++) { $b = ['n' => $i]; foreach (BAN_F as $f) $b[$f] = S("of{$i}_$f"); $o[] = $b; } return $o; }

$READ = ['products' => 'products', 'categories' => 'products', 'collections' => 'products', 'reviews' => 'reviews', 'banners' => 'banners', 'site' => 'dashboard', 'orders' => 'orders'];
$WRITE = ['products' => 'products', 'categories' => 'categories', 'collections' => 'collections', 'reviews' => 'reviews', 'banners' => 'banners', 'site' => 'settings'];

if ($a === 'get') {
  $c = (string)($_GET['c'] ?? '');
  if (!isset($READ[$c])) out(['error' => 'Unknown data'], 404);
  need($READ[$c]);
  $d = match ($c) {
    'products' => get_products(), 'categories' => get_categories(), 'collections' => jget('catalog_cols'), 'reviews' => jget('reviews'), 'banners' => get_banners(), 'site' => get_site(),
    'orders' => array_map('order_json', $pdo->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll()),
  };
  out(['data' => $d]);
}

if ($a === 'put') {
  $c = (string)($_GET['c'] ?? ''); $d = body()['data'] ?? null;
  if (!isset($WRITE[$c]) || !is_array($d)) out(['error' => 'Bad data'], 400);
  need($WRITE[$c]);
  if (strlen((string)json_encode($d)) > 4000000) out(['error' => 'Too big'], 413);

  if ($c === 'products') {
    $cols = array_column(collections(false), 'id', 'slug');
    $seen = []; $total = 0;
    foreach ($d as $list) $total += is_array($list) ? count($list) : 0;
    $have = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($total === 0 && $have > 2) out(['error' => 'Nothing to save (safety stop). Reload the page and try again.'], 400);
    $pdo->beginTransaction();
    try {
      $sort = 0;
      foreach ($d as $k => $list) {
        if (!is_array($list)) continue; $cid = $cols[(string)$k] ?? null;
        foreach ($list as $x) {
          if (!is_array($x) || cs($x['n'] ?? '') === '') continue; $sort++;
          $imgs = []; $gc = [];
          if (!empty($x['i']) && img_ok((string)$x['i'])) { $imgs[] = (string)$x['i']; $gc[] = cs($x['ic'] ?? '', 40); }
          elseif (!empty($x['r']) && img_ok((string)$x['r'])) { $imgs[] = (string)$x['r']; $gc[] = ''; }
          foreach (array_slice((array)($x['g'] ?? []), 0, 15) as $g) { $s = (string)($g['s'] ?? ''); if (img_ok($s)) { $imgs[] = $s; $gc[] = cs($g['c'] ?? '', 40); } }
          $p = (int)($x['p'] ?? 0); $o = (int)($x['o'] ?? 0);
          $f = ['name' => cs($x['n'], 160), 'collection_id' => $cid, 'brand' => cs($x['b'] ?? '', 60), 'label' => cs($x['t'] ?? '', 40),
            'gender' => in_array($x['gx'] ?? '', ['men', 'women', 'kids', 'unisex'], true) ? $x['gx'] : 'men',
            'price' => max(0, $p), 'old_price' => $o > $p ? $o : 0, 'images' => json_encode($imgs, JSON_UNESCAPED_SLASHES), 'gal_colors' => json_encode($gc, JSON_UNESCAPED_UNICODE),
            'sizes' => implode(',', list_csv(cs($x['sz'] ?? '', 200))), 'colors' => implode(', ', list_csv(cs($x['cl'] ?? '', 200))), 'stock' => max(0, (int)($x['st'] ?? 0)),
            'short' => cs($x['sd'] ?? '', 600), 'description' => cs($x['d'] ?? '', 6000), 'short_bn' => cs($x['sdb'] ?? '', 600), 'description_bn' => cs($x['db'] ?? '', 6000),
            'fb_post' => cs($x['fb'] ?? '', 4000), 'fb_post_en' => cs($x['fbe'] ?? '', 4000), 'tags' => cs($x['tg'] ?? '', 300),
            'featured' => empty($x['ft']) ? 0 : 1, 'is_new' => empty($x['nw']) ? 0 : 1, 'flash' => empty($x['fs']) ? 0 : 1, 'best' => empty($x['bs']) ? 0 : 1, 'active' => empty($x['off']) ? 1 : 0, 'sort' => $sort, 'updated' => $now];
          // empty text? write it automatically, like the old admin did
          if ($f['short'] === '' && $f['description'] === '' || $f['short_bn'] === '' && $f['description_bn'] === '') {
            $cc = $cid ? $pdo->query('SELECT name,slug FROM collections WHERE id=' . (int)$cid)->fetch() : null;
            $cap = caption_template(['name' => $f['name'], 'collection' => $cc['name'] ?? '', 'collection_slug' => $cc['slug'] ?? '', 'brand' => $f['brand'], 'price' => $f['price'], 'old_price' => $f['old_price'], 'sizes' => $f['sizes'], 'colors' => $f['colors'], 'gender' => $f['gender']]);
            if ($f['short'] === '' && $f['description'] === '') foreach (['short', 'description'] as $q) $f[$q] = $cap[$q];
            if ($f['short_bn'] === '' && $f['description_bn'] === '') foreach (['short_bn', 'description_bn'] as $q) $f[$q] = $cap[$q];
            foreach (['fb_post', 'fb_post_en', 'tags'] as $q) if ($f[$q] === '') $f[$q] = $cap[$q] ?? '';
          }
          $id = preg_match('/^j(\d+)$/', (string)($x['id'] ?? ''), $m) ? (int)$m[1] : 0;
          if ($id && $pdo->query('SELECT 1 FROM products WHERE id=' . $id)->fetchColumn()) {
            $set = implode(',', array_map(fn($q) => "$q=?", array_keys($f)));
            $pdo->prepare("UPDATE products SET $set WHERE id=?")->execute([...array_values($f), $id]);
          } else {
            $f['slug'] = unique_slug('products', $f['name']); $f['created'] = $now;
            $pdo->prepare('INSERT INTO products(' . implode(',', array_keys($f)) . ') VALUES(' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
            $id = (int)$pdo->lastInsertId();
          }
          $seen[] = $id;
        }
      }
      $gone = $seen ? $pdo->query('SELECT id FROM products WHERE id NOT IN (' . implode(',', $seen) . ')')->fetchAll(PDO::FETCH_COLUMN) : [];
      if ($gone) $pdo->exec('DELETE FROM products WHERE id IN (' . implode(',', array_map('intval', $gone)) . ')');
      $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); out(['error' => 'Could not save: ' . $e->getMessage()], 500); }
    // remove deleted products from catalog collections too
    if ($gone) { $cc = jget('catalog_cols'); foreach ($cc as &$q) $q['ids'] = array_values(array_diff((array)($q['ids'] ?? []), array_map(fn($i) => "j$i", $gone))); unset($q); jset('catalog_cols', $cc); }
    audit('saved products' . ($gone ? ' (deleted ' . count($gone) . ')' : ''));
    out(['ok' => 1, 'data' => get_products()]);
  }

  if ($c === 'categories') {
    $keep = []; $pdo->beginTransaction();
    foreach ($d as $x) {
      $k = (string)($x['k'] ?? ''); if (!preg_match('/^[a-z0-9-]{2,30}$/', $k) || cs($x['name'] ?? '') === '') continue; $keep[] = $k;
      $img = img_ok((string)($x['img'] ?? '')) ? (string)$x['img'] : '';
      $v = [cs($x['name'], 80), cs($x['en'] ?? '', 80), cs($x['tag'] ?? '', 200), cs($x['tagb'] ?? '', 200), $img, (int)($x['order'] ?? 0), empty($x['home']) ? 0 : 1, empty($x['on']) ? 0 : 1];
      $id = $pdo->prepare('SELECT id FROM collections WHERE slug=?'); $id->execute([$k]); $id = (int)$id->fetchColumn();
      if ($id) $pdo->prepare('UPDATE collections SET name=?,name_en=?,tagline=?,tagline_bn=?,image=?,sort=?,show_home=?,active=? WHERE id=?')->execute([...$v, $id]);
      else $pdo->prepare('INSERT INTO collections(name,name_en,tagline,tagline_bn,image,sort,show_home,active,slug) VALUES(?,?,?,?,?,?,?,?,?)')->execute([...$v, $k]);
    }
    if ($keep) foreach ($pdo->query('SELECT id,slug FROM collections') as $r) if (!in_array($r['slug'], $keep, true)) {
      $pdo->prepare('UPDATE products SET collection_id=NULL WHERE collection_id=?')->execute([$r['id']]); $pdo->prepare('DELETE FROM collections WHERE id=?')->execute([$r['id']]);
    }
    $pdo->commit(); audit('saved categories'); out(['ok' => 1]);
  }
  if ($c === 'collections') {
    $o = []; foreach ($d as $x) if (cs($x['name'] ?? '') !== '') $o[] = ['name' => cs($x['name'], 80), 'en' => cs($x['en'] ?? '', 80), 'slug' => preg_replace('/[^a-z0-9-]/', '', (string)($x['slug'] ?? '')) ?: 'c' . count($o),
      'ids' => array_values(array_filter(array_map('strval', (array)($x['ids'] ?? [])), fn($i) => preg_match('/^j\d+$/', $i))), 'on' => empty($x['on']) ? 0 : 1];
    jset('catalog_cols', $o); audit('saved collections'); out(['ok' => 1]);
  }
  if ($c === 'reviews') {
    $o = []; foreach (array_slice($d, 0, 60) as $x) if (cs($x['name'] ?? '') !== '' && cs($x['text'] ?? '') !== '') $o[] = ['name' => cs($x['name'], 80), 'pet' => cs($x['pet'] ?? '', 80), 'text' => cs($x['text'], 600), 'stars' => max(1, min(5, (int)($x['stars'] ?? 5))), 'on' => empty($x['on']) ? 0 : 1];
    jset('reviews', $o); audit('saved reviews'); out(['ok' => 1]);
  }
  if ($c === 'banners') {
    $themes = ['purple', 'teal', 'red', 'navy', 'green', 'maroon', 'pink', 'orange', 'black', 'sand'];
    foreach ($d as $b) { $i = (int)($b['n'] ?? 0); if ($i < 1 || $i > 9) continue;
      set_setting("of{$i}_on", empty($b['on']) ? '0' : '1');
      foreach (['l1', 'l2', 'top', 'big', 'end'] as $f) set_setting("of{$i}_$f", cs($b[$f] ?? '', 30));
      set_setting("of{$i}_theme", in_array($b['theme'] ?? '', $themes, true) ? $b['theme'] : 'red');
      set_setting("of{$i}_link", preg_replace('~[^A-Za-z0-9_\-/?=&.:#%]~', '', cs($b['link'] ?? '', 200)));
      $img = (string)($b['img'] ?? ''); set_setting("of{$i}_img", img_ok($img) ? $img : jb_defaults()["of{$i}_img"]);
    }
    audit('saved banners'); out(['ok' => 1]);
  }
  if ($c === 'site') {
    foreach (SITE_MAP as $g => $m) foreach ($m as $k => $sk) if (isset($d[$g]) && array_key_exists($k, (array)$d[$g])) {
      $v = $d[$g][$k]; $v = is_bool($v) ? ($v ? '1' : '0') : cs($v, 3000);
      if (in_array($sk, IMG_KEYS, true) && $v !== '' && !img_ok($v)) continue;
      if (in_array($sk, ['delivery_in', 'delivery_out', 'free_over', 'wa_popup_sec'], true)) $v = (string)max(0, (int)$v);
      if ($sk === 'pixel_id') $v = preg_replace('/\D/', '', $v);
      set_setting($sk, $v);
    }
    audit('saved settings'); out(['ok' => 1]);
  }
}

if ($a === 'upload') {
  need('products');
  $f = $_FILES['photo'] ?? null;
  if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 12000000) out(['error' => 'Upload failed. Try another photo (max 12 MB).'], 400);
  $p = save_image($f['tmp_name']);
  if (!$p) out(['error' => 'Only JPG, PNG, WEBP or GIF images.'], 400);
  out(['ok' => 1, 'i' => $p]);
}
if ($a === 'pull') { need('products'); out(['ok' => 1, 'new' => 0, 'have' => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(), 'fail' => 0]); }

/* ---------- orders ---------- */
if ($a === 'order_add') {
  need('orders'); $b = body()['order'] ?? [];
  $phone = norm_phone((string)($b['phone'] ?? ''));
  if (cs($b['name'] ?? '') === '' || !preg_match('/^01[3-9]\d{8}$/', $phone)) out(['error' => 'Name and an 11-digit phone are needed.'], 400);
  $items = []; $sub = 0; $get = $pdo->prepare('SELECT id,name,price FROM products WHERE id=?');
  foreach ((array)($b['items'] ?? []) as $it) {
    $q = max(1, (int)($it['q'] ?? 1)); $id = (int)preg_replace('/\D/', '', (string)($it['id'] ?? '')); $get->execute([$id]); $p = $get->fetch(); if (!$p) continue;
    $pr = isset($it['p']) ? max(0, (int)$it['p']) : (int)$p['price'];
    $items[] = ['id' => $id, 'name' => $p['name'], 'size' => cs($it['size'] ?? '', 10), 'color' => cs($it['color'] ?? '', 30), 'qty' => $q, 'price' => $pr]; $sub += $q * $pr;
  }
  if (!$items) out(['error' => 'Add at least one product.'], 400);
  $ship = max(0, (int)($b['ship'] ?? 0)); $pay = in_array($b['pay'] ?? '', ['cod', 'bkash', 'nagad'], true) ? $b['pay'] : 'cod';
  $pdo->beginTransaction();
  $pdo->prepare('INSERT INTO orders(name,phone,address,area,note,items,subtotal,delivery,total,status,ip,created,updated,seen,pay,src,hist) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?)')
    ->execute([cs($b['name'], 80), $phone, cs($b['address'] ?? ''), ($b['area'] ?? '') === 'out' ? 'out' : 'in', cs($b['note'] ?? ''), json_encode($items, JSON_UNESCAPED_UNICODE), $sub, $ship, $sub + $ship, 'new', '', $now, $now, $pay, cs($b['src'] ?? 'manual', 20), json_encode([['t' => time(), 's' => 'pending', 'u' => me()['u']]])]);
  $id = (int)$pdo->lastInsertId(); $code = 'JB-' . (1000 + $id);
  $pdo->prepare('UPDATE orders SET code=? WHERE id=?')->execute([$code, $id]);
  $dec = $pdo->prepare('UPDATE products SET stock=MAX(0,stock-?) WHERE id=?'); foreach ($items as $it) $dec->execute([$it['qty'], $it['id']]);
  $pdo->commit(); audit("added order $code"); out(['ok' => 1, 'no' => $code]);
}
if ($a === 'order_update') {
  need('orders'); $b = body(); $r = find_order((string)($b['no'] ?? ''));
  if (!$r) out(['error' => 'Order not found'], 404);
  $st = $r['status']; $hist = json_decode((string)($r['hist'] ?? '[]'), true) ?: [];
  if (isset($b['status']) && isset(ST_IN[st_out((string)$b['status'])]) && st_out((string)$b['status']) !== $r['status']) {
    $st = st_out((string)$b['status']); $hist[] = ['t' => time(), 's' => $b['status'], 'u' => me()['u']];
    $bad = ['cancelled', 'returned']; $items = json_decode((string)$r['items'], true) ?: [];
    if (in_array($st, $bad, true) && !in_array($r['status'], $bad, true)) { $q = $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?'); foreach ($items as $it) $q->execute([(int)$it['qty'], (int)$it['id']]); }
    if (!in_array($st, $bad, true) && in_array($r['status'], $bad, true)) { $q = $pdo->prepare('UPDATE products SET stock=MAX(0,stock-?) WHERE id=?'); foreach ($items as $it) $q->execute([(int)$it['qty'], (int)$it['id']]); }
  }
  $pdo->prepare('UPDATE orders SET status=?, admin_note=?, paid=?, hist=?, seen=1, updated=? WHERE id=?')
    ->execute([$st, array_key_exists('admin_note', $b) ? cs($b['admin_note'], 500) : $r['admin_note'], array_key_exists('paid', $b) ? ($b['paid'] ? 1 : 0) : (int)$r['paid'], json_encode($hist), $now, $r['id']]);
  if ($st !== $r['status'] && !empty(jget('secrets')['sms_on_status'])) jb_sms($r['phone'], S('shop_name_en') . ": your order {$r['code']} is now " . strtoupper(ST_IN[$st]) . '.');
  audit("order {$r['code']} updated"); out(['ok' => 1]);
}
if ($a === 'order_del') {
  need('settings'); $r = find_order((string)(body()['no'] ?? '')); if (!$r) out(['error' => 'Order not found'], 404);
  $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$r['id']]); audit("deleted order {$r['code']}"); out(['ok' => 1]);
}

/* ---------- Steadfast courier ---------- */
function sf(string $path, ?array $post = null): array {
  $s = jget('secrets'); $base = rtrim((string)($s['sf_base'] ?? '') ?: 'https://portal.packzy.com/api/v1', '/');
  if (empty($s['sf_key']) || empty($s['sf_secret'])) return ['error' => 'Add your Steadfast API key and secret in Site Settings > Courier.'];
  $r = jb_http($base . $path, ['headers' => ['Api-Key: ' . $s['sf_key'], 'Secret-Key: ' . $s['sf_secret'], 'Content-Type: application/json'], 'post' => $post ? json_encode($post) : null, 'timeout' => 20]);
  $j = json_decode($r['body'], true);
  return is_array($j) ? $j : ['error' => 'Courier did not answer (' . ($r['code'] ?: $r['err']) . ')'];
}
if ($a === 'courier_send') {
  need('courier'); $r = find_order((string)(body()['no'] ?? '')); if (!$r) out(['error' => 'Order not found'], 404);
  if (json_decode((string)($r['courier'] ?? ''), true)) out(['error' => 'Already sent to courier.'], 400);
  $res = sf('/create_order', ['invoice' => $r['code'], 'recipient_name' => $r['name'], 'recipient_phone' => norm_phone($r['phone']), 'recipient_address' => $r['address'], 'cod_amount' => !empty($r['paid']) ? 0 : (int)$r['total'], 'note' => (string)$r['note']]);
  $c = $res['consignment'] ?? null;
  if (!$c) out(['error' => $res['error'] ?? ($res['message'] ?? 'Courier error'), 'raw' => $res], 400);
  $cj = ['by' => 'steadfast', 'cid' => (string)($c['consignment_id'] ?? ''), 'code' => (string)($c['tracking_code'] ?? ''), 'st' => (string)($c['status'] ?? 'in_review'), 't' => time()];
  $pdo->prepare("UPDATE orders SET courier=?, status=CASE WHEN status IN ('new','confirmed') THEN 'shipped' ELSE status END, updated=? WHERE id=?")->execute([json_encode($cj), $now, $r['id']]);
  audit("sent {$r['code']} to courier"); out(['ok' => 1, 'courier' => $c]);
}
if ($a === 'courier_status') {
  need('courier'); $r = find_order((string)(body()['no'] ?? '')); if (!$r) out(['error' => 'Order not found'], 404);
  $res = sf('/status_by_invoice/' . rawurlencode($r['code'])); $st = $res['delivery_status'] ?? null;
  if (!$st) out(['error' => $res['error'] ?? 'No status yet', 'raw' => $res], 400);
  $cj = json_decode((string)$r['courier'], true) ?: []; $cj['st'] = $st;
  $new = $st === 'delivered' ? 'delivered' : ($st === 'cancelled' ? 'returned' : $r['status']);
  $pdo->prepare('UPDATE orders SET courier=?, status=?, updated=? WHERE id=?')->execute([json_encode($cj), $new, $now, $r['id']]);
  out(['ok' => 1, 'status' => $st]);
}
if ($a === 'courier_balance') { need('courier'); $r = sf('/get_balance'); out(isset($r['current_balance']) ? ['ok' => 1, 'balance' => $r['current_balance']] : ['error' => $r['error'] ?? 'Could not read balance']); }

/* ---------- dashboard numbers ---------- */
if ($a === 'stats') {
  need('dashboard'); $days = [];
  foreach ($pdo->query("SELECT d, COUNT(*) n FROM visits WHERE d >= '" . date('Y-m-d', time() - 95 * 86400) . "' GROUP BY d") as $r) $days[$r['d']] = (int)$r['n'];
  $live = (int)$pdo->query('SELECT COUNT(*) FROM live WHERE t > ' . (time() - 300))->fetchColumn();
  $capi = array_filter(jget('capi_log'), fn($e) => $e['t'] > time() - 30 * 86400);
  out(['days' => $days, 'live' => $live, 'capi' => ['events' => count($capi), 'ok' => count(array_filter($capi, fn($e) => $e['code'] === 200))], 'audit' => array_slice(array_reverse(jget('admin_log')), 0, 30)]);
}

/* ---------- customer accounts (from the shop's login) ---------- */
if ($a === 'accounts') { need('customers'); out(['data' => $pdo->query('SELECT id,name,phone,blocked,created,last_login FROM customers ORDER BY id DESC')->fetchAll()]); }
if ($a === 'account_op') {
  need('customers'); $b = body(); $id = (int)($b['id'] ?? 0); $op = (string)($b['op'] ?? '');
  if ($op === 'block') $pdo->prepare('UPDATE customers SET blocked=1-blocked WHERE id=?')->execute([$id]);
  elseif ($op === 'pw') { $np = (string)($b['pass'] ?? ''); if (mb_strlen($np) < 6) out(['error' => 'Password must be 6+ characters.'], 400); $pdo->prepare('UPDATE customers SET pass=? WHERE id=?')->execute([password_hash($np, PASSWORD_DEFAULT), $id]); }
  elseif ($op === 'delete') { need('settings'); $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$id]); }
  audit("customer account $id: $op"); out(['ok' => 1]);
}

/* ---------- keys (never sent back in full) ---------- */
const SECRET_KEYS = ['capi_token', 'capi_test', 'sf_key', 'sf_secret', 'sf_base', 'sms_url', 'sms_admin', 'sms_on_order', 'sms_on_status', 'gemini_key'];
const SECRET_HIDDEN = ['capi_token', 'sf_secret', 'gemini_key', 'sms_url'];
if ($a === 'secrets_get') {
  need('settings'); $s = jget('secrets'); $s['gemini_key'] = S('gemini_key'); $o = [];
  foreach (SECRET_KEYS as $k) $o[$k] = in_array($k, SECRET_HIDDEN, true) ? (empty($s[$k]) ? '' : '__set__') : ($s[$k] ?? '');
  out(['data' => $o]);
}
if ($a === 'secrets_put') {
  need('settings'); if (me()['role'] !== 'super') out(['error' => 'Only the super admin can change keys.'], 403);
  $in = body()['data'] ?? []; $s = jget('secrets');
  foreach (SECRET_KEYS as $k) if (array_key_exists($k, $in)) { $v = is_bool($in[$k]) ? ($in[$k] ? 1 : 0) : cs($in[$k], 600); if ($v === '__set__') continue; if (in_array($k, SECRET_HIDDEN, true) && $v === '') continue; $s[$k] = $v; }
  if (isset($s['gemini_key'])) { set_setting('gemini_key', (string)$s['gemini_key']); unset($s['gemini_key']); }
  jset('secrets', $s); audit('changed keys'); out(['ok' => 1]);
}
if ($a === 'sms_test') { need('settings'); $n = norm_phone((string)(body()['to'] ?? '')); if (!preg_match('/^01[3-9]\d{8}$/', $n)) out(['error' => 'Give an 11-digit number'], 400); out(jb_sms($n, S('shop_name_en') . ' test SMS. Your SMS settings work.')); }

/* ---------- admins & roles ---------- */
if ($a === 'users') { need('users'); out(['data' => array_map(fn($u) => ['u' => $u['u'], 'name' => $u['name'], 'role' => $u['role'], 'on' => $u['on'] ?? 1, 'last' => $u['last'] ?? null], $users)]); }
if ($a === 'user_save') {
  need('users'); $b = body(); $un = strtolower(cs($b['u'] ?? '', 30)); $role = (string)($b['role'] ?? 'staff'); $pw = (string)($b['pass'] ?? '');
  if (!preg_match('/^[a-z0-9_.]{3,30}$/', $un) || !isset(ROLES[$role])) out(['error' => 'Username 3+ letters (a-z, 0-9) and a role.'], 400);
  $exists = false; foreach ($users as $u) if ($u['u'] === $un) $exists = true;
  if (!$exists && strlen($pw) < 6) out(['error' => 'New admin needs a 6+ character password.'], 400);
  if ($pw !== '' && strlen($pw) < 6) out(['error' => 'Password must be 6+ characters.'], 400);
  if (!$exists) $users[] = ['u' => $un, 'name' => '', 'role' => $role, 'hash' => '', 'on' => 1];
  foreach ($users as &$u) if ($u['u'] === $un) { $u['name'] = cs($b['name'] ?? $un, 60); if ($u['u'] !== me()['u']) { $u['role'] = $role; $u['on'] = empty($b['on']) ? 0 : 1; } if ($pw !== '') $u['hash'] = password_hash($pw, PASSWORD_DEFAULT); }
  unset($u); jset('admin_users', $users); audit("saved admin $un"); out(['ok' => 1]);
}
if ($a === 'user_del') { need('users'); $un = (string)(body()['u'] ?? ''); if ($un === me()['u']) out(['error' => 'You cannot delete yourself.'], 400); jset('admin_users', array_values(array_filter($users, fn($u) => $u['u'] !== $un))); audit("deleted admin $un"); out(['ok' => 1]); }
if ($a === 'pass') {
  $b = body(); $old = (string)($b['old'] ?? ''); $new = (string)($b['new'] ?? ''); $mine = null;
  foreach ($users as $u) if ($u['u'] === me()['u']) $mine = $u;
  if (!$mine || !password_verify($old, $mine['hash'])) out(['error' => 'Current password is wrong.'], 403);
  if (strlen($new) < 6) out(['error' => 'New password must be 6+ characters.'], 400);
  foreach ($users as &$u) if ($u['u'] === me()['u']) $u['hash'] = password_hash($new, PASSWORD_DEFAULT); unset($u);
  jset('admin_users', $users); audit('changed own password'); out(['ok' => 1]);
}

/* ---------- AI: product text (Gemini if a key is set, else the built-in shoe writer) ---------- */
if ($a === 'caption') {
  need('products'); $b = body(); $cols = array_column(collections(false), null, 'slug'); $c = $cols[(string)($b['k'] ?? '')] ?? null;
  $in = ['name' => cs($b['n'] ?? '', 160), 'collection' => $c['name'] ?? '', 'collection_slug' => $c['slug'] ?? '', 'brand' => cs($b['b'] ?? '', 60), 'price' => (int)($b['p'] ?? 0), 'old_price' => (int)($b['o'] ?? 0),
    'sizes' => cs($b['sz'] ?? '', 200), 'colors' => cs($b['cl'] ?? '', 200), 'gender' => (string)($b['gx'] ?? ''), 'notes' => cs($b['notes'] ?? '', 300)];
  $tone = in_array($b['tone'] ?? '', ['premium', 'offer', 'festive', 'casual', 'simple'], true) ? $b['tone'] : 'premium';
  $b64 = ''; $mime = 'image/jpeg'; $img = (string)($b['img'] ?? '');
  if ($img !== '' && img_ok($img) && !preg_match('~^https?://~', $img)) { $fp = realpath(JB_ROOT . '/' . $img); if ($fp && strpos($fp, realpath(JB_ROOT)) === 0 && is_file($fp) && filesize($fp) < 4000000) { $b64 = base64_encode((string)file_get_contents($fp)); $mime = (string)(@getimagesize($fp)['mime'] ?? 'image/jpeg'); } }
  $err = ''; $o = caption_gemini($in, $b64, $mime, $err, $tone);
  if ($o) $o += array_diff_key(caption_template($in, null, $tone), $o); else $o = caption_template($in, null, $tone) + ['source' => 'template'];
  out(['ok' => 1] + $o);
}
if ($a === 'ai') {
  need('products'); $key = S('gemini_key');
  if ($key === '') out(['error' => 'nokey']);
  $prompt = cs(body()['prompt'] ?? '', 2000);
  foreach (['gemini-flash-latest', 'gemini-2.5-flash'] as $model) {
    $r = jb_http("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent", ['post' => json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]), 'headers' => ['Content-Type: application/json', 'x-goog-api-key: ' . $key], 'timeout' => 30]);
    $t = json_decode($r['body'], true)['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($t) out(['ok' => 1, 'text' => $t]);
  }
  out(['error' => 'AI did not answer. Check the Gemini key.']);
}

/* ---------- downloads ---------- */
if ($a === 'export') {
  need('reports'); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="jutarbazar-orders-' . date('Ymd') . '.csv"');
  $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF");
  fputcsv($f, ['Order', 'Date', 'Name', 'Phone', 'Address', 'Area', 'Items', 'Subtotal', 'Delivery', 'Total', 'Payment', 'Status', 'Note', 'Admin note']);
  foreach ($pdo->query('SELECT * FROM orders ORDER BY id DESC') as $r) { $o = order_json($r);
    fputcsv($f, [$o['no'], date('Y-m-d H:i', $o['t']), $o['name'], $o['phone'], $o['address'], $o['area'] === 'out' ? 'Outside Dhaka' : 'Inside Dhaka', implode('; ', array_map(fn($i) => $i['n'] . ($i['v'] ? " ({$i['v']})" : '') . ' x' . $i['q'], $o['items'])), $o['sub'], $o['ship'], $o['total'], $o['pay'], $o['status'], $o['note'], $o['admin_note']]); }
  exit;
}
if ($a === 'backup') {
  need('settings'); if (!class_exists('ZipArchive')) out(['error' => 'ZipArchive is missing on this server.'], 500);
  @set_time_limit(300); $tmp = tempnam(sys_get_temp_dir(), 'jb'); $snap = jb_data_dir() . '/backup-snap.sqlite';
  @unlink($snap); $pdo->exec('VACUUM INTO ' . $pdo->quote($snap));
  $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE); $z->addFile($snap, 'shop.sqlite');
  if (is_dir(JB_ROOT . '/uploads')) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(JB_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile()) $z->addFile($f->getPathname(), 'uploads/' . substr($f->getPathname(), strlen(JB_ROOT . '/uploads/')));
  $z->close(); @unlink($snap);
  header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="jutarbazar-backup-' . date('Y-m-d') . '.zip"'); header('Content-Length: ' . filesize($tmp));
  readfile($tmp); @unlink($tmp); exit;
}
out(['error' => 'Unknown action'], 404);
