<?php
namespace App\Services;
use App\Core\DB;

/**
 * Utilitas Event: limit (default global + override per user), slug, path storage,
 * dan pemrosesan gambar (resize <=640px + watermark publik + simpan standard bersih).
 */
class Events
{
    public const DEFAULT_MAX_EVENTS = 3;
    public const DEFAULT_MAX_PHOTOS = 200;
    public const MAX_DIMENSION = 640;
    public const MAX_BYTES = 204800; // 200 KB
    public const COVER_MAX_DIMENSION = 1280;
    public const COVER_MAX_BYTES = 204800; // 200 KB

    /** Ekstensi gambar yang diizinkan (berdasarkan MIME). */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
    ];

    /** @return array{maxEvents:int,maxPhotos:int} */
    public static function limits(DB $db, int $customerId): array
    {
        $maxEvents = self::DEFAULT_MAX_EVENTS;
        $maxPhotos = self::DEFAULT_MAX_PHOTOS;

        $rows = $db->query('SELECT name,value FROM vp_settings WHERE name IN (?,?)', ['event_max_events', 'event_max_photos_per_event'])->result_array() ?: [];
        foreach ($rows as $r) {
            if ($r['name'] === 'event_max_events' && (int) $r['value'] > 0) {
                $maxEvents = (int) $r['value'];
            }
            if ($r['name'] === 'event_max_photos_per_event' && (int) $r['value'] > 0) {
                $maxPhotos = (int) $r['value'];
            }
        }

        $prof = $db->query('SELECT max_events,max_photos_per_event FROM vp_event_profiles WHERE customer_id=? LIMIT 1', [$customerId])->row_array();
        if ($prof) {
            if ($prof['max_events'] !== null && (int) $prof['max_events'] > 0) {
                $maxEvents = (int) $prof['max_events'];
            }
            if ($prof['max_photos_per_event'] !== null && (int) $prof['max_photos_per_event'] > 0) {
                $maxPhotos = (int) $prof['max_photos_per_event'];
            }
        }

        return ['maxEvents' => $maxEvents, 'maxPhotos' => $maxPhotos];
    }

    public static function ensureProfile(DB $db, int $customerId): void
    {
        $exists = $db->query('SELECT customer_id FROM vp_event_profiles WHERE customer_id=? LIMIT 1', [$customerId])->row_array();
        if (!$exists) {
            $now = date('Y-m-d H:i:s');
            $approved = self::whitelistMode($db) === 'whitelist' ? 0 : 1;
            $db->insert('vp_event_profiles', ['customer_id' => $customerId, 'balance' => 0, 'status' => 'active', 'approved' => $approved, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** Dipaksa whitelist: hanya user yang disetujui admin boleh membuat event. */
    public static function whitelistMode(DB $db): string
    {
        return 'whitelist';
    }

    /** @return array{ok:bool,reason:string,approved:bool} */
    public static function canCreate(DB $db, int $customerId): array
    {
        $prof = $db->query('SELECT status,approved FROM vp_event_profiles WHERE customer_id=? LIMIT 1', [$customerId])->row_array();
        if ($prof && $prof['status'] === 'blocked') {
            return ['ok' => false, 'reason' => 'Akun event diblokir', 'approved' => false];
        }
        $approved = $prof ? ((int) $prof['approved'] === 1) : false;
        if (self::whitelistMode($db) === 'whitelist' && !$approved) {
            return ['ok' => false, 'reason' => 'Akun event menunggu persetujuan admin', 'approved' => false];
        }
        return ['ok' => true, 'reason' => '', 'approved' => $approved];
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $s), '-'));
        return $s === '' ? 'event' : substr($s, 0, 200);
    }

    public static function publicRoot(): string
    {
        $p = defined('Env::MEDIA_STORAGE_PATH') ? trim((string) \Env::MEDIA_STORAGE_PATH) : '';
        return $p !== '' ? rtrim($p, '/\\') : dirname(__DIR__, 3) . '/public/uploads';
    }

    public static function privateRoot(): string
    {
        $p = defined('Env::EVENT_PRIVATE_STORAGE_PATH') ? trim((string) \Env::EVENT_PRIVATE_STORAGE_PATH) : '';
        return $p !== '' ? rtrim($p, '/\\') : dirname(__DIR__, 3) . '/storage/private';
    }

    public static function previewUrl(string $key): string
    {
        return rtrim((string) (\Env::MEDIA_BASE_URL ?? ''), '/') . '/uploads/' . ltrim($key, '/');
    }

    private static function writeFile(string $path, string $bytes): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new \RuntimeException('Gagal membuat folder penyimpanan');
        }
        if (file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException('Gagal menyimpan file');
        }
    }

    private static function encode($img, string $mime, int $quality): string
    {
        ob_start();
        switch ($mime) {
            case 'image/png':
                imagepng($img, null, 6);
                break;
            case 'image/webp':
                imagewebp($img, null, $quality);
                break;
            case 'image/gif':
                imagegif($img);
                break;
            case 'image/bmp':
                imagebmp($img);
                break;
            default:
                imagejpeg($img, null, $quality);
        }
        return (string) ob_get_clean();
    }

    /** Tempel watermark "Vita Pictura" berpola miring berulang. */
    private static function watermark($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $tileW = 220;
        $tileH = 90;

        // Tile kecil berisi teks, lalu dirotasi agar miring.
        $tile = imagecreatetruecolor($tileW, $tileH);
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        $transparent = imagecolorallocatealpha($tile, 0, 0, 0, 127);
        imagefilledrectangle($tile, 0, 0, $tileW, $tileH, $transparent);
        imagealphablending($tile, true);
        $textColor = imagecolorallocatealpha($tile, 90, 60, 150, 70);
        imagestring($tile, 5, 6, (int) (($tileH - imagefontheight(5)) / 2), 'Vita Pictura', $textColor);
        $tile = imagerotate($tile, -28, $transparent);
        imagealphablending($tile, true);
        imagesavealpha($tile, true);

        $tw = imagesx($tile);
        $th = imagesy($tile);
        for ($y = -$th; $y < $h + $th; $y += $th) {
            for ($x = -$tw; $x < $w + $tw; $x += $tw) {
                imagecopy($img, $tile, $x, $y, 0, 0, $tw, $th);
            }
        }
        imagedestroy($tile);
        return $img;
    }

    /**
     * Proses foto yang diunggah: resize <=640px, simpan standard (bersih, privat)
     * dan preview (watermark, publik) dengan format SUMBER.
     * @return array{standardKey:string,previewKey:string,mime:string,width:int,height:int}
     */
    public static function processPhoto(string $tmpPath, string $mime, int $eventId): array
    {
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Format gambar tidak didukung (JPG/PNG/WEBP/GIF/BMP)');
        }
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Ekstensi GD tidak tersedia di server');
        }

        $data = @file_get_contents($tmpPath);
        if ($data === false || $data === '') {
            throw new \RuntimeException('Gagal membaca file');
        }
        $img = @imagecreatefromstring($data);
        if (!$img) {
            throw new \RuntimeException('Gambar tidak valid');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $max = self::MAX_DIMENSION;
        if ($w > $max || $h > $max) {
            $scale = $max / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $res = imagecreatetruecolor($nw, $nh);
            if ($mime === 'image/png' || $mime === 'image/gif') {
                imagealphablending($res, false);
                imagesavealpha($res, true);
                $t = imagecolorallocatealpha($res, 0, 0, 0, 127);
                imagefilledrectangle($res, 0, 0, $nw, $nh, $t);
            }
            imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $res;
            $w = $nw;
            $h = $nh;
        }

        // Standard bersih: turunkan kualitas bila > 200KB.
        $stdData = '';
        $quality = 90;
        for ($i = 0; $i < 6; $i++) {
            $stdData = self::encode($img, $mime, $quality);
            if (strlen($stdData) <= self::MAX_BYTES || $quality <= 40) {
                break;
            }
            $quality -= 10;
        }

        $preview = self::watermark($img);
        $prevData = self::encode($preview, $mime, 82);
        imagedestroy($preview);
        imagedestroy($img);

        $ext = self::ALLOWED[$mime];
        $rand = bin2hex(random_bytes(12));
        $stdKey = 'events/' . $eventId . '/standard/' . $rand . '.' . $ext;
        $prevKey = 'events/' . $eventId . '/preview/' . $rand . '.' . $ext;

        self::writeFile(self::privateRoot() . '/' . $stdKey, $stdData);
        self::writeFile(self::publicRoot() . '/' . $prevKey, $prevData);

        return ['standardKey' => $stdKey, 'previewKey' => $prevKey, 'mime' => $mime, 'width' => $w, 'height' => $h];
    }

    /** Simpan cover event: resize <=1280px, selalu JPEG terkompres kecil, publik. */
    public static function storeCover(string $tmpPath, string $mime): array
    {
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Format gambar tidak didukung (JPG/PNG/WEBP/GIF/BMP)');
        }
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Ekstensi GD tidak tersedia di server');
        }

        $data = @file_get_contents($tmpPath);
        if ($data === false || $data === '') {
            throw new \RuntimeException('Gagal membaca file');
        }
        $img = @imagecreatefromstring($data);
        if (!$img) {
            throw new \RuntimeException('Gambar tidak valid');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $max = self::COVER_MAX_DIMENSION;
        if ($w > $max || $h > $max) {
            $scale = $max / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $res = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $res;
        }

        // Cover selalu disimpan sebagai JPEG agar ukurannya kecil.
        $bytes = '';
        $quality = 80;
        for ($i = 0; $i < 6; $i++) {
            ob_start();
            imagejpeg($img, null, $quality);
            $bytes = (string) ob_get_clean();
            if (strlen($bytes) <= self::COVER_MAX_BYTES || $quality <= 40) {
                break;
            }
            $quality -= 10;
        }
        imagedestroy($img);

        $key = 'events/covers/' . bin2hex(random_bytes(12)) . '.jpg';
        self::writeFile(self::publicRoot() . '/' . $key, $bytes);

        return ['coverKey' => $key, 'coverUrl' => self::previewUrl($key)];
    }

    /** Ambil key storage dari nilai cover (URL penuh atau key). */
    public static function coverKeyFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('~/uploads/(.+)$~i', $url, $m)) {
            return ltrim($m[1], '/');
        }
        if (strpos($url, '://') !== false) {
            return '';
        }
        return ltrim($url, '/');
    }

    /** Hapus file cover publik bila menunjuk ke folder covers event. */
    public static function deleteCoverFile(string $url): void
    {
        $key = self::coverKeyFromUrl($url);
        if (strpos($key, 'events/covers/') === 0) {
            self::safeUnlink(self::publicRoot() . '/' . $key);
        }
    }

    /** Simpan file original (format sumber apa adanya). */
    public static function storeOriginal(string $tmpPath, string $mime, int $eventId): array
    {
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Format gambar tidak didukung');
        }
        $ext = self::ALLOWED[$mime];
        $rand = bin2hex(random_bytes(12));
        $key = 'events/' . $eventId . '/original/' . $rand . '.' . $ext;
        $target = self::privateRoot() . '/' . $key;
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new \RuntimeException('Gagal membuat folder penyimpanan');
        }
        if (!move_uploaded_file($tmpPath, $target)) {
            throw new \RuntimeException('Gagal menyimpan file original');
        }
        return ['originalKey' => $key, 'mime' => $mime];
    }

    /** Hapus file fisik sebuah foto (preview publik, standard & original privat). */
    public static function deletePhotoFiles(array $photo): void
    {
        if (!empty($photo['preview_key'])) {
            self::safeUnlink(self::publicRoot() . '/' . ltrim((string) $photo['preview_key'], '/'));
        }
        if (!empty($photo['standard_key'])) {
            self::safeUnlink(self::privateRoot() . '/' . ltrim((string) $photo['standard_key'], '/'));
        }
        if (!empty($photo['original_key'])) {
            self::safeUnlink(self::privateRoot() . '/' . ltrim((string) $photo['original_key'], '/'));
        }
    }

    /** Hapus semua file fisik foto milik sebuah event, lalu folder event bila kosong. */
    public static function deleteEventFiles(DB $db, int $eventId): void
    {
        $rows = $db->query('SELECT preview_key,standard_key,original_key FROM vp_event_photos WHERE event_id=?', [$eventId])->result_array() ?: [];
        foreach ($rows as $row) {
            self::deletePhotoFiles($row);
        }

        $ev = $db->query('SELECT cover_image_url FROM vp_events WHERE id=? LIMIT 1', [$eventId])->row_array();
        if ($ev && !empty($ev['cover_image_url'])) {
            $key = self::coverKeyFromUrl((string) $ev['cover_image_url']);
            if (strpos($key, 'events/') === 0) {
                self::safeUnlink(self::publicRoot() . '/' . $key);
            }
        }

        self::removeEmptyDirs(self::publicRoot() . '/events/' . $eventId);
        self::removeEmptyDirs(self::privateRoot() . '/events/' . $eventId);
    }

    private static function safeUnlink(string $path): void
    {
        if ($path === '' || strpos($path, '..') !== false) {
            return;
        }
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function removeEmptyDirs(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeEmptyDirs($path);
            }
        }
        $remaining = @scandir($dir);
        if ($remaining !== false && count($remaining) === 2) {
            @rmdir($dir);
        }
    }
}
