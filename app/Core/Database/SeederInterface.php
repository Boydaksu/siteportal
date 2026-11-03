<?php

declare(strict_types=1);

namespace SitePortal\Core\Database;

interface SeederInterface
{
    public function run(Connection $db): void;

    public function getName(): string;
}
