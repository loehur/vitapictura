<?php
/**
 * Migrate legacy ABFLab product detail into Vita Pictura.
 *
 * Reads the legacy `vita` database and the legacy asset folders, then rebuilds
 * (per product): nested option groups/values, gallery media, description tabs,
 * file-delivery flag, and template (mal) list. Safe to re-run: the option
 * groups, media, and tabs of each product are deleted and recreated.
 *
 * It only WRITES to the Vita Pictura database and the Vita Pictura media folder.
 *
 * Usage:
 *   php scripts/migrate_legacy_detail.php
 *
 * Environment overrides:
 *   OLD_DB_HOST (default Env::DB_HOST)   OLD_DB_USER (default vita)
 *   OLD_DB_PASS (default empty)          OLD_DB_NAME (default vita)
 *   OLD_ASSETS  (default /www/wwwroot/abflab/assets)
 *   OLD_VIEWS   (default $OLD_ASSETS/../app/Views/Load/Produk_Deskripsi)
 *   VP_MEDIA    (default <repo>/public/store/uploads)
 *   VP_MEDIA_URL (default /uploads)
 */

function mld_env(string $key, ?string $default = null): ?string { $value = getenv($key); return ($value === false || $value === '') ? $default : $value; }
function mld_db(string $host, string $user, string $pass, string $name): mysqli { $db = new mysqli($host, $user, $pass, $name); if ($db->connect_error) throw new RuntimeException("DB {$name}: " . $db->connect_error); $db->set_charset('utf8mb4'); return $db; }
function mld_has_column(mysqli $db, string $table, string $column): bool { $t = $db->real_escape_string($table); $c = $db->real_escape_string($column); $r = $db->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}'"); return $r && (int) $r->fetch_assoc()['c'] > 0; }
function mld_has_table(mysqli $db, string $table): bool { $t = $db->real_escape_string($table); $r = $db->query("SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'"); return $r && (int) $r->fetch_assoc()['c'] > 0; }
function mld_suffix(string $key): ?string { if ($key === 'm') return null; $s = preg_replace('/^m_?/', '', $key); return ($s === '' || $s === false) ? null : $s; }
function mld_suffix_from_img($img): ?string { $img = trim((string) $img); if ($img === '' || $img === '0') return null; return $img; }

function mld_ensure_schema(mysqli $db): void {
    if (!mld_has_column($db, 'vp_products', 'legacy_img_detail')) $db->query("ALTER TABLE vp_products ADD COLUMN legacy_img_detail VARCHAR(120) NULL AFTER legacy_product_id");
    if (!mld_has_column($db, 'vp_products', 'perlu_file')) $db->query("ALTER TABLE vp_products ADD COLUMN perlu_file TINYINT(1) NOT NULL DEFAULT 0 AFTER short_description");
    if (!mld_has_column($db, 'vp_products', 'mal_json')) $db->query("ALTER TABLE vp_products ADD COLUMN mal_json JSON NULL AFTER perlu_file");
    if (!mld_has_column($db, 'vp_product_option_groups', 'legacy_vg_id')) $db->query("ALTER TABLE vp_product_option_groups ADD COLUMN legacy_vg_id BIGINT UNSIGNED NULL AFTER product_id");
    if (!mld_has_column($db, 'vp_product_option_groups', 'group_level')) $db->query("ALTER TABLE vp_product_option_groups ADD COLUMN group_level TINYINT NOT NULL DEFAULT 1 AFTER name");
    if (!mld_has_column($db, 'vp_product_option_groups', 'parent_group_id')) {
        $db->query("ALTER TABLE vp_product_option_groups ADD COLUMN parent_group_id BIGINT UNSIGNED NULL AFTER group_level");
        $db->query("ALTER TABLE vp_product_option_groups ADD KEY vp_option_groups_legacy_index (product_id, legacy_vg_id)");
        $db->query("ALTER TABLE vp_product_option_groups ADD CONSTRAINT vp_option_groups_parent_fk FOREIGN KEY (parent_group_id) REFERENCES vp_product_option_groups (id) ON DELETE CASCADE");
    }
    if (!mld_has_column($db, 'vp_product_option_values', 'legacy_varian_id')) $db->query("ALTER TABLE vp_product_option_values ADD COLUMN legacy_varian_id BIGINT UNSIGNED NULL AFTER option_group_id");
    if (!mld_has_column($db, 'vp_product_option_values', 'image_suffix')) $db->query("ALTER TABLE vp_product_option_values ADD COLUMN image_suffix VARCHAR(80) NULL AFTER name");
    if (!mld_has_column($db, 'vp_product_option_values', 'parent_value_id')) {
        $db->query("ALTER TABLE vp_product_option_values ADD COLUMN parent_value_id BIGINT UNSIGNED NULL AFTER image_suffix");
        $db->query("ALTER TABLE vp_product_option_values ADD KEY vp_option_values_legacy_index (legacy_varian_id)");
        $db->query("ALTER TABLE vp_product_option_values ADD CONSTRAINT vp_option_values_parent_fk FOREIGN KEY (parent_value_id) REFERENCES vp_product_option_values (id) ON DELETE CASCADE");
    }
    if (!mld_has_column($db, 'vp_product_media', 'image_key')) {
        $db->query("ALTER TABLE vp_product_media ADD COLUMN image_key VARCHAR(120) NULL AFTER url");
        $db->query("ALTER TABLE vp_product_media ADD COLUMN image_suffix VARCHAR(80) NULL AFTER image_key");
        $db->query("ALTER TABLE vp_product_media ADD KEY vp_product_media_key_index (product_id, image_key)");
    }
    if (!mld_has_table($db, 'vp_product_detail_tabs')) {
        $db->query("CREATE TABLE vp_product_detail_tabs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, product_id BIGINT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL, content_key VARCHAR(120) NULL, content_html MEDIUMTEXT NULL, sort_order INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY vp_product_detail_tabs_listing (product_id, sort_order), CONSTRAINT vp_product_detail_tabs_product_fk FOREIGN KEY (product_id) REFERENCES vp_products (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

$root = dirname(__DIR__);
$envFile = $root . '/api/app/Config/Env.php';
if (!file_exists($envFile)) $envFile = $root . '/api/app/Config/Env.example.php';
require $envFile;
$newConfig = Env::DB_CREDENTIALS[Env::MODE][0];

$oldHost = mld_env('OLD_DB_HOST', Env::DB_HOST);
$oldUser = mld_env('OLD_DB_USER', 'vita');
$oldPass = mld_env('OLD_DB_PASS', '');
$oldName = mld_env('OLD_DB_NAME', 'vita');
$oldAssets = rtrim((string) mld_env('OLD_ASSETS', '/www/wwwroot/abflab/assets'), '/\\');
$oldViews = rtrim((string) mld_env('OLD_VIEWS', $oldAssets . '/../app/Views/Load/Produk_Deskripsi'), '/\\');
$newMedia = rtrim((string) mld_env('VP_MEDIA', $root . '/public/store/uploads'), '/\\');
$mediaUrl = rtrim((string) mld_env('VP_MEDIA_URL', '/uploads'), '/');

echo "Legacy DB : {$oldUser}@{$oldHost}/{$oldName}\n";
echo "Target DB : {$newConfig['user']}@" . Env::DB_HOST . "/{$newConfig['db']}\n";
echo "Legacy assets: {$oldAssets}\n";
echo "Target media : {$newMedia}\n\n";

$old = mld_db($oldHost, $oldUser, $oldPass, $oldName);
$new = mld_db(Env::DB_HOST, $newConfig['user'], $newConfig['pass'], $newConfig['db']);
mld_ensure_schema($new);

function mld_tab_content(string $views, string $key): ?string { if ($key === '') return null; $file = $views . '/' . $key . '.php'; return is_file($file) ? file_get_contents($file) : null; }
function mld_legacy_cover(int $legacy): ?string { return in_array($legacy, [1, 2, 3, 4, 5, 6, 7, 8, 9, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24], true) ? '/uploads/products/product-' . $legacy . '.webp' : null; }

$now = date('Y-m-d H:i:s');
$stats = ['products' => 0, 'media' => 0, 'mal' => 0, 'tabs' => 0, 'groups' => 0, 'values' => 0];

$products = $new->query("SELECT id, legacy_product_id FROM vp_products WHERE legacy_product_id IS NOT NULL ORDER BY id");
while ($product = $products->fetch_assoc()) {
    $pid = (int) $product['id'];
    $legacy = (int) $product['legacy_product_id'];

    $src = $old->query("SELECT * FROM produk WHERE produk_id = {$legacy} LIMIT 1")->fetch_assoc();
    if (!$src) { echo "skip legacy {$legacy}: not found\n"; continue; }

    $imgDetail = (string) $src['img_detail'];
    $perluFile = (int) $src['perlu_file'];

    $new->query("DELETE FROM vp_product_detail_tabs WHERE product_id = {$pid}");

    $tabs = [];
    $detailRaw = (string) $src['detail'];
    if ($detailRaw !== '') { $decoded = @unserialize($detailRaw); if (is_array($decoded)) $tabs = $decoded; }
    $tabOrder = 0;
    $firstText = null;
    $insertTab = $new->prepare("INSERT INTO vp_product_detail_tabs (product_id, title, content_key, content_html, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($tabs as $tab) {
        $title = (string) ($tab['judul'] ?? '');
        $key = (string) ($tab['konten'] ?? '');
        if ($title === '' && $key === '') continue;
        $html = mld_tab_content($oldViews, $key);
        if ($firstText === null && $html) $firstText = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        $k = $key; $h = $html; $t = $title; $o = $tabOrder; $n = $now;
        $insertTab->bind_param('isssis', $pid, $t, $k, $h, $o, $n);
        $insertTab->execute();
        $tabOrder++; $stats['tabs']++;
    }

    $malOut = [];
    $malRaw = (string) $src['mal'];
    if ($malRaw !== '') {
        $malFiles = @unserialize($malRaw);
        if (is_array($malFiles) && $malFiles) {
            $malDir = $newMedia . '/mal';
            if (!is_dir($malDir)) @mkdir($malDir, 0755, true);
            foreach ($malFiles as $file) {
                $from = $oldAssets . '/img/mal/' . $file;
                $actual = $file;
                if (!is_file($from)) {
                    $base = pathinfo($file, PATHINFO_FILENAME);
                    $candidates = glob($oldAssets . '/img/mal/' . $base . '.*') ?: [];
                    if ($candidates) { $actual = basename($candidates[0]); $from = $candidates[0]; }
                }
                if (is_file($from)) @copy($from, $malDir . '/' . $actual);
                $malOut[] = ['name' => $actual, 'url' => $mediaUrl . '/mal/' . rawurlencode($actual)];
                $stats['mal']++;
            }
        }
    }
    $malJson = $malOut ? json_encode($malOut, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    $new->query("DELETE FROM vp_product_media WHERE product_id = {$pid}");
    $galleryDir = $oldAssets . '/img/produk_detail/' . $imgDetail;
    $order = 0;
    if (is_dir($galleryDir)) {
        $destDir = $newMedia . '/produk_detail/' . $imgDetail;
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        $files = array_values(array_filter(scandir($galleryDir), fn($f) => (bool) preg_match('/\.webp$/i', $f)));
        usort($files, function ($a, $b) { if ($a === 'm.webp') return -1; if ($b === 'm.webp') return 1; return strnatcasecmp($a, $b); });
        $insertMedia = $new->prepare("INSERT INTO vp_product_media (product_id, url, image_key, image_suffix, alt_text, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($files as $file) {
            @copy($galleryDir . '/' . $file, $destDir . '/' . $file);
            $key = pathinfo($file, PATHINFO_FILENAME);
            $url = $mediaUrl . '/produk_detail/' . $imgDetail . '/' . $file;
            $suffix = mld_suffix($key);
            $alt = (string) $src['produk'];
            $insertMedia->bind_param('issssis', $pid, $url, $key, $suffix, $alt, $order, $now);
            $insertMedia->execute();
            $order++; $stats['media']++;
        }
    }

    $new->query("DELETE FROM vp_product_option_groups WHERE product_id = {$pid}");

    $group1 = [];
    $q = $old->query("SELECT * FROM varian_grup_1 WHERE produk_id = {$legacy} ORDER BY vg1_id");
    $insertGroup = $new->prepare("INSERT INTO vp_product_option_groups (product_id, legacy_vg_id, name, group_level, parent_group_id, is_required, sort_order, created_at, updated_at) VALUES (?, ?, ?, 1, NULL, 1, ?, ?, ?)");
    while ($g = $q->fetch_assoc()) {
        $vg1 = (int) $g['vg1_id'];
        $name = (string) $g['vg'];
        $sort = $vg1;
        $insertGroup->bind_param('iisiss', $pid, $vg1, $name, $sort, $now, $now);
        $insertGroup->execute();
        $group1[$vg1] = $new->insert_id;
        $stats['groups']++;
    }

    $value1 = [];
    $insertValue = $new->prepare("INSERT INTO vp_product_option_values (option_group_id, legacy_varian_id, name, image_suffix, parent_value_id, price_delta, weight_delta_grams, length_delta_mm, width_delta_mm, height_delta_mm, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
    $q = $old->query("SELECT v.* FROM varian_1 v INNER JOIN varian_grup_1 g ON g.vg1_id = v.vg1_id WHERE g.produk_id = {$legacy} ORDER BY v.varian_id");
    while ($v = $q->fetch_assoc()) {
        $vg1 = (int) $v['vg1_id'];
        if (!isset($group1[$vg1])) continue;
        $gid = $group1[$vg1];
        $vid = (int) $v['varian_id'];
        $name = (string) $v['varian'];
        $suffix = mld_suffix_from_img($v['img'] ?? '');
        $parent = null;
        $price = (float) $v['harga'];
        $weight = (int) $v['berat'];
        $l = (int) $v['p'] * 10; $w = (int) $v['l'] * 10; $h = (int) $v['t'] * 10;
        $sort = $vid;
        $insertValue->bind_param('iissidiiiiiss', $gid, $vid, $name, $suffix, $parent, $price, $weight, $l, $w, $h, $sort, $now, $now);
        $insertValue->execute();
        $value1[$vid] = $new->insert_id;
        $stats['values']++;
    }

    $group2 = [];
    $q = $old->query("SELECT * FROM varian_grup_2 ORDER BY vg2_id");
    while ($g = $q->fetch_assoc()) {
        $vg1 = (int) $g['vg1_id'];
        if (!isset($group1[$vg1])) continue;
        $vg2 = (int) $g['vg2_id'];
        $name = (string) $g['vg'];
        $parentGroup = $group1[$vg1];
        $sort = $vg2;
        $stmt = $new->prepare("INSERT INTO vp_product_option_groups (product_id, legacy_vg_id, name, group_level, parent_group_id, is_required, sort_order, created_at, updated_at) VALUES (?, ?, ?, 2, ?, 0, ?, ?, ?)");
        $stmt->bind_param('iisiiss', $pid, $vg2, $name, $parentGroup, $sort, $now, $now);
        $stmt->execute();
        $group2[$vg2] = $new->insert_id;
        $stats['groups']++;
    }

    $q = $old->query("SELECT v.*, h.v2_head FROM varian_2 v LEFT JOIN v2_head h ON h.v2_head_id = v.v2_head_id ORDER BY v.varian_id");
    while ($v = $q->fetch_assoc()) {
        $vg2 = (int) $v['vg2_id'];
        if (!isset($group2[$vg2])) continue;
        $gid = $group2[$vg2];
        $vid = (int) $v['varian_id'];
        $name = $v['v2_head'] !== null && $v['v2_head'] !== '' ? (string) $v['v2_head'] : ('Option ' . $vid);
        $suffix = mld_suffix_from_img($v['img'] ?? '');
        $parent = $value1[(int) $v['v1_id']] ?? null;
        $price = (float) $v['harga'];
        $weight = (int) $v['berat'];
        $l = (int) $v['p'] * 10; $w = (int) $v['l'] * 10; $h = (int) $v['t'] * 10;
        $sort = $vid;
        $insertValue->bind_param('iissidiiiiiss', $gid, $vid, $name, $suffix, $parent, $price, $weight, $l, $w, $h, $sort, $now, $now);
        $insertValue->execute();
        $stats['values']++;
    }

    $summary = $firstText ?: null;
    $update = $new->prepare("UPDATE vp_products SET legacy_img_detail = ?, perlu_file = ?, mal_json = ?, description = ?, short_description = ?, updated_at = ? WHERE id = ?");
    $update->bind_param('sissssi', $imgDetail, $perluFile, $malJson, $summary, $summary, $now, $pid);
    $update->execute();

    $stats['products']++;
    echo "product {$pid} (legacy {$legacy}) '{$src['produk']}': media={$order} tabs={$tabOrder} mal=" . count($malOut) . " groups=" . (count($group1) + count($group2)) . "\n";
}

echo "\nDone. " . json_encode($stats) . "\n";
