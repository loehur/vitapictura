<?php
/**
 * Copies legacy catalog covers to the Vita Pictura public directory and updates
 * the migrated products. Database credentials are supplied only through ENV.
 */
function connectDb(string $host, string $user, string $pass, string $name): mysqli {
    $db = new mysqli($host, $user, $pass, $name);
    if ($db->connect_error) throw new RuntimeException($db->connect_error);
    $db->set_charset('utf8mb4');
    return $db;
}
function normalizeImageName(string $value): string {
    return strtolower((string) preg_replace('/[^a-z0-9]/i', '', pathinfo(trim($value), PATHINFO_FILENAME));
}

$legacyDir = getenv('LEGACY_IMAGE_DIR') ?: '/www/wwwroot/abflab/assets/img/home_produk';
$publicDir = getenv('TARGET_IMAGE_DIR') ?: '/www/wwwroot/vitapictura/public/store/uploads/products';
$publicBaseUrl = rtrim(getenv('TARGET_IMAGE_URL') ?: 'https://vpictura.com/uploads/products', '/');
if (!is_dir($legacyDir)) throw new RuntimeException("Legacy image directory is missing: {$legacyDir}");
if (!is_dir($publicDir) && !mkdir($publicDir, 0755, true) && !is_dir($publicDir)) throw new RuntimeException("Cannot create: {$publicDir}");

$source = connectDb(getenv('OLD_DB_HOST') ?: 'localhost', (string) getenv('OLD_DB_USER'), (string) getenv('OLD_DB_PASS'), getenv('OLD_DB_NAME') ?: 'vita');
$target = connectDb(getenv('NEW_DB_HOST') ?: 'localhost', (string) getenv('NEW_DB_USER'), (string) getenv('NEW_DB_PASS'), getenv('NEW_DB_NAME') ?: 'vitapictura');
$files = [];
foreach (glob($legacyDir . '/*') ?: [] as $file) {
    if (is_file($file)) $files[normalizeImageName(basename($file))] = $file;
}

$products = $source->query('SELECT produk_id, produk, img FROM produk WHERE img IS NOT NULL AND TRIM(img) <> "" ORDER BY produk_id');
$findTarget = $target->prepare('SELECT id, name FROM vp_products WHERE legacy_product_id = ? LIMIT 1');
$updateCover = $target->prepare('UPDATE vp_products SET cover_image_url = ?, updated_at = NOW() WHERE id = ?');
$findMedia = $target->prepare('SELECT id FROM vp_product_media WHERE product_id = ? AND url = ? LIMIT 1');
$insertMedia = $target->prepare('INSERT INTO vp_product_media(product_id, url, alt_text, sort_order, created_at) VALUES(?, ?, ?, 0, NOW())');
$copied = 0; $updated = 0; $missing = [];

while ($legacy = $products->fetch_assoc()) {
    $legacyId = (int) $legacy['produk_id'];
    $key = normalizeImageName((string) $legacy['img']);
    if (!isset($files[$key])) { $missing[] = "{$legacyId}: {$legacy['img']}"; continue; }
    $findTarget->bind_param('i', $legacyId); $findTarget->execute();
    $targetProduct = $findTarget->get_result()->fetch_assoc();
    if (!$targetProduct) { $missing[] = "{$legacyId}: target product missing"; continue; }
    $extension = strtolower(pathinfo($files[$key], PATHINFO_EXTENSION)) ?: 'webp';
    $filename = "product-{$legacyId}.{$extension}";
    $destination = $publicDir . DIRECTORY_SEPARATOR . $filename;
    if (!copy($files[$key], $destination)) throw new RuntimeException("Cannot copy {$files[$key]}");
    @chmod($destination, 0644); $copied++;
    $url = $publicBaseUrl . '/' . rawurlencode($filename); $productId = (int) $targetProduct['id'];
    $updateCover->bind_param('si', $url, $productId); $updateCover->execute(); $updated++;
    $findMedia->bind_param('is', $productId, $url); $findMedia->execute();
    if (!$findMedia->get_result()->fetch_assoc()) { $alt = (string) $targetProduct['name']; $insertMedia->bind_param('iss', $productId, $url, $alt); $insertMedia->execute(); }
}
echo "Images copied={$copied}; products updated={$updated}; missing=" . count($missing) . PHP_EOL;
if ($missing) echo "Missing: " . implode('; ', $missing) . PHP_EOL;
