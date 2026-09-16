# Tahap 1 — Fondasi proyek

Tahap ini menyediakan proyek kosong yang dapat dibangun, tanpa katalog, akun pelanggan, atau integrasi produksi.

Kontrak awal:

- Storefront: Vue 3 + Vite, output `public/store`.
- Admin: Vue 3 + Vite, output `public/admin`.
- API: PHP REST, health check `Example/Health/check`.
- Konfigurasi lokal API hanya melalui `api/app/Config/Env.php` dan tidak di-commit.

Tahap berikutnya akan menambahkan desain skema database dan migration tanpa memodifikasi data ABFLab.
