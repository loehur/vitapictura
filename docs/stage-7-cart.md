# Tahap 7 — Keranjang

`vp_cart_items` menyimpan keranjang per customer dan mengikat satu konfigurasi produk. API menyediakan `GET Customer/Cart/index`, `POST Customer/Cart/add`, `POST Customer/Cart/update/{id}`, dan `POST Customer/Cart/remove/{id}`.

Saat add, API menghitung ulang harga dari database—bukan mempercayai harga browser—lalu menyimpan snapshot pilihan varian di `vp_product_configurations`. Upload yang dikirim harus milik customer dan produk yang sama sebelum bisa ditautkan ke konfigurasi.
