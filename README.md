# Vita Pictura

Fondasi proyek migrasi ABFLab ke aplikasi Vue 3 dan PHP REST API.

## Struktur

- `api/`: REST API PHP.
- `frontend/store/`: storefront pelanggan Vue 3 + Vite.
- `frontend/admin/`: panel operasional Vue 3 + Vite.
- `public/`: hasil build frontend dan media publik.
- `docs/`: catatan keputusan dan migrasi.

## Menjalankan frontend

Jalankan `npm install` lalu `npm run dev` dari masing-masing direktori frontend.

## Konfigurasi API

Salin `api/app/Config/Env.example.php` menjadi `Env.php`, lalu isi koneksi database lokal. File `Env.php` tidak dilacak Git.

## Database lokal

Rancangan dan migration tahap awal ada di `database/migrations/`. Lihat `docs/stage-2-data-design.md` sebelum menjalankannya.
