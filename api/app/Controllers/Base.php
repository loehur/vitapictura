<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Base Controller
 * Controller default jika tidak ada route yang cocok.
 * URL: /Base/index atau /
 */
class Base extends Controller
{
    public function index()
    {
        $this->handleCors();

        $this->success([
            'name' => \Env::APP_NAME ?? 'API',
            'version' => '1.0.0',
            'mode' => \Env::MODE ?? 'dev',
        ], 'API is running');
    }
}
