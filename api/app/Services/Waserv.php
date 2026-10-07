<?php
namespace App\Services;

/**
 * Klien HTTP ke layanan WhatsApp multi-device "md_waserver".
 * Diadopsi dari model Waserv ORINS (app/Models/Waserv.php).
 *
 * Endpoint produksi: https://waserv.asiabarufoto.com
 * Konfigurasi di Env.php:
 *   const WASERV_URL    = 'https://waserv.asiabarufoto.com';
 *   const WASERV_TOKEN  = '....';
 *   const WASERV_SESSION = '';  // kosong = ambil sesi connected pertama dari service
 */
class Waserv
{
    private const DEFAULT_URL = 'https://waserv.asiabarufoto.com';

    public static function baseUrl(): string
    {
        $url = defined('Env::WASERV_URL') ? trim((string) \Env::WASERV_URL) : '';
        return $url !== '' ? rtrim($url, '/') : self::DEFAULT_URL;
    }

    public static function token(): string
    {
        return defined('Env::WASERV_TOKEN') ? trim((string) \Env::WASERV_TOKEN) : '';
    }

    public static function enabled(): bool
    {
        return defined('Env::WASERV_ENABLED') ? (bool) \Env::WASERV_ENABLED : true;
    }

    /** @return array{ok:bool,code:int,error:string,data:array|null} */
    public static function request(string $method, string $path, array $payload = [], array $query = [], int $timeout = 12): array
    {
        $url = self::baseUrl() . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        $token = self::token();
        if ($token !== '') {
            $headers[] = 'Authorization: ' . $token;
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
            $headers[] = 'Content-Type: application/json';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'code' => 0,
                'error' => $curlError !== '' ? $curlError : 'Tidak dapat terhubung ke server WhatsApp',
                'data' => null,
            ];
        }

        $data = json_decode((string) $response, true);
        $ok = ($code >= 200 && $code < 300);
        $error = '';
        if (!$ok) {
            if (is_array($data)) {
                $error = (string) ($data['reason'] ?? $data['message'] ?? ('HTTP ' . $code));
            } else {
                $error = 'HTTP ' . $code;
            }
            if ($code === 401) {
                $error = 'Token WhatsApp server salah / tidak diizinkan';
            }
        }

        return [
            'ok' => $ok,
            'code' => $code,
            'error' => $error,
            'data' => is_array($data) ? $data : null,
        ];
    }

    /** Kirim pesan (POST /send). $extra: url/filename/inboxid/typing/read. */
    public static function send(string $session, string $target, string $message, array $extra = []): array
    {
        $payload = array_merge([
            'session' => $session,
            'target' => $target,
            'message' => $message,
        ], $extra);

        return self::request('POST', '/send', $payload);
    }

    public static function health(string $session = ''): array
    {
        $session = trim($session);
        return self::request('GET', '/health', [], $session !== '' ? ['session' => $session] : []);
    }

    public static function sessions(): array
    {
        return self::request('GET', '/sessions');
    }

    /**
     * Sesi pengirim untuk notifikasi.
     * Prioritas: Env::WASERV_SESSION → sesi connected/open pertama → sesi pertama.
     */
    public static function defaultSession(): string
    {
        $configured = defined('Env::WASERV_SESSION') ? trim((string) \Env::WASERV_SESSION) : '';
        if ($configured !== '') {
            return strtolower($configured);
        }

        $res = self::sessions();
        if (empty($res['ok']) || empty($res['data']['sessions']) || !is_array($res['data']['sessions'])) {
            return '';
        }

        $firstAny = '';
        foreach ($res['data']['sessions'] as $s) {
            if (!is_array($s)) {
                continue;
            }
            $id = (string) ($s['id'] ?? $s['session'] ?? $s['sessionId'] ?? '');
            if ($id === '') {
                continue;
            }
            if ($firstAny === '') {
                $firstAny = $id;
            }

            $connected = ($s['connected'] ?? false) === true;
            $state = strtolower((string) ($s['state'] ?? $s['status'] ?? ''));
            if ($connected || in_array($state, ['open', 'connected'], true)) {
                return $id;
            }
        }

        return $firstAny;
    }
}
