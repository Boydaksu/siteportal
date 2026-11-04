<?php

declare(strict_types=1);

namespace SitePortal\Controllers;

use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Request;
use SitePortal\Core\Response;
use Throwable;

final class HealthController
{
    private Config $config;
    private ?Connection $db;

    public function __construct(?Config $config = null, ?Connection $db = null)
    {
        $this->config = $config ?? Config::getInstance();
        $this->db = $db;
    }

    public function health(Request $request): Response
    {
        $status = 'ok';
        $statusCode = 200;
        $checks = [];

        $isDetailed = $request->getQuery('detailed') === '1'
            || $request->getQuery('detailed') === 'true';

        if ($isDetailed) {
            $checks['database'] = $this->checkDatabase();
            if ($checks['database'] !== 'ok') {
                $status = 'degraded';
                $statusCode = 503;
            }
        }

        $data = [
            'status' => $status,
            'app' => (string) $this->config->get('app.name', 'SitePortal'),
            'version' => (string) $this->config->get('app.version', '0.1.0'),
            'environment' => (string) $this->config->get('app.env', 'development'),
            'request_id' => $request->getRequestId(),
            'timestamp' => date(DATE_ATOM),
        ];

        if ($isDetailed) {
            $data['checks'] = $checks;
        }

        return Response::json($data, $statusCode)
            ->withHeader('X-Request-Id', $request->getRequestId())
            ->withCorrelationId($request->getCorrelationId());
    }

    private function checkDatabase(): string
    {
        try {
            $connection = $this->db;
            if ($connection === null) {
                /** @var string $driver */
                $driver = (string) $this->config->get('database.default', 'mysql');
                /** @var array{
                 *     driver?: string,
                 *     host?: string,
                 *     port?: int,
                 *     database?: string,
                 *     username?: string,
                 *     password?: string,
                 *     charset?: string,
                 *     options?: array<int, mixed>
                 * } $connConfig */
                $connConfig = (array) $this->config->get("database.connections.{$driver}", []);
                if (empty($connConfig)) {
                    return 'not_configured';
                }
                $connection = Connection::create($connConfig);
            }

            $stmt = $connection->getPdo()->query('SELECT 1');
            if ($stmt === false || $stmt->fetch() === false) {
                return 'failed';
            }
            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
