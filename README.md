# html2pdfconverter PHP Client

A powerful, type-safe PHP client for the [html2pdfconverter.com](https://html2pdfconverter.com) SaaS API. Convert HTML, URLs, and files to PDF with ease. Supports job polling, webhooks, and direct file downloads.

## Features

- 🚀 **Simple API** – Intuitive methods for PDF conversion
- 📄 **Multiple Input Types** – Convert HTML strings, URLs, or local files
- 💾 **Flexible Output** – Save directly to disk or get raw PDF bytes
- 🔄 **Job Polling** – Automatic polling with configurable intervals and timeouts
- 🪝 **Webhook Support** – Async job handling with webhook callbacks
- ✅ **Signature Verification** – Built-in HMAC-SHA256 webhook validation
- 🧪 **Well-tested** – Includes PHPUnit tests and GitHub Actions CI
- 📋 **PHP 8.0+** – Modern PHP with strict types

## Installation

Install via Composer:

```bash
composer require html2pdfconverter/php-client
```

### Requirements

- PHP >= 8.0
- `guzzlehttp/guzzle` ^7.0 (automatically installed)

## Quick Start

```php
require 'vendor/autoload.php';
use Html2PdfConverter\PdfClient;

// Initialize the client
$client = new PdfClient([
    'apiKey' => 'your-api-key-here'
]);

// Convert HTML to PDF and save
$result = $client->convert([
    'html' => '<html><body><h1>Hello, PDF!</h1></body></html>',
    'saveTo' => '/tmp/output.pdf'
]);

echo "Saved to: $result\n";
```

## Usage

### Convert HTML String

```php
$client = new PdfClient(['apiKey' => 'YOUR_API_KEY']);

// Save to file
$filePath = $client->convert([
    'html' => '<h1>Hello World</h1><p>This is a test</p>',
    'saveTo' => './output.pdf',
    'pdfOptions' => [
        'format' => 'A4',
        'margin' => '10mm'
    ]
]);

echo "Saved to: $filePath\n";
```

### Convert from URL

```php
$result = $client->convert([
    'url' => 'https://example.com',
    'saveTo' => './website.pdf',
    'timeoutMs' => 60000 // 60 second timeout
]);
```

### Convert Local File

```php
$result = $client->convert([
    'filePath' => './document.html',
    'saveTo' => './output.pdf',
    'pdfOptions' => [
        'pageSize' => 'Letter'
    ]
]);
```

### Get Raw PDF Bytes

```php
$pdfBuffer = $client->convert([
    'html' => '<h1>Test</h1>',
    // No 'saveTo' – returns raw buffer
]);

// Send as download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="test.pdf"');
echo $pdfBuffer;
```

### Async Conversion with Webhook

```php
$jobId = $client->convert([
    'html' => '<h1>Async Job</h1>',
    'webhookUrl' => 'https://yourapp.com/webhook/pdf-ready',
    // Don't include 'saveTo' or polling happens immediately
]);

echo "Job ID: $jobId\n";
// You'll receive a POST to webhookUrl when complete
```

### Poll Job Status

```php
$jobId = 'job_abc123';

$result = $client->getJob($jobId, [
    'pollIntervalMs' => 1000,    // Check every 1 second
    'timeoutMs' => 300000,       // 5 minute timeout
    'saveTo' => './result.pdf'   // Optional: save directly
]);

echo "PDF saved to: $result\n";
```

### Verify Webhook Signature

```php
// In your webhook endpoint handler
$client = new PdfClient([
    'apiKey' => 'YOUR_API_KEY',
    'webhookSecret' => 'your-webhook-secret'
]);

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PDF_SERVICE_SIGNATURE'] ?? '';

try {
    $data = $client->verifyWebhook($payload, $signature);
    
    // $data contains:
    // {
    //   "jobId": "job_abc123",
    //   "status": "completed",
    //   "downloadUrl": "https://...",
    //   "errorMessage": null
    // }
    
    echo "Job completed: " . $data['jobId'] . "\n";
} catch (RuntimeException $e) {
    echo "Invalid signature: " . $e->getMessage() . "\n";
    http_response_code(401);
}
```

## API Reference

### Constructor

```php
$client = new PdfClient(array $options);
```

**Options:**
- `apiKey` (string, required) – Your html2pdfconverter API key
- `baseURL` (string, optional) – Custom API endpoint (default: `https://api.html2pdfconverter.com`)
- `webhookSecret` (string, optional) – Secret for webhook signature verification

### convert()

```php
public function convert(array $options): string|false|resource
```

Submit a PDF conversion job and optionally wait for completion.

**Options:**
- `html` (string, optional) – HTML content to convert
- `url` (string, optional) – URL to convert
- `filePath` (string, optional) – Path to HTML file to convert
- `pdfOptions` (array, optional) – PDF generation options (format, margin, etc.)
- `webhookUrl` (string, optional) – Webhook URL for async completion notification
- `saveTo` (string, optional) – File path to save PDF (returns path on success)
- `pollIntervalMs` (int, optional) – Poll interval in milliseconds (default: 2000)
- `timeoutMs` (int, optional) – Total timeout in milliseconds (default: 300000)

**Returns:**
- When `saveTo` is provided: file path (string)
- When `saveTo` is omitted: raw PDF bytes (string/resource)
- When `webhookUrl` is provided: job ID (string) – no polling occurs

**Throws:** `InvalidArgumentException`, `RuntimeException`

### getJob()

```php
public function getJob(string $jobId, array $options = []): string|false|resource
```

Poll a job until completion and retrieve the PDF.

**Options:**
- `pollIntervalMs` (int, optional) – Poll interval in milliseconds (default: 2000)
- `timeoutMs` (int, optional) – Total timeout in milliseconds (default: 900000)
- `saveTo` (string, optional) – File path to save PDF

**Returns:** File path (if `saveTo` provided) or raw PDF bytes

**Throws:** `RuntimeException` on timeout or job failure

### verifyWebhook()

```php
public function verifyWebhook(string $rawBody, string $signature): array
```

Verify webhook authenticity using HMAC-SHA256 signature.

**Parameters:**
- `rawBody` (string) – Raw request body from webhook
- `signature` (string) – Signature header value (format: `sha256=...`)

**Returns:** Parsed JSON payload as array

**Throws:** `RuntimeException` on invalid signature or malformed JSON

## PDF Options

Pass custom options to the PDF generator via the `pdfOptions` parameter:

```php
$client->convert([
    'html' => $html,
    'pdfOptions' => [
        'format' => 'A4',              // Page format: A4, Letter, Legal, etc.
        'margin' => '10mm',            // All margins
        'marginTop' => '20mm',         // Individual margins
        'marginBottom' => '10mm',
        'marginLeft' => '15mm',
        'marginRight' => '15mm',
        'pageSize' => 'A4',            // Alternative to 'format'
        'landscape' => false,          // Orientation
        'scale' => 1.0                 // Zoom level
    ],
    'saveTo' => './output.pdf'
]);
```

See the API documentation at [html2pdfconverter.com/docs](https://html2pdfconverter.com/docs) for all supported options.

## Error Handling

```php
use Html2PdfConverter\PdfClient;

$client = new PdfClient(['apiKey' => 'YOUR_API_KEY']);

try {
    $result = $client->convert([
        'url' => 'https://example.com',
        'saveTo' => './output.pdf',
        'timeoutMs' => 30000
    ]);
    echo "Success: $result\n";
} catch (InvalidArgumentException $e) {
    // Missing required parameters
    echo "Invalid input: " . $e->getMessage() . "\n";
} catch (RuntimeException $e) {
    // API error, timeout, or job failed
    echo "Conversion failed: " . $e->getMessage() . "\n";
}
```

## Examples

See the `examples/` directory for complete working examples:

```bash
# Run the basic example
php examples/convert.php
```

## Testing

Run PHPUnit tests:

```bash
composer install --dev
vendor/bin/phpunit
```

## Development

Clone the repository and install dependencies:

```bash
git clone https://github.com/html2pdfconverter/html2pdfconverter-php.git
cd html2pdfconverter-php
composer install
vendor/bin/phpunit
```

## License

MIT License – see LICENSE file for details.

## Support

- 📖 [API Documentation](https://html2pdfconverter.com/docs)
- 💬 [GitHub Issues](https://github.com/html2pdfconverter/html2pdfconverter-php/issues)
- 📧 [Contact Support](https://html2pdfconverter.com/support)
