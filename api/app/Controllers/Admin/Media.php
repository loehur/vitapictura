<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Helpers\AdminAuth;

/**
 * Media library untuk admin Vita Pictura.
 * Folder + file gambar disimpan di tabel vp_media_folders / vp_media,
 * file fisik ditulis ke folder uploads (sama dengan media produk).
 */
class Media extends Controller
{
    private const FOLDERS = 'vp_media_folders';
    private const FILES = 'vp_media';

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'image/bmp' => 'bmp',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/vnd.rar' => 'rar',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    public function index(): void
    {
        $this->browse();
    }

    /** GET /Admin/Media/browse?folder_id=0 */
    public function browse(): void
    {
        $this->handleCors();
        $this->admin();
        if (!$this->isGet()) {
            $this->error('Method not allowed', 405);
        }

        $this->ensure();
        $folderId = (int) $this->query('folder_id', 0);

        $folders = $this->db()->query(
            'SELECT id, name, slug, parent_id, created_at, updated_at FROM ' . self::FOLDERS . ' WHERE parent_id = ? ORDER BY name ASC',
            [$folderId]
        )->result_array() ?: [];

        $files = $this->db()->query(
            'SELECT id, name, folder_id, mime_type, size_bytes, path, alt_text, created_at, updated_at FROM ' . self::FILES . ' WHERE folder_id = ? ORDER BY created_at DESC, id DESC',
            [$folderId]
        )->result_array() ?: [];

        $this->success([
            'folderId' => $folderId,
            'breadcrumb' => $this->buildBreadcrumb($folderId),
            'folders' => array_map([$this, 'transformFolder'], $folders),
            'files' => array_map([$this, 'transformFile'], $files),
        ], 'Media loaded');
    }

    /** POST /Admin/Media/create_folder { name, parent } */
    public function create_folder(): void
    {
        $this->handleCors();
        $this->admin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->ensure();
        $body = $this->getBody();
        $name = trim((string) ($body['name'] ?? ''));
        $parent = (int) ($body['parent'] ?? $body['parent_id'] ?? 0);

        if ($name === '') {
            $this->error('Nama folder wajib diisi', 422);
        }

        $slug = $this->uniqueFolderSlug($this->slugify($name), $parent);
        $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');

        $id = (int) $this->db()->insert(self::FOLDERS, [
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parent,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($id <= 0) {
            $this->error('Gagal membuat folder', 500);
        }

        $row = $this->db()->get_where(self::FOLDERS, ['id' => $id], 1)->row_array();
        $this->success($this->transformFolder($row), 'Folder dibuat');
    }

    /** POST /Admin/Media/rename_folder { id, name } */
    public function rename_folder(): void
    {
        $this->handleCors();
        $this->admin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->ensure();
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);
        $name = trim((string) ($body['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            $this->error('id dan nama folder wajib diisi', 422);
        }

        $row = $this->db()->get_where(self::FOLDERS, ['id' => $id], 1)->row_array();
        if (!$row) {
            $this->error('Folder tidak ditemukan', 404);
        }

        $slug = $this->uniqueFolderSlug($this->slugify($name), (int) $row['parent_id'], $id);
        $this->db()->update(self::FOLDERS, [
            'name' => $name,
            'slug' => $slug,
            'updated_at' => $GLOBALS['now'] ?? date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $row = $this->db()->get_where(self::FOLDERS, ['id' => $id], 1)->row_array();
        $this->success($this->transformFolder($row), 'Folder diubah');
    }

    /** POST /Admin/Media/delete_folder { id } */
    public function delete_folder(): void
    {
        $this->handleCors();
        $this->admin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->ensure();
        $id = (int) ($this->getBody()['id'] ?? 0);
        if ($id <= 0) {
            $this->error('id wajib diisi', 422);
        }

        $childFolders = (int) ($this->db()->query('SELECT COUNT(*) total FROM ' . self::FOLDERS . ' WHERE parent_id = ?', [$id])->row_array()['total'] ?? 0);
        $childFiles = (int) ($this->db()->query('SELECT COUNT(*) total FROM ' . self::FILES . ' WHERE folder_id = ?', [$id])->row_array()['total'] ?? 0);

        if ($childFolders > 0 || $childFiles > 0) {
            $this->error('Folder tidak kosong. Kosongkan dulu sebelum menghapus.', 422);
        }

        $this->db()->delete(self::FOLDERS, ['id' => $id]);
        $this->success(['id' => $id], 'Folder dihapus');
    }

    /** POST /Admin/Media/upload  multipart: folder_id, file|files[] */
    public function upload(): void
    {
        $this->handleCors();
        $this->admin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->ensure();
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        $uploaded = $this->normalizeUploads();

        if (!$uploaded) {
            $this->error('Tidak ada file yang diunggah', 422);
        }

        $relativeDir = $this->folderRelativePath($folderId);
        $dirKey = 'media' . ($relativeDir !== '' ? '/' . $relativeDir : '');
        $absDir = $this->storageRoot() . '/' . $dirKey;
        if (!is_dir($absDir) && !mkdir($absDir, 0750, true)) {
            $this->error('Folder penyimpanan tidak dapat dibuat', 500);
        }

        $max = (int) (\Env::CUSTOMER_UPLOAD_MAX_BYTES ?? 52428800);
        $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
        $saved = [];

        foreach ($uploaded as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > $max) {
                continue;
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset(self::ALLOWED[$mime])) {
                continue;
            }
            $ext = self::ALLOWED[$mime];

            $original = (string) ($file['name'] ?? 'file');
            $base = $this->slugify(pathinfo($original, PATHINFO_FILENAME)) ?: 'file';
            $finalName = $base . '-' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $ext;
            $key = $dirKey . '/' . $finalName;
            $absolute = $this->storageRoot() . '/' . $key;

            if (!move_uploaded_file($file['tmp_name'], $absolute)) {
                continue;
            }

            $id = $this->db()->insert(self::FILES, [
                'name' => $original,
                'folder_id' => $folderId,
                'mime_type' => $mime,
                'size_bytes' => (int) (filesize($absolute) ?: $file['size']),
                'path' => $key,
                'alt_text' => pathinfo($original, PATHINFO_FILENAME),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (!$id) {
                @unlink($absolute);
                continue;
            }

            $row = $this->db()->get_where(self::FILES, ['id' => $id], 1)->row_array();
            if ($row) {
                $saved[] = $this->transformFile($row);
            }
        }

        if (!$saved) {
            $this->error('Upload gagal. Format yang didukung: JPG, PNG, WEBP, GIF, BMP, SVG, PDF, ZIP, RAR, DOC, DOCX, XLS, XLSX.', 422);
        }

        $this->success(['items' => $saved, 'count' => count($saved)], 'Upload berhasil');
    }

    /** POST /Admin/Media/rename_file { id, name } */
    public function rename_file(): void
    {
        $this->handleCors();
        $this->admin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->ensure();
        $body = $this->getBody();
        $id = (int) ($body['id'] ?? 0);
        $name = trim((string) ($body['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            $this->error('id dan nama file wajib diisi', 422);
        }

        $row = $this->db()->get_where(self::FILES, ['id' => $id], 1)->row_array();
        if (!$row) {
            $this->error('File tidak ditemukan', 404);
        }

        $this->db()->update(self::FILES, [
            'name' => $name,
            'updated_at' => $GLOBALS['now'] ?? date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $row = $this->db()->get_where(self::FILES, ['id' => $id], 1)->row_array();
        $this->success($this->transformFile($row), 'File diubah');
    }

    /** POST /Admin/Media/delete_file { id } */
    public function delete_file(): void
    {
        $this->handleCors();
        $this->admin();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $this->ensure();
        $id = (int) ($this->getBody()['id'] ?? 0);
        if ($id <= 0) {
            $this->error('id wajib diisi', 422);
        }

        $row = $this->db()->get_where(self::FILES, ['id' => $id], 1)->row_array();
        if (!$row) {
            $this->error('File tidak ditemukan', 404);
        }

        $absolute = $this->absolutePath($row['path'] ?? '');
        if ($absolute !== '' && is_file($absolute)) {
            @unlink($absolute);
        }

        $this->db()->delete(self::FILES, ['id' => $id]);
        $this->success(['id' => $id], 'File dihapus');
    }

    private function admin(): array
    {
        $a = AdminAuth::user();
        if (!$a) {
            $this->error('Unauthorized', 401);
        }
        return $a;
    }

    private function ensure(): void
    {
        $this->db()->query("CREATE TABLE IF NOT EXISTS " . self::FOLDERS . " (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), KEY vp_media_folders_parent_index(parent_id, slug)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db()->query("CREATE TABLE IF NOT EXISTS " . self::FILES . " (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, folder_id BIGINT UNSIGNED NOT NULL DEFAULT 0, mime_type VARCHAR(120) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0, path VARCHAR(500) NOT NULL, alt_text VARCHAR(190) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY vp_media_path_unique(path), KEY vp_media_folder_index(folder_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function transformFolder(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'parent' => (int) ($row['parent_id'] ?? 0),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function transformFile(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $path = (string) ($row['path'] ?? '');
        $mime = (string) ($row['mime_type'] ?? '');
        $isImage = strpos($mime, 'image/') === 0;
        if (!$isImage && $path !== '') {
            $isImage = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'], true);
        }
        return [
            'id' => (int) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'folderId' => (int) ($row['folder_id'] ?? 0),
            'mimeType' => $mime,
            'size' => (int) ($row['size_bytes'] ?? 0),
            'sizeLabel' => $this->formatBytes((int) ($row['size_bytes'] ?? 0)),
            'path' => $path,
            'url' => $this->urlForPath($path),
            'alt' => (string) ($row['alt_text'] ?? ''),
            'isImage' => $isImage,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function buildBreadcrumb(int $folderId): array
    {
        $crumbs = [['id' => 0, 'name' => 'Media']];
        $chain = [];
        $current = $folderId;
        $guard = 0;
        while ($current > 0 && $guard < 40) {
            $row = $this->db()->get_where(self::FOLDERS, ['id' => $current], 1)->row_array();
            if (!$row) {
                break;
            }
            array_unshift($chain, ['id' => (int) $row['id'], 'name' => (string) $row['name']]);
            $current = (int) $row['parent_id'];
            $guard++;
        }
        return array_merge($crumbs, $chain);
    }

    private function folderRelativePath(int $folderId): string
    {
        if ($folderId <= 0) {
            return '';
        }
        $parts = [];
        $current = $folderId;
        $guard = 0;
        while ($current > 0 && $guard < 40) {
            $row = $this->db()->get_where(self::FOLDERS, ['id' => $current], 1)->row_array();
            if (!$row) {
                break;
            }
            $parts[] = $row['slug'] ?: $this->slugify((string) $row['name']);
            $current = (int) $row['parent_id'];
            $guard++;
        }
        return implode('/', array_reverse($parts));
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? 'item';
        return trim($text, '-') ?: 'item';
    }

    private function uniqueFolderSlug(string $slug, int $parent, int $ignoreId = 0): string
    {
        $base = $slug;
        $i = 1;
        while (true) {
            $row = $this->db()->query('SELECT id FROM ' . self::FOLDERS . ' WHERE slug = ? AND parent_id = ? LIMIT 1', [$slug, $parent])->row_array();
            if (!$row || (int) $row['id'] === $ignoreId) {
                return $slug;
            }
            $i++;
            $slug = $base . '-' . $i;
        }
    }

    private function normalizeUploads(): array
    {
        if (!empty($_FILES['file']) && is_array($_FILES['file'])) {
            if (!isset($_FILES['file']['name']) || is_string($_FILES['file']['name'])) {
                return [$_FILES['file']];
            }
        }

        if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
            $out = [];
            foreach ($_FILES['files']['name'] as $i => $name) {
                $out[] = [
                    'name' => $name,
                    'type' => $_FILES['files']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['files']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['files']['size'][$i] ?? 0,
                ];
            }
            return $out;
        }

        return [];
    }

    private function storageRoot(): string
    {
        if (defined('Env::MEDIA_STORAGE_PATH') && \Env::MEDIA_STORAGE_PATH !== '') {
            return rtrim(str_replace('\\', '/', (string) \Env::MEDIA_STORAGE_PATH), '/');
        }
        return str_replace('\\', '/', dirname(__DIR__, 4)) . '/public/uploads';
    }

    private function baseUrl(): string
    {
        $base = (string) (\Env::MEDIA_BASE_URL ?? '');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return rtrim($base, '/');
    }

    private function urlForPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return '';
        }
        return $this->baseUrl() . '/uploads/' . $path;
    }

    private function absolutePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return '';
        }
        $root = $this->storageRoot();
        $absolute = $root . '/' . $path;
        return strpos($absolute, $root) === 0 ? $absolute : '';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }
}
