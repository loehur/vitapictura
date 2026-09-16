# Tahap 2 — Data pelanggan dan lokasi

## Keputusan yang dikunci

1. Pelanggan tidak memiliki password lokal. Identitas login disimpan terpisah dalam `vp_customer_identities`; provider pertama adalah `google`.
2. `provider_subject` Google adalah identitas unik utama, bukan email. Email tetap unik pada pelanggan untuk mencegah dua akun pelanggan menggunakan email yang sama.
3. Alamat bukan kolom di `vp_customers`. Satu pelanggan dapat memiliki banyak record `vp_customer_addresses`.
4. Hanya service API yang boleh memastikan satu alamat default per pelanggan: dalam transaksi, kosongkan `is_default` pada seluruh alamat pelanggan sebelum menetapkan alamat baru.
5. Koordinat selalu berpasangan. Lokasi tanpa koordinat boleh disimpan, tetapi belum dapat dipakai untuk memperoleh ongkir Biteship.
6. Saat tahap checkout dibuat, order akan menyimpan snapshot alamat, koordinat, dan ongkir. Riwayat order tidak berubah jika pengguna mengubah alamatnya kemudian.

## Entitas tahap ini

| Entitas | Peran |
| --- | --- |
| `vp_customers` | Profil pelanggan milik Vita Pictura. |
| `vp_customer_identities` | Koneksi profil ke Google OAuth menggunakan subject Google. |
| `vp_customer_addresses` | Banyak alamat/lokasi per pelanggan. |
| `vp_schema_migrations` | Catatan migration SQL yang telah diaplikasikan. |

## Batasan tahap ini

Tidak ada tabel katalog, keranjang, order, pembayaran, atau token OAuth persisten. Google access/refresh token tidak disimpan karena login cukup menggunakan identity token terverifikasi dan session server.

## Cara memakai

1. Buat database kosong `vitapictura`.
2. Jalankan `database/migrations/001_customer_identity_and_addresses.sql`.
3. Opsional untuk lokal: jalankan `database/seeds/customer_identity_and_addresses.demo.sql`.
4. Salin `api/app/Config/Env.example.php` menjadi `Env.php` dan isi koneksi database serta Google OAuth saat Tahap 4 dimulai.

Tidak ada migration atau seed ini yang menyentuh database ABFLab.
