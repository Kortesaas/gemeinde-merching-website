<?php

namespace Tests\Unit;

use App\Services\Uploads\RejectedUpload;
use App\Services\Uploads\UploadInspector;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadInspectorTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N\x00\x00\x00\x00IEND\xaeB`\x82";

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    public function test_valid_pdf_is_accepted_with_generated_name(): void
    {
        $file = UploadedFile::fake()->createWithContent('../../Gemeinderat Protokoll<script>.PDF', self::PDF);

        $result = (new UploadInspector)->inspect($file);

        $this->assertSame('application/pdf', $result->mimeType);
        $this->assertSame('pdf', $result->extension);
        $this->assertMatchesRegularExpression('#^uploads/\d{4}/\d{2}/[a-z0-9]{40}\.pdf$#', $result->storagePath);
        $this->assertStringNotContainsString('Protokoll', $result->storagePath);
        $this->assertSame('Gemeinderat Protokoll_script_.PDF', $result->originalName);
        $this->assertSame(hash('sha256', self::PDF), $result->sha256);
    }

    public function test_valid_png_is_accepted(): void
    {
        $result = (new UploadInspector)->inspect(UploadedFile::fake()->createWithContent('foto.png', self::PNG));

        $this->assertSame('image/png', $result->mimeType);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejectedFiles(): array
    {
        return [
            'php script' => ['shell.php', '<?php echo 1;'],
            'php disguised as pdf' => ['dokument.pdf', '<?php system($_GET["c"]); ?>'],
            'html disguised as png' => ['bild.png', '<html><script>alert(1)</script></html>'],
            'svg (active content)' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'double extension' => ['bild.php.png', '<?php echo 1;'],
            'png declared as pdf' => ['dokument.pdf', self::PNG],
            'no extension' => ['README', 'text'],
        ];
    }

    #[DataProvider('rejectedFiles')]
    public function test_dangerous_or_mismatching_files_are_rejected(string $name, string $content): void
    {
        $this->expectException(RejectedUpload::class);

        (new UploadInspector)->inspect(UploadedFile::fake()->createWithContent($name, $content));
    }

    public function test_size_limit_is_enforced(): void
    {
        config(['uploads.max_kilobytes' => 1]);

        $this->expectException(RejectedUpload::class);

        (new UploadInspector)->inspect(UploadedFile::fake()->createWithContent('gross.pdf', self::PDF.str_repeat('x', 2048)));
    }
}
