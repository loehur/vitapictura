# Tahap 9 — Midtrans

- `POST Customer/Payments/create/{orderId}` membuat Snap token untuk order `pending_payment` milik customer aktif.
- `GET Customer/Payments/status/{orderId}` memberi status lokal.
- `POST Webhook/Midtrans/notification` adalah URL notifikasi yang harus didaftarkan di Midtrans.

Webhook memverifikasi `SHA512(order_id + status_code + gross_amount + server_key)`, mencocokkan nominal order, lalu memperbarui status secara idempoten. Gunakan URL HTTPS publik untuk webhook dan isi server/client key hanya pada `Env.php`.
