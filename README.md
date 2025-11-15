# html2pdfconverter PHP Client

PHP client for the html2pdfconverter.com SaaS API. Provides a `PdfClient` class to submit HTML/URL/file conversions, poll job status, download PDFs, and verify webhooks.

Installation

1. Require package (locally)

```bash
composer require guzzlehttp/guzzle
```

2. Add this package to your project by copying `src/` or by making it a composer package and requiring it.

Quick usage

```php
require 'vendor/autoload.php';
use Html2PdfConverter\PdfClient;

$client = new PdfClient(['apiKey' => 'YOUR_API_KEY']);
$result = $client->convert(['html' => '<h1>hi</h1>', 'saveTo' => '/tmp/out.pdf']);
echo $result; // path saved
```

Verify webhook

```php
$payload = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_HOOK_SIGNATURE'] ?? '';
$client = new PdfClient(['apiKey' => 'KEY', 'webhookSecret' => 'SECRET']);
$data = $client->verifyWebhook($payload, $sig);
```

Files created

- `composer.json` – package manifest
- `src/PdfClient.php` – main client
- `examples/convert.php` – example usage

Notes

- This client requires PHP >= 8.0 and `guzzlehttp/guzzle`.
- To test the example, run `composer install` then `php examples/convert.php`.
