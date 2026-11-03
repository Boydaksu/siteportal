<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Exceptions;

use Throwable;

final class RobotsDisallowedException extends RobotsException
{
    private string $disallowedUrl;
    private ?string $matchedRule;

    public function __construct(
        string $disallowedUrl,
        ?string $matchedRule = null,
        string $message = 'Access to this URL is disallowed by robots.txt rules.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->disallowedUrl = $disallowedUrl;
        $this->matchedRule = $matchedRule;
        parent::__construct($message, 'ROBOTS_DISALLOWED', $code, $previous);
    }

    public function getDisallowedUrl(): string
    {
        return $this->disallowedUrl;
    }

    public function getMatchedRule(): ?string
    {
        return $this->matchedRule;
    }
}
