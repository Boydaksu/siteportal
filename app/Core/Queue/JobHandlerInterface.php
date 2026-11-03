<?php

declare(strict_types=1);

namespace SitePortal\Core\Queue;

use Throwable;

interface JobHandlerInterface
{
    /**
     * İlgili kuyruk işini yürütür.
     * İşlem başarısız olursa fırlatılan istisna üstel geri çekilme (backoff)
     * veya dead-letter sürecini tetikler.
     *
     * @throws Throwable
     */
    public function handle(Job $job): void;
}
