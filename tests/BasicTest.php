<?php
use PHPUnit\Framework\TestCase;
use Html2PdfConverter\PdfClient;

final class BasicTest extends TestCase
{
    public function testClassExistsAndCanInstantiate(): void
    {
        $client = new PdfClient(['apiKey' => 'test-key']);
        $this->assertInstanceOf(PdfClient::class, $client);
    }

    public function testConstructorRequiresApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PdfClient([]);
    }
}
