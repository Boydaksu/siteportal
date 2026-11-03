<?php

declare(strict_types=1);

namespace SitePortal\Controllers;

use SitePortal\Core\Config;
use SitePortal\Core\Request;
use SitePortal\Core\Response;

final class HealthController
{
    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
    }

    public function health(Request $request): Response
    {
        $data = [
            'status' => 'ok',
            'app' => (string) $this->config->get('app.name', 'SitePortal'),
            'version' => (string) $this->config->get('app.version', '0.1.0'),
            'environment' => (string) $this->config->get('app.env', 'development'),
            'request_id' => $request->getRequestId(),
            'timestamp' => date(DATE_ATOM),
        ];

        return Response::json($data, 200);
    }
}
