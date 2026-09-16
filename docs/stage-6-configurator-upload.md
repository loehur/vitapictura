# Tahap 6 — Konfigurator dan upload

- `POST Customer/Configurator/quote`: memvalidasi setiap opsi wajib, menghitung delta harga, lalu mengembalikan harga tanpa menyimpan cart.
- `POST Customer/Configurator/upload`: menerima satu berkas `multipart/form-data` dengan field `file` dan opsional `product_id`.

Tipe yang diizinkan: JPG, PNG, PDF, ZIP. Ukuran maksimum default 50 MB, dapat diubah lewat `CUSTOMER_UPLOAD_MAX_BYTES`. File disimpan menggunakan nama acak di `public/uploads/customer-uploads/{customer}/{tahun}/{bulan}/`; nama asli hanya metadata. Tahap keranjang berikutnya akan menautkan upload pada konfigurasi dan item cart.
