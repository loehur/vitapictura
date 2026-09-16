# Tahap 10 — Pesanan pelanggan

- `GET Customer/Orders/index?status=` menampilkan pesanan customer aktif.
- `GET Customer/Orders/show/{id}` menampilkan snapshot penerima, item, pembayaran, dan delivery/tracking.

Table `vp_order_deliveries` menyimpan resi serta payload tracking. Tahap operasional berikutnya mengisi data ini dari admin dan webhook Biteship.
