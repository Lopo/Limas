<?php

namespace Limas\Message;

/**
 * Scheduled trigger to persist a row of inventory statistics. Handler
 * delegates to StatisticService::createStatisticSnapshot() and feeds
 * the Statistics chart in the View menu.
 */
final readonly class CreateStatisticSnapshotMessage
{
}
