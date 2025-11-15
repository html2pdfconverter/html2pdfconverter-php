<?php
require __DIR__ . '/../vendor/autoload.php';

use Html2PdfConverter\PdfClient;

$client = new PdfClient(['apiKey' => getenv('HTML2PDF_API_KEY') ?: 'f7abea0b-d05a-4b36-94b4-1a8a3caedaf4']);

// Example: convert HTML string and save
try {
    $result = $client->convert([
        'html' => '<html><body><h1>Hello PDF</h1></body></html>',
        'saveTo' => __DIR__ . '/out.pdf',
        'timeoutMs' => 120000,
    ]);
    echo "Saved to: $result\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
