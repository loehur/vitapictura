# Tahap 8 — Checkout dan ongkir

`POST Customer/Checkout/quote` menerima `address_id` dan menghitung ulang ongkir dari cart aktif melalui Biteship. `POST Customer/Checkout/create` menerima alamat serta pasangan `courier_company`/`courier_type`, meminta quote ulang, lalu membuat order pending-payment dengan snapshot alamat, item, dan harga.

Isi `BITESHIP_API_KEY`, origin area, koordinat origin, dan courier filter dalam `Env.php`. Tanpa konfigurasi ini tidak ada request eksternal dan checkout tidak dapat diteruskan.
