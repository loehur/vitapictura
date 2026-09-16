# Tahap 4 — Login pelanggan dengan Google

Login pelanggan memakai Google Identity Services dengan ID token dan session PHP. Sistem tidak menyimpan password pelanggan, Google access token, refresh token, atau client secret.

## Konfigurasi yang diperlukan

1. Buat OAuth Client ID bertipe **Web application** di Google Cloud Console.
2. Tambahkan origin storefront, misalnya `http://localhost:5173` untuk lokal dan domain storefront production nantinya.
3. Salin `api/app/Config/Env.example.php` menjadi `Env.php`.
4. Isi `GOOGLE_OAUTH_CLIENT_ID` dengan client ID yang sama dengan konfigurasi Google.

## Kontrak API

- `GET Customer/Auth/config`: mengirim client ID publik ke storefront.
- `POST Customer/Auth/google`: menerima `credential` dari Google, memvalidasi issuer, audience, masa berlaku, serta status email terverifikasi; kemudian membuat/memperbarui customer dan session.
- `GET Customer/Auth/me`: membaca customer dari session.
- `POST Customer/Auth/logout`: menghapus session pelanggan.

Email bukan identitas OAuth utama. API mengenali akun Google menggunakan pasangan `provider=google` dan `provider_subject`, sehingga perubahan email akun Google tidak membuat akun pelanggan baru.
