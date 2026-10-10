<?php

namespace Database\Seeders\Demo;

use RuntimeException;
use ZipArchive;

/**
 * Generates small, obviously fictional demo files for the development seeder.
 * Nothing here is used by the application at runtime.
 */
final class DemoFiles
{
    /**
     * A valid single-page PDF (Helvetica, WinAnsi) with a title and text lines.
     *
     * @param  list<string>  $lines
     */
    public static function pdf(string $title, array $lines, int $padToKilobytes = 0): string
    {
        $text = ['BT', '/F1 9 Tf', '56 800 Td', '(MUSTERDOKUMENT - NUR ZUR DEMONSTRATION - KEINE AMTLICHE INFORMATION) Tj', 'ET', 'BT', '/F2 20 Tf', '56 760 Td', '('.self::escape($title).') Tj', 'ET', 'BT', '/F1 11 Tf', '14 TL', '56 728 Td'];
        foreach ($lines as $line) {
            foreach (self::wrap($line, 92) as $part) {
                $text[] = '('.self::escape($part).') Tj T*';
            }
            $text[] = 'T*';
        }
        $text[] = 'ET';
        $stream = implode("\n", $text);
        if ($padToKilobytes > 0) {
            // Comment lines enlarge the file to show realistic size metadata.
            $stream .= "\n".str_repeat("% Musterinhalt zur Demonstration der Dateigroesse.\n", (int) ceil($padToKilobytes * 1024 / 52));
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Title ('.self::escape($title).') /Subject (Demonstrationsdokument) /Creator (DevelopmentDemoSeeder) >>',
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R /Info 7 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    /** Local vector-like raster portrait, intentionally abstract and fictional. */
    public static function portrait(int $variant): string
    {
        $image = imagecreatetruecolor(400, 500);
        $background = (int) imagecolorallocate($image, $variant === 1 ? 220 : 230, 235, 240);
        $skin = (int) imagecolorallocate($image, 223, 177, 145);
        $hair = (int) imagecolorallocate($image, $variant === 1 ? 87 : 119, 81, 65);
        $coat = (int) imagecolorallocate($image, $variant === 1 ? 42 : 63, 99, 130);
        imagefill($image, 0, 0, $background);
        imagefilledellipse($image, 200, 430, 320, 320, $coat);
        imagefilledellipse($image, 200, 178, 166, 204, $hair);
        imagefilledellipse($image, 200, 200, 138, 168, $skin);
        imagefilledrectangle($image, 133, 111, 265, 149, $hair);
        imagefilledellipse($image, 172, 200, 9, 9, $hair);
        imagefilledellipse($image, 227, 200, 9, 9, $hair);
        imagearc($image, 200, 225, 54, 40, 10, 170, $hair);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /**
     * A minimal Word document (Office Open XML) with demo paragraphs.
     *
     * @param  list<string>  $paragraphs
     */
    public static function docx(string $title, array $paragraphs): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required for the DOCX demo file.');
        }
        $path = tempnam(sys_get_temp_dir(), 'demo-docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $body = '';
        foreach (['MUSTERDOKUMENT – NUR ZUR DEMONSTRATION', $title, ...$paragraphs] as $paragraph) {
            $body .= '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($paragraph, ENT_XML1).'</w:t></w:r></w:p>';
        }
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private static function escape(string $text): string
    {
        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return strtr($encoded, ['\\' => '\\\\', '(' => '\(', ')' => '\)']);
    }

    /** @return list<string> */
    private static function wrap(string $line, int $width): array
    {
        return explode("\n", wordwrap($line, $width, "\n", true));
    }
}
