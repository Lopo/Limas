<?php

namespace Limas\Tests;

use Limas\Service\UploadedFileService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Regression guard for the remote-download hang. replaceFromURL sets
 * stream:true (to cap the body as it streams); with allow_url_fopen on, Guzzle
 * routes that to the PHP StreamHandler, whose read loop blocks on a keep-alive
 * connection until the request times out ("Unable to read from stream").
 * Forcing the cURL handler fixed it. The bug shipped unnoticed because every
 * other attachment test uses a data: URI, so the real remote-fetch path never
 * ran — this exercises it against a local server that holds the connection open.
 */
class UploadedFileDownloadTest
	extends KernelTestCase
{
	/**
	 * The download must finish as soon as Content-Length bytes have arrived,
	 * NOT wait for the keep-alive connection to close. cURL does; the old
	 * StreamHandler did not — so the elapsed-time bound is what catches a
	 * regression back to it.
	 */
	public function testKeepAliveDownloadDoesNotBlock(): void
	{
		// Raw HTTP/1.1 server: send the body with Content-Length + keep-alive,
		// then hold the socket open (~3s) instead of closing. Prints "ready"
		// once it is listening so the test doesn't race the bind.
		$server = <<<'PHP'
$port = (int) $argv[1];
$sock = @stream_socket_server("tcp://127.0.0.1:$port", $e, $s);
if ($sock === false) { fwrite(STDERR, "bind failed\n"); exit(1); }
echo "ready\n"; flush();
$conn = @stream_socket_accept($sock, 10);
if ($conn === false) { exit(0); }
$req = '';
while (!str_contains($req, "\r\n\r\n")) {
    $chunk = fread($conn, 2048);
    if ($chunk === '' || $chunk === false) { break; }
    $req .= $chunk;
}
$body = str_repeat('%PDF-1.4 ', 600);
fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: application/pdf\r\n"
    . "Content-Length: " . strlen($body) . "\r\nConnection: keep-alive\r\n\r\n" . $body);
usleep(3000000); // hold the connection open — the stream handler would block here
fclose($conn);
PHP;

		// Reserve a free port, then hand it to the server subprocess.
		$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		self::assertIsResource($probe, 'could not reserve a local port');
		$name = stream_socket_get_name($probe, false);
		$port = (int)substr($name, strrpos($name, ':') + 1);
		fclose($probe);

		$proc = proc_open(
			[PHP_BINARY, '-r', $server, (string)$port],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes
		);
		self::assertIsResource($proc, 'could not start the local HTTP server');

		try {
			// Wait for the server to report it is listening.
			stream_set_timeout($pipes[1], 5);
			$ready = fgets($pipes[1]);
			self::assertSame('ready', trim((string)$ready), 'local server did not come up');

			$svc = self::getContainer()->get(UploadedFileService::class);
			$download = new \ReflectionMethod($svc, 'downloadUrlBody');

			$start = microtime(true);
			[$data, $contentType] = $download->invoke(
				$svc,
				"http://127.0.0.1:{$port}/x.pdf",
				'127.0.0.1',
				['127.0.0.1'],
				null
			);
			$elapsed = microtime(true) - $start;

			self::assertSame(600 * strlen('%PDF-1.4 '), strlen($data), 'the full body must be read');
			self::assertStringContainsString('application/pdf', $contentType);
			self::assertLessThan(
				1.5,
				$elapsed,
				'download blocked on a held keep-alive connection — the stream handler is back'
			);
		} finally {
			proc_terminate($proc);
			foreach ($pipes as $pipe) {
				if (is_resource($pipe)) {
					fclose($pipe);
				}
			}
			proc_close($proc);
		}
	}
}
