<?php

declare(strict_types=1);

namespace SitePortal\Database\Seeders;

use RuntimeException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\SeederInterface;

final class RuleRegistrySeeder implements SeederInterface
{
    public function getName(): string
    {
        return 'RuleRegistrySeeder (84 kuralın tohumlanması)';
    }

    public function run(Connection $db): void
    {
        $jsonPath = dirname(__DIR__, 2) . '/schemas/rule-registry.seed.json';
        if (!is_file($jsonPath)) {
            throw new RuntimeException("Kural tohum dosyasi bulunamadi: {$jsonPath}");
        }

        $content = file_get_contents($jsonPath);
        if ($content === false) {
            throw new RuntimeException("Kural tohum dosyasi okunamadi: {$jsonPath}");
        }

        /** @var list<array{
         *     id: string,
         *     rule_version: string,
         *     category: string,
         *     severity_default: string,
         *     condition: string,
         *     reference: string
         * }> $rules */
        $rules = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $insertSql = 'INSERT INTO rules (rule_id, rule_version, category, severity, title_tr, title_en, reference_id)
            VALUES (:rule_id, :rule_version, :category, :severity, :title_tr, :title_en, :reference_id)';

        $checkSql = 'SELECT id FROM rules WHERE rule_id = :rule_id AND rule_version = :rule_version LIMIT 1';

        foreach ($rules as $rule) {
            $existing = $db->fetch($checkSql, [
                'rule_id' => $rule['id'],
                'rule_version' => $rule['rule_version'],
            ]);

            if ($existing === null) {
                $db->query($insertSql, [
                    'rule_id' => $rule['id'],
                    'rule_version' => $rule['rule_version'],
                    'category' => $rule['category'],
                    'severity' => $rule['severity_default'],
                    'title_tr' => $rule['condition'],
                    'title_en' => $rule['condition'],
                    'reference_id' => $rule['reference'],
                ]);
            }
        }
    }
}
