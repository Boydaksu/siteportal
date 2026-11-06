<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Services;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Observability\Logger;

final class GuestAnalysisMigrationService
{
    private Connection $db;
    private ?Logger $logger;

    public function __construct(Connection $db, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Misafir oturumundaki sahipsiz analizleri giriş yapan kullanıcıya aktarır.
     *
     * @param list<int> $analysisIds
     * @return int Aktarılan analiz sayısı
     */
    public function migrate(int $userId, array $analysisIds): int
    {
        if (empty($analysisIds) || $userId <= 0) {
            return 0;
        }

        $migratedCount = 0;

        foreach ($analysisIds as $id) {
            // Yalnızca user_id IS NULL olan analizler sahiplenebilir
            $record = $this->db->fetch(
                'SELECT id, user_id FROM analyses WHERE id = :id',
                ['id' => $id]
            );

            if ($record !== null && ($record['user_id'] === null || (int) $record['user_id'] === 0)) {
                $this->db->execute(
                    'UPDATE analyses SET user_id = :user_id WHERE id = :id AND user_id IS NULL',
                    [
                        'user_id' => $userId,
                        'id' => $id,
                    ]
                );
                $migratedCount++;
            }
        }

        if ($migratedCount > 0 && $this->logger !== null) {
            $this->logger->info('Guest analyses migrated to user', [
                'user_id' => $userId,
                'migrated_count' => $migratedCount,
                'analysis_ids' => $analysisIds,
            ]);
        }

        return $migratedCount;
    }
}
