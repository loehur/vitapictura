# Tahap 12 — Kesiapan migrasi dan peluncuran

## Status workspace

- Storefront dan admin berhasil dibuild.
- Audit dependency `npm audit --omit=dev` bersih untuk kedua aplikasi.
- Tidak ada `api/app/Config/Env.php` atau kredensial runtime di repository.
- Migration database tersedia dan belum dijalankan terhadap data ABFLab maupun produksi.

## Prosedur staging

1. Buat backup terverifikasi database ABFLab dan folder asetnya.
2. Buat database Vita Pictura staging kosong.
3. Jalankan migration sesuai `database/migration-order.txt`.
4. Jalankan seed demo hanya bila staging tidak memakai impor katalog nyata.
5. Impor katalog pada salinan data ABFLab, verifikasi jumlah produk/varian/media dan sampling harga/dimensi.
6. Buat `Env.php` khusus staging berisi kredensial database, Google client ID, Biteship, dan Midtrans sandbox.
7. Daftarkan origin Google dan URL webhook Midtrans HTTPS milik staging.
8. Uji pada perangkat mobile: login, alamat, varian, upload, cart, rate, Snap sandbox, webhook, status pesanan, dan admin.

## Syarat cutover produksi

- Import katalog telah direkonsiliasi dengan sumber.
- Google OAuth production, Biteship production, dan Midtrans production berhasil diuji.
- Webhook menggunakan HTTPS publik serta secret production.
- Admin pertama telah diprovision dengan hash password; tidak ada akun default.
- Backup, rollback URL, dan jendela maintenance telah disetujui pemilik sistem.

## Kondisi yang masih menghalangi peluncuran dari workspace ini

- PHP CLI tidak tersedia, sehingga lint dan integration test PHP lokal belum dapat dijalankan.
- Database Vita Pictura/staging belum dikonfigurasi.
- Tidak ada salinan database/aset ABFLab untuk impor dan rekonsiliasi.
- Kredensial serta domain production untuk Google, Biteship, Midtrans, dan webhook belum diberikan.

Karena itu, tidak ada cutover atau perubahan pada sistem eksternal yang dilakukan pada tahap ini.
