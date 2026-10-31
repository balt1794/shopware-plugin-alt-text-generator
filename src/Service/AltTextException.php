<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Service;

/**
 * A problem the person using the plugin can understand and fix, such as a missing
 * API key or an empty balance. The message is shown in the administration as it is.
 */
final class AltTextException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $status = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** True when every other image would fail for the same reason, so a batch should stop. */
    public function isFatal(): bool
    {
        return \in_array($this->status, [401, 402], true);
    }
}
