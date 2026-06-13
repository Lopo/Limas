<?php

namespace Limas\Message;

/**
 * Scheduled trigger to compare the running Limas version against the
 * latest GitHub release. Handler runs VersionService::doVersionCheck()
 * which produces a SystemNotice when a newer tag is available.
 */
final readonly class VersionCheckMessage
{
}
