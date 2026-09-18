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

    /** Google Identity Services untuk login pelanggan; jangan commit Env.php. */
    const GOOGLE_OAUTH_CLIENT_ID = '';
    /** Google Maps Javascript API (Places + Maps) untuk memilih titik lokasi alamat. */
    const GOOGLE_MAPS_API_KEY = '';
    const BITESHIP_API_KEY = '';
    const BITESHIP_ORIGIN_AREA_ID = '';
    const BITESHIP_ORIGIN_LATITUDE = 0.0;
    const BITESHIP_ORIGIN_LONGITUDE = 0.0;
    const BITESHIP_COURIERS = '';
    const MIDTRANS_IS_PRODUCTION = false;
    const MIDTRANS_CLIENT_KEY = '';
    const MIDTRANS_SERVER_KEY = '';

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
    ];

    public static function isDev(): bool
    {
        return self::MODE === 'dev';
    }
}
