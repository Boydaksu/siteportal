<?php

declare(strict_types=1);

namespace SitePortal\Core\Database;

interface MigrationInterface
{
    public function up(Connection $db): void;

    public function down(Connection $db): void;

    public function getDescription(): string;
}
