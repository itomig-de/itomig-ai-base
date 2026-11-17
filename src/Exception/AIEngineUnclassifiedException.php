<?php

namespace Itomig\iTop\Extension\AIBase\Exception;

/**
 * Thrown when the AI engine failed but the cause could not be classified.
 *
 * This is the fallback for provider errors that match neither the HTTP status
 * categories (rate limit, authentication, context window) nor the known vision
 * rejections. Callers should present it as an unexpected engine error rather
 * than as a network problem — {@see AINetworkException} is reserved for actual
 * transport failures (connection refused, DNS, timeouts).
 */
class AIEngineUnclassifiedException extends AIEngineException
{
}
