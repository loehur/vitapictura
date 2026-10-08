<?php
namespace App\Services;
use App\Core\DB;

/**
 * Kuota foto event gratis pada produk Vita Pictura.
 *
 * Kuota = SUM(free_photo_quota * qty) dari produk yang ada di cart pelanggan.
 * Hanya foto varian "standard" yang berhak gratis, dibatasi jumlah kuota,
 * dialokasikan urut penambahan (yang lebih dulu ditambahkan lebih dulu gratis).
 *
 * - apply(): berikan gratis ke foto standard pertama..ke-k (k = min(kuota, jumlah standard)).
 *   Dipakai saat menambah foto event dan saat user menekan "Klaim gratis".
 * - clamp(): hanya mencabut kelebihan klaim (dari yang terbaru) bila kuota turun.
 *   Dipakai saat membaca cart, mengubah/menghapus produk, dan checkout — tidak menambah gratis.
 */
class EventQuota
{
    /** Total kuota gratis dari produk di cart. */
    public static function total(DB $db, int $customerId): int
    {
        $row = $db->query(
            'SELECT COALESCE(SUM(p.free_photo_quota * ci.quantity), 0) AS q
             FROM vp_cart_items ci
             INNER JOIN vp_product_configurations c ON c.id = ci.configuration_id
             INNER JOIN vp_products p ON p.id = c.product_id
             WHERE ci.customer_id = ?',
            [$customerId]
        )->row_array();

        return (int) ($row['q'] ?? 0);
    }

    /** Terapkan kuota ke foto standard urut penambahan (id asc). */
    public static function apply(DB $db, int $customerId): void
    {
        $quota = self::total($db, $customerId);
        $rows = $db->query(
            'SELECT id, variant, free_claimed FROM vp_cart_event_photos WHERE customer_id = ? ORDER BY id',
            [$customerId]
        )->result_array() ?: [];

        $std = 0;
        foreach ($rows as $r) {
            $should = 0;
            if (($r['variant'] ?? '') === 'standard') {
                $std++;
                if ($std <= $quota) {
                    $should = 1;
                }
            }
            if ((int) ($r['free_claimed'] ?? 0) !== $should) {
                $db->update('vp_cart_event_photos', ['free_claimed' => $should], ['id' => (int) $r['id']]);
            }
        }
    }

    /** Cabut kelebihan klaim (dari foto terbaru) bila kuota turun. Tidak menambah gratis. */
    public static function clamp(DB $db, int $customerId): void
    {
        $quota = self::total($db, $customerId);
        $free = $db->query(
            "SELECT id FROM vp_cart_event_photos WHERE customer_id = ? AND variant = 'standard' AND free_claimed = 1 ORDER BY id DESC",
            [$customerId]
        )->result_array() ?: [];

        $excess = count($free) - $quota;
        for ($i = 0; $i < $excess; $i++) {
            $db->update('vp_cart_event_photos', ['free_claimed' => 0], ['id' => (int) $free[$i]['id']]);
        }
    }
}
