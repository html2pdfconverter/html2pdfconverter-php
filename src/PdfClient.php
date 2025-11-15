<?php
namespace Html2PdfConverter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;

class PdfClient
{
    private Client $client;
    private string $apiKey;
    private ?string $webhookSecret;
    private string $baseURL;

    public function __construct(array $options)
    {
        $apiKey = $options['apiKey'] ?? '';
        if (!$apiKey) {
            throw new \InvalidArgumentException('Missing apiKey');
        }
        $this->apiKey = $apiKey;
        $this->webhookSecret = $options['webhookSecret'] ?? null;
        $this->baseURL = $options['baseURL'] ?? 'https://api.html2pdfconverter.com';

        $this->client = new Client([
            'base_uri' => $this->baseURL,
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
            'http_errors' => false,
            'timeout' => 0,
        ]);
    }

    /**
     * Convert HTML/URL/File → PDF
     * Returns jobId string when webhookUrl provided, otherwise returns saved path or raw bytes.
     * @param array $options
     * @return string|false|resource
     * @throws \Exception
     */
    public function convert(array $options)
    {
        $html = $options['html'] ?? null;
        $url = $options['url'] ?? null;
        $filePath = $options['filePath'] ?? null;
        $pdfOptions = $options['pdfOptions'] ?? [];
        $webhookUrl = $options['webhookUrl'] ?? null;
        $pollIntervalMs = $options['pollIntervalMs'] ?? 2000;
        $timeoutMs = $options['timeoutMs'] ?? 300000;
        $saveTo = $options['saveTo'] ?? null;

        if (!$html && !$url && !$filePath) {
            throw new \InvalidArgumentException('You must provide html, url, or filePath');
        }

        $jobId = null;

        if ($filePath || $html) {
            $tempFile = null;
            if ($html) {
                $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'temp-' . bin2hex(random_bytes(16)) . '.html';
                file_put_contents($tempFile, $html);
                $filePath = $tempFile;
            }

            if (!$filePath || !file_exists($filePath)) {
                if ($tempFile && file_exists($tempFile)) unlink($tempFile);
                throw new \InvalidArgumentException('No file path to send for conversion.');
            }

            $multipart = [
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ],
                [
                    'name' => 'options',
                    'contents' => json_encode($pdfOptions),
                ],
            ];
            if ($webhookUrl) {
                $multipart[] = ['name' => 'webhookUrl', 'contents' => $webhookUrl];
            }

            try {
                $res = $this->client->request('POST', '/convert', [
                    'multipart' => $multipart,
                    'headers' => [],
                ]);
                $status = $res->getStatusCode();
                $body = (string)$res->getBody();
                $data = json_decode($body, true);
                if ($status >= 400) {
                    $msg = $data['message'] ?? $body;
                    throw new \RuntimeException("PDF conversion failed (status: $status): $msg");
                }
                $jobId = $data['jobId'] ?? null;
            } catch (GuzzleException $e) {
                if (isset($tempFile) && file_exists($tempFile)) unlink($tempFile);
                throw $e;
            } finally {
                if (isset($tempFile) && file_exists($tempFile)) unlink($tempFile);
            }
        } else {
            try {
                $res = $this->client->request('POST', '/convert', [
                    'json' => ['url' => $url, 'options' => $pdfOptions, 'webhookUrl' => $webhookUrl],
                ]);
                $status = $res->getStatusCode();
                $body = (string)$res->getBody();
                $data = json_decode($body, true);
                if ($status >= 400) {
                    $msg = $data['message'] ?? $body;
                    throw new \RuntimeException("PDF conversion failed (status: $status): $msg");
                }
                $jobId = $data['jobId'] ?? null;
            } catch (GuzzleException $e) {
                throw $e;
            }
        }

        if (!$jobId) {
            throw new \RuntimeException('Failed to create conversion job');
        }

        if ($webhookUrl) return $jobId;

        return $this->getJob($jobId, ['pollIntervalMs' => $pollIntervalMs, 'timeoutMs' => $timeoutMs, 'saveTo' => $saveTo]);
    }

    /**
     * Poll job until completion and return buffer or saved path
     * @param string $jobId
     * @param array $options
     * @return string|false|resource
     * @throws \Exception
     */
    public function getJob(string $jobId, array $options = [])
    {
        $pollIntervalMs = $options['pollIntervalMs'] ?? 2000;
        $timeoutMs = $options['timeoutMs'] ?? 900000;
        $saveTo = $options['saveTo'] ?? null;

        $start = microtime(true) * 1000;

        while (true) {
            try {
                $res = $this->client->request('GET', '/jobs/' . rawurlencode($jobId));
            } catch (GuzzleException $e) {
                throw $e;
            }
            $status = $res->getStatusCode();
            $body = (string)$res->getBody();
            if ($status >= 400) {
                $data = json_decode($body, true);
                $msg = $data['message'] ?? $body;
                throw new \RuntimeException("PDF job status check failed (status: $status): $msg");
            }
            $job = json_decode($body, true);

            $jobStatus = $job['status'] ?? null;
            if ($jobStatus === 'completed' && !empty($job['downloadUrl'])) {
                $downloadUrl = $job['downloadUrl'];
                if ($saveTo) {
                    try {
                        $stream = $this->client->request('GET', $downloadUrl, ['stream' => true]);
                        $out = fopen($saveTo, 'wb');
                        foreach ($stream->getBody() as $chunk) {
                            fwrite($out, $chunk);
                        }
                        fclose($out);
                        return $saveTo;
                    } catch (GuzzleException $e) {
                        throw $e;
                    }
                } else {
                    $res2 = $this->client->request('GET', $downloadUrl, ['sink' => fopen('php://temp', 'w+b')]);
                    $contents = $res2->getBody()->getContents();
                    return $contents;
                }
            }

            if ($jobStatus === 'failed') {
                $err = $job['errorMessage'] ?? 'Unknown error';
                throw new \RuntimeException('PDF conversion failed: ' . $err);
            }

            if ((microtime(true) * 1000) - $start > $timeoutMs) {
                throw new \RuntimeException('PDF conversion timed out after ' . ($timeoutMs / 1000) . ' seconds waiting for completion');
            }

            usleep((int)$pollIntervalMs * 1000);
        }
    }

    /**
     * Verify webhook authenticity and return parsed payload
     * @param string $rawBody
     * @param string $signature
     * @return mixed
     * @throws \Exception
     */
    public function verifyWebhook(string $rawBody, string $signature)
    {
        if (!$this->webhookSecret) {
            throw new \RuntimeException('Missing webhookSecret in PdfClient constructor');
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->webhookSecret);
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid webhook signature');
        }

        $data = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in webhook payload');
        }
        return $data;
    }
}
