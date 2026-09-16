<?php

namespace App\Controllers\Example;

use App\Core\Controller;

/**
 * Contoh controller nested.
 * URL: /Example/Health/check
 */
class Health extends Controller
{
    public function check()
    {
        $this->handleCors();

        $this->success([
            'status' => 'ok',
            'timestamp' => $GLOBALS['now'] ?? date('Y-m-d H:i:s'),
        ], 'Health check passed');
    }
}
