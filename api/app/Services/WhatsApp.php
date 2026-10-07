<?php
namespace App\Services;

/**
 * Notifikasi WhatsApp Vita Pictura.
 * Memakai service md_waserver melalui App\Services\Waserv (metode kirim ORINS).
 *
 * Contoh:
 *   WhatsApp::toAdmin('Order baru REF#VP123');
 *   WhatsApp::toCustomer('08123456789', 'Pesanan selesai');
 *   WhatsApp::orderReceived($order);      // order = row vp_orders (+ phone)
 *   WhatsApp::orderCompleted($order);
 *   WhatsApp::orderCancelled($order, 'Stok habis');
 */
class WhatsApp
{
    private const DEFAULT_ADMIN_TARGET = '6281268098300-1581749587@g.us';

    private static ?string $session = null;

    public static function toAdmin(string $message): array
    {
        $target = defined('Env::WASERV_ADMIN_TARGET') ? trim((string) \Env::WASERV_ADMIN_TARGET) : '';
        if ($target === '') {
            $target = self::DEFAULT_ADMIN_TARGET;
        }
        return self::send($target, $message);
    }

    public static function toCustomer(?string $phone, string $message): array
    {
        return self::send(self::normalizePhone((string) $phone), $message);
    }

    public static function send(string $target, string $message): array
    {
        $target = trim($target);

        if (!Waserv::enabled()) {
            return ['status' => false, 'error' => 'WhatsApp dinonaktifkan'];
        }
        if ($target === '') {
            return ['status' => false, 'error' => 'Target WhatsApp kosong'];
        }
        if (Waserv::token() === '') {
            return ['status' => false, 'error' => 'WASERV_TOKEN belum diatur'];
        }

        $session = self::session();
        if ($session === '') {
            return ['status' => false, 'error' => 'Tidak ada sesi WhatsApp tersedia'];
        }

        $res = Waserv::send($session, $target, $message);
        $dataStatus = $res['data']['status'] ?? null;
        $ok = !empty($res['ok']) && ($dataStatus === null || !empty($dataStatus));

        if (!$ok) {
            $reason = $res['error'] ?? ($res['data']['reason'] ?? 'unknown');
            error_log('WA send failed [' . $session . ' -> ' . $target . ']: ' . $reason);
        }

        return [
            'status' => $ok,
            'error' => $ok ? null : (string) ($res['error'] ?? ($res['data']['reason'] ?? 'send failed')),
            'session' => $session,
        ];
    }

    // ------------------------------------------------------------- pesan pesanan

    public static function orderReceived(array $order): array
    {
        $ref = (string) ($order['order_number'] ?? '');
        return self::toAdmin('VitaPictura, order baru *DITERIMA*. REF#' . $ref . '.');
    }

    public static function orderCompleted(array $order): array
    {
        $ref = (string) ($order['order_number'] ?? '');
        $text = "*VitaPictura*\nREF#" . $ref . "\nOrderan telah selesai dan siap dijemput";
        return self::toCustomer(self::orderPhone($order), $text);
    }

    public static function orderCancelled(array $order, string $note = ''): array
    {
        $ref = (string) ($order['order_number'] ?? '');
        $text = "*VitaPictura*\nREF#" . $ref . "\nTransaksi dibatalkan";
        if (trim($note) !== '') {
            $text .= "\nNote: " . trim($note);
        }
        return self::toCustomer(self::orderPhone($order), $text);
    }

    public static function adminAlert(string $text): array
    {
        return self::toAdmin($text);
    }

    // -------------------------------------------------------------------- util

    /** Ambil nomor WA pelanggan dari order (snapshot dulu, lalu kolom phone). */
    public static function orderPhone(array $order): string
    {
        $snapshot = $order['recipient_snapshot'] ?? null;
        if (is_string($snapshot) && $snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : null;
        }
        if (is_array($snapshot) && !empty($snapshot['recipientPhone'])) {
            return (string) $snapshot['recipientPhone'];
        }
        return (string) ($order['phone'] ?? '');
    }

    /** Normalisasi ke format internasional 62...; id grup (mengandung @) dibiarkan. */
    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '' || strpos($phone, '@') !== false) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        if (substr($digits, 0, 2) === '62') {
            return $digits;
        }
        if (substr($digits, 0, 1) === '0') {
            return '62' . substr($digits, 1);
        }
        if (substr($digits, 0, 1) === '8') {
            return '62' . $digits;
        }
        return $digits;
    }

    private static function session(): string
    {
        if (self::$session === null || self::$session === '') {
            self::$session = Waserv::defaultSession();
        }
        return self::$session;
    }
}
