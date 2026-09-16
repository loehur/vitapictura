# Tahap 3 — Katalog

## Yang telah tersedia

- Migration `002_catalog.sql` untuk kategori, produk, media, kelompok opsi, dan nilai opsi.
- Endpoint baca-only `Store/Catalog/home`, `categories`, `products`, dan `show/{slug}`.
- Storefront mobile-first untuk Beranda dan Detail Produk.
- Seed katalog lokal agar API dapat diuji tanpa data ABFLab.

## Pemetaan ABFLab yang akan dieksekusi pada database salinan

| ABFLab | Vita Pictura | Catatan |
| --- | --- | --- |
| `produk.produk_id` | `vp_products.legacy_product_id` | ID asal disimpan untuk pelacakan dan idempotensi impor. |
| `produk.produk` | `vp_products.name` | Slug dibentuk deterministik dari nama dan ID. |
| `produk.harga`, `berat`, `p`, `l`, `t` | Harga/berat/dimensi produk | Dimensi lama dikonversi ke milimeter setelah unitnya dikonfirmasi. |
| `assets/img/produk_detail/*` | `vp_product_media` | Disalin ke media publik baru, tidak memakai URL lama. |
| `varian_grup_1`, `varian_1` | Option group dan value | Harga/dimensi tambahan menjadi delta. |
| `varian_grup_2`, `varian_2`, `v2_head` | Option group/value bertingkat | Diputuskan per produk saat uji impor karena relasinya bersyarat. |

## Batasan

Data ABFLab belum dieksekusi ke Vita Pictura karena koneksi database sumber dan salinan aset belum diberikan. Tidak ada kode pada tahap ini yang dapat menulis ke database ABFLab.
