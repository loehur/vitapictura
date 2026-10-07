<?php

/**
 * Salin file ini ke Env.php dan sesuaikan credential.
 * Env.php tidak di-commit ke git (lihat api/.gitignore).
 */
class Env
{
    const MODE = 'dev'; // 'dev' | 'pro'
    const DB_HOST = 'localhost';
    const APP_NAME = 'Vita Pictura API';

    /**
     * Base URL API ORINS. Ganti satu nilai ini bila domain ORINS berpindah.
     * Endpoint yang dipakai: /Product/get dan /Product/getStock.
     */
    const ORINS_BASE_URL = '';

    /**
     * Base URL publik untuk file media Botble (folder storage/).
     * Contoh: https://vitapictura.example  →  {base}/storage/products/xxx.jpg
     */
    const MEDIA_BASE_URL = 'http://localhost/vitapictura';

    /**
     * Path absolut folder storage di server (tempat upload ditulis).
     * Harus sama dengan folder yang dilayani sebagai /storage/.
     * Kosongkan = {project_root}/storage
     */
    const MEDIA_STORAGE_PATH = '';
    const CUSTOMER_UPLOAD_MAX_BYTES = 52428800; // 50 MB

    /**
     * Path privat untuk file Event (standard bersih & original).
     * HARUS di luar folder publik (tidak disajikan langsung oleh web server).
     * Kosongkan = {project_root}/storage/private
     */
    const EVENT_PRIVATE_STORAGE_PATH = '';

    /** Google Identity Services untuk login pelanggan; jangan commit Env.php. */
    const GOOGLE_OAUTH_CLIENT_ID = '';
    /** Google Maps Javascript API (Places + Maps) untuk memilih titik lokasi alamat. */
    const GOOGLE_MAPS_API_KEY = '';
    const BITESHIP_API_KEY = '';
    const BITESHIP_WEBHOOK_TOKEN = ''; // opsional: verifikasi header X-Biteship-Token / ?token=
    const BITESHIP_ORIGIN_AREA_ID = '';
    const BITESHIP_ORIGIN_LATITUDE = 0.0;
    const BITESHIP_ORIGIN_LONGITUDE = 0.0;
    const BITESHIP_COURIERS = '';
    const MIDTRANS_IS_PRODUCTION = false;
    const MIDTRANS_CLIENT_KEY = '';
    const MIDTRANS_SERVER_KEY = '';

    /**
     * Notifikasi WhatsApp via service md_waserver (metode kirim ORINS).
     * Token disamakan dengan WASERV_TOKEN di server https://waserv.asiabarufoto.com.
     */
    const WASERV_URL = 'https://waserv.asiabarufoto.com';
    const WASERV_TOKEN = '';
    /** Nama sesi/device pengirim. Kosong = ambil sesi connected pertama dari service. */
    const WASERV_SESSION = '';
    /** Target notifikasi admin (nomor atau id grup WhatsApp). */
    const WASERV_ADMIN_TARGET = '6281268098300-1581749587@g.us';
    /** false = matikan pengiriman tanpa menghapus kode. */
    const WASERV_ENABLED = true;

    /**
     * Cron/penjadwal (endpoint /Cron/*). Set token acak di production,
     * panggil dengan ?token=... atau header X-Cron-Token.
     */
    const CRON_TOKEN = '';
    /** Ambang order pending_payment dianggap expired, dalam jam (ABFLab: 25). */
    const ORDER_EXPIRY_HOURS = 25;

    /**
     * Umur sesi login (detik).
     *   SESSION_LIFETIME          = admin / sesi umum (default 7 hari).
     *   CUSTOMER_SESSION_LIFETIME = sesi pelanggan storefront (default 30 hari).
     */
    const SESSION_LIFETIME = 604800;
    const CUSTOMER_SESSION_LIFETIME = 2592000;

    /**
     * Database credentials per environment.
     * Index 0 = database utama.
     */
    const DB_CREDENTIALS = [
        'dev' => [
            0 => ['db' => 'vitapictura', 'user' => 'root', 'pass' => ''],
        ],
        'pro' => [
            0 => ['db' => 'vitapictura', 'user' => 'vitapictura', 'pass' => 'ISI_PASSWORD'],
        ],
    ];

    /**
     * Origin yang diizinkan untuk CORS.
     * Tambahkan domain production Anda di sini.
     */
    const ALLOWED_ORIGINS = [
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:5173',
        'http://localhost:5174',
        'https://vpictura.com',
        'https://www.vpictura.com',
        'https://api.vpictura.com',
        'https://event.vpictura.com',
    ];

    public static function isDev(): bool
    {
        return self::MODE === 'dev';
    }
}
