<?php
require __DIR__ . '/../vendor/autoload.php';

use Html2PdfConverter\PdfClient;

// Get API key from environment variable
$apiKey = getenv('HTML2PDF_API_KEY');
if (!$apiKey) {
    echo "Error: HTML2PDF_API_KEY environment variable not set.\n";
    echo "Usage: HTML2PDF_API_KEY=your-api-key php examples/convert.php\n";
    exit(1);
}

$client = new PdfClient(['apiKey' => $apiKey]);

// Example: convert HTML string and save
try {
    echo "Starting conversion...\n";
    $result = $client->convert([
        'html' => '<html><body><h1>Hello PDF</h1><p>Generated at ' . date('Y-m-d H:i:s') . '</p></body></html>',
        'saveTo' => __DIR__ . '/out.pdf',
        'timeoutMs' => 120000,
    ]);
    echo "Saved to: $result\n";
    $size = filesize($result);
    echo "PDF size: $size bytes\n";
    
    if ($size > 0) {
        echo "✓ PDF generated successfully!\n";
    } else {
        echo "⚠️  Warning: PDF file is empty.\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
