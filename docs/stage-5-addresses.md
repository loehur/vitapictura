# Tahap 5 — Buku alamat pelanggan

Endpoint yang membutuhkan session pelanggan:

- `GET Customer/Addresses/index`
- `GET Customer/Addresses/show/{id}`
- `POST Customer/Addresses/save` (baru)
- `POST Customer/Addresses/save/{id}` (ubah)
- `POST Customer/Addresses/set-default/{id}`
- `POST Customer/Addresses/remove/{id}`

Payload simpan memakai `label`, `recipient_name`, `recipient_phone`, dan `address_line` sebagai data wajib. `latitude` dan `longitude` opsional, tetapi harus keduanya ada bila salah satu dikirim.

API memakai transaksi saat membuat, memperbarui, atau mengganti alamat default. Bila alamat default dihapus, alamat terbaru yang tersisa menjadi default. Endpoint checkout tahap berikutnya hanya akan mengizinkan alamat milik customer session yang sedang aktif.
