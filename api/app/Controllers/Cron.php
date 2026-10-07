<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\WhatsApp;

/**
 * Penjadwal (cron) Vita Pictura.
 * Dipicu via HTTP: GET /Cron/expired?token=<CRON_TOKEN>
 * (atau header X-Cron-Token). Migrasi dari ABFLab Cron::expired().
 */
class Cron extends Controller
{
    /**
     * Tandai order pending_payment yang melewati ambang waktu sebagai expired,
     * lalu kirim notifikasi WhatsApp ke admin.
     */
    public function expired(): void
    {
        $this->handleCors();
        $this->guard();

        $hours = defined('Env::ORDER_EXPIRY_HOURS') ? (float) \Env::ORDER_EXPIRY_HOURS : 25.0;
        if ($hours <= 0) {
            $hours = 25.0;
        }

        $now = $GLOBALS['now'] ?? date('Y-m-d H:i:s');
        $cutoff = date('Y-m-d H:i:s', time() - (int) round($hours * 3600));

        $rows = $this->db()->query(
            "SELECT o.id, o.order_number, o.created_at, c.full_name, c.phone
               FROM vp_orders o
               INNER JOIN vp_customers c ON c.id = o.customer_id
              WHERE o.status = 'pending_payment' AND o.created_at < ?
              ORDER BY o.created_at ASC",
            [$cutoff]
        )->result_array() ?: [];

        $expired = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];

            $this->db()->update('vp_orders', ['status' => 'expired', 'updated_at' => $now], ['id' => $id]);
            $this->db()->update('vp_payments', ['status' => 'expired', 'updated_at' => $now], ['order_id' => $id]);
            $expired++;

            try {
                WhatsApp::orderExpired($row, $hours);
            } catch (\Throwable $e) {
                error_log('WA order-expired failed: ' . $e->getMessage());
            }
        }

        $this->success([
            'expired' => $expired,
            'hours' => $hours,
            'cutoff' => $cutoff,
        ], $expired . ' order expired');
    }

    private function guard(): void
    {
        $token = defined('Env::CRON_TOKEN') ? trim((string) \Env::CRON_TOKEN) : '';

        if ($token === '') {
            if (!\Env::isDev()) {
                $this->error('Cron token not configured', 503);
            }
            return;
        }

        $given = (string) ($_SERVER['HTTP_X_CRON_TOKEN'] ?? $this->query('token', ''));
        if (!hash_equals($token, $given)) {
            $this->error('Invalid cron token', 401);
        }
    }
}
