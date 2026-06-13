<?php

namespace Limas\Message;

/**
 * Scheduled trigger to refresh the Tip-of-the-Day catalog. Handler
 * delegates to TipOfTheDayService::syncTips() which currently swallows
 * the (broken) partkeepr.org fetch silently — kept as scheduled work
 * so re-pointing the URL later is a one-line config change.
 */
final readonly class SyncTipsMessage
{
}
