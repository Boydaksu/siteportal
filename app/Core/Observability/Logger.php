<?php

declare(strict_types=1);

namespace SitePortal\Core\Observability;

use DateTimeImmutable;
use DateTimeInterface;

final class Logger implements LoggerInterface
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';
    public const ALERT = 'alert';
    public const EMERGENCY = 'emergency';

    /** @var array<string, int> */
    private const LEVEL_PRIORITY = [
        self::DEBUG => 100,
        self::INFO => 200,
        self::NOTICE => 250,
        self::WARNING => 300,
        self::ERROR => 400,
        self::CRITICAL => 500,
        self::ALERT => 550,
        self::EMERGENCY => 600,
    ];

    private string $channel;
    private string $minLevel;
    private ?string $filePath;
    private string $format;
    private LogMasker $masker;
    private ?string $correlationId = null;
    private ?string $requestId = null;

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    private bool $bufferEnabled = false;

    /** @var (callable(string, array<string, mixed>): void)|null */
    private $customWriter = null;

    public function __construct(
        string $channel = 'app',
        string $minLevel = self::DEBUG,
        ?string $filePath = null,
        string $format = 'json',
        ?LogMasker $masker = null,
        bool $bufferEnabled = false
    ) {
        $this->channel = $channel;
        $this->minLevel = strtolower($minLevel);
        $this->filePath = $filePath;
        $this->format = strtolower($format);
        $this->masker = $masker ?? new LogMasker();
        $this->bufferEnabled = $bufferEnabled;
    }

    public static function createInMemory(string $channel = 'test', string $minLevel = self::DEBUG): self
    {
        return new self($channel, $minLevel, null, 'json', null, true);
    }

    public function withCorrelationId(?string $correlationId): self
    {
        $clone = clone $this;
        $clone->correlationId = $correlationId;
        return $clone;
    }

    public function withRequestId(?string $requestId): self
    {
        $clone = clone $this;
        $clone->requestId = $requestId;
        return $clone;
    }

    public function withChannel(string $channel): self
    {
        $clone = clone $this;
        $clone->channel = $channel;
        return $clone;
    }

    public function setCustomWriter(?callable $writer): void
    {
        $this->customWriter = $writer;
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $normalizedLevel = strtolower($level);

        $levelPriority = self::LEVEL_PRIORITY[$normalizedLevel] ?? 200;
        $minPriority = self::LEVEL_PRIORITY[$this->minLevel] ?? 100;

        if ($levelPriority < $minPriority) {
            return;
        }

        // Mask secrets from message and context
        $maskedMessage = $this->masker->maskString($message);
        $maskedContext = $this->masker->maskArray($context);

        $corrId = $this->correlationId;
        if (isset($maskedContext['correlation_id']) && is_string($maskedContext['correlation_id'])) {
            $corrId = $maskedContext['correlation_id'];
            unset($maskedContext['correlation_id']);
        }

        $reqId = $this->requestId;
        if (isset($maskedContext['request_id']) && is_string($maskedContext['request_id'])) {
            $reqId = $maskedContext['request_id'];
            unset($maskedContext['request_id']);
        }

        $record = [
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::RFC3339_EXTENDED),
            'level' => $normalizedLevel,
            'channel' => $this->channel,
            'message' => $maskedMessage,
            'context' => $maskedContext,
            'correlation_id' => $corrId,
            'request_id' => $reqId,
        ];

        if ($this->bufferEnabled) {
            $this->buffer[] = $record;
        }

        $formatted = $this->formatRecord($record);

        if ($this->customWriter !== null) {
            ($this->customWriter)($formatted, $record);
            return;
        }

        if ($this->filePath !== null) {
            $dir = dirname($this->filePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($this->filePath, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBuffer(): array
    {
        return $this->buffer;
    }

    public function clearBuffer(): void
    {
        $this->buffer = [];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function formatRecord(array $record): string
    {
        if ($this->format === 'line') {
            $corrPart = $record['correlation_id'] !== null ? " [{$record['correlation_id']}]" : '';
            $reqPart = $record['request_id'] !== null ? " [{$record['request_id']}]" : '';
            $ctxPart = !empty($record['context'])
                ? ' ' . json_encode($record['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '';

            $levelUpper = strtoupper((string) $record['level']);
            return sprintf(
                '[%s] [%s.%s]%s%s: %s%s',
                (string) $record['timestamp'],
                (string) $record['channel'],
                $levelUpper,
                $corrPart,
                $reqPart,
                (string) $record['message'],
                $ctxPart
            );
        }

        $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '{}';
    }
}
