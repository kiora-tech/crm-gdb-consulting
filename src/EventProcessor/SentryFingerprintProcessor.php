<?php

namespace App\EventProcessor;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Processor that improves error grouping in Sentry/GlitchTip by generating
 * consistent fingerprints based on error type and message, ignoring variable data.
 */
class SentryFingerprintProcessor
{
    public function __invoke(Event $event, EventHint $hint): ?Event
    {
        $fingerprint = [];

        // For exceptions: use exception class + file + line
        if (isset($hint->exception)) {
            $exception = $hint->exception;
            $fingerprint[] = get_class($exception);

            // Use the first frame from the stack trace for location
            $stacktrace = $event->getStacktrace();
            if (null !== $stacktrace && count($stacktrace->getFrames()) > 0) {
                $frame = $stacktrace->getFrames()[0];
                $fingerprint[] = $frame->getFile() ?? 'unknown';
                $fingerprint[] = $frame->getLine() ?? 0;
            }
        } else {
            // For log messages: use message + logger + level
            $message = $event->getMessage();
            if (null !== $message) {
                $fingerprint[] = $message;
            }

            $logger = $event->getLogger();
            if (null !== $logger) {
                $fingerprint[] = $logger;
            }

            $level = $event->getLevel();
            if (null !== $level) {
                $fingerprint[] = (string) $level;
            }
        }

        // Only set fingerprint if we have meaningful data
        if (!empty($fingerprint)) {
            $event->setFingerprint($fingerprint);
        }

        return $event;
    }
}
