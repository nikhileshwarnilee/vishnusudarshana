<?php

if (!function_exists('vs_event_pdf_resolve_project_path')) {
    function vs_event_pdf_resolve_project_path(string $relativePath, array $allowedRoots = []): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));
        if ($relativePath === '') {
            return '';
        }

        $relativePath = ltrim($relativePath, '/');
        $absolutePath = realpath(__DIR__ . '/../' . $relativePath);
        if ($absolutePath === false || !is_file($absolutePath)) {
            return '';
        }

        if (empty($allowedRoots)) {
            return $absolutePath;
        }

        $absolutePathNorm = str_replace('\\', '/', $absolutePath);
        foreach ($allowedRoots as $allowedRoot) {
            $allowedRoot = trim(str_replace('\\', '/', (string)$allowedRoot), '/');
            if ($allowedRoot === '') {
                continue;
            }

            $allowedAbsolute = realpath(__DIR__ . '/../' . $allowedRoot);
            if ($allowedAbsolute === false) {
                continue;
            }

            $allowedNorm = rtrim(str_replace('\\', '/', $allowedAbsolute), '/');
            if (strpos($absolutePathNorm, $allowedNorm . '/') === 0 || $absolutePathNorm === $allowedNorm) {
                return $absolutePath;
            }
        }

        return '';
    }
}

if (!function_exists('vs_event_pdf_prepare_image_from_path')) {
    function vs_event_pdf_prepare_image_from_path(string $path, int $jpegQuality = 86): array
    {
        $path = trim($path);
        if ($path === '' || !is_file($path)) {
            return [];
        }

        $rawBinary = @file_get_contents($path);
        if (!is_string($rawBinary) || $rawBinary === '') {
            return [];
        }

        $imageInfo = @getimagesizefromstring($rawBinary);
        $image = @imagecreatefromstring($rawBinary);
        if ($image === false || !$imageInfo) {
            return [];
        }

        $width = max((int)($imageInfo[0] ?? 0), 1);
        $height = max((int)($imageInfo[1] ?? 0), 1);
        $maxDimension = 1600;

        if ($width > $maxDimension || $height > $maxDimension) {
            $scale = min($maxDimension / $width, $maxDimension / $height);
            $targetWidth = max((int)round($width * $scale), 1);
            $targetHeight = max((int)round($height * $scale), 1);
        } else {
            $targetWidth = $width;
            $targetHeight = $height;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$canvas) {
            imagedestroy($image);
            return [];
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        ob_start();
        imagejpeg($canvas, null, $jpegQuality);
        $jpegBinary = (string)ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($image);

        if ($jpegBinary === '') {
            return [];
        }

        return [
            'binary' => $jpegBinary,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }
}

if (!function_exists('vs_event_pdf_estimate_text_width')) {
    function vs_event_pdf_estimate_text_width(string $text, float $fontSize, bool $isBold = false): float
    {
        $normalized = vs_event_pdf_normalize_text($text);
        if ($normalized === '') {
            return 0.0;
        }

        $factor = $isBold ? 0.56 : 0.52;
        return strlen($normalized) * $fontSize * $factor;
    }
}

if (!function_exists('vs_event_pdf_wrap_text')) {
    function vs_event_pdf_wrap_text(string $text, float $fontSize, float $maxWidth, bool $isBold = false): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = explode("\n", $text);
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = vs_event_pdf_normalize_text($paragraph);
            if ($paragraph === '') {
                if (!empty($lines)) {
                    $lines[] = '';
                }
                continue;
            }

            $words = preg_split('/\s+/', $paragraph) ?: [];
            $currentLine = '';

            foreach ($words as $word) {
                $word = trim((string)$word);
                if ($word === '') {
                    continue;
                }

                $candidate = $currentLine === '' ? $word : ($currentLine . ' ' . $word);
                if (vs_event_pdf_estimate_text_width($candidate, $fontSize, $isBold) <= $maxWidth) {
                    $currentLine = $candidate;
                    continue;
                }

                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = '';
                }

                if (vs_event_pdf_estimate_text_width($word, $fontSize, $isBold) <= $maxWidth) {
                    $currentLine = $word;
                    continue;
                }

                $maxChars = max((int)floor($maxWidth / max($fontSize * ($isBold ? 0.56 : 0.52), 1)), 1);
                $chunks = str_split($word, $maxChars);
                foreach ($chunks as $chunkIndex => $chunk) {
                    $chunk = trim((string)$chunk);
                    if ($chunk === '') {
                        continue;
                    }
                    if ($chunkIndex === count($chunks) - 1) {
                        $currentLine = $chunk;
                    } else {
                        $lines[] = $chunk;
                    }
                }
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return empty($lines) ? [''] : $lines;
    }
}

if (!function_exists('vs_event_pdf_new_document')) {
    function vs_event_pdf_new_document(): array
    {
        return [
            'pages' => [],
            'images' => [],
            'image_order' => [],
        ];
    }
}

if (!function_exists('vs_event_pdf_add_page')) {
    function vs_event_pdf_add_page(array &$document): int
    {
        $document['pages'][] = [
            'commands' => [],
            'images' => [],
        ];

        return count($document['pages']) - 1;
    }
}

if (!function_exists('vs_event_pdf_register_image')) {
    function vs_event_pdf_register_image(array &$document, array $imageData): string
    {
        if (empty($imageData['binary']) || empty($imageData['width']) || empty($imageData['height'])) {
            return '';
        }

        $imageKey = sha1($imageData['binary']);
        if (!isset($document['images'][$imageKey])) {
            $resourceName = 'Im' . (count($document['image_order']) + 1);
            $document['images'][$imageKey] = [
                'name' => $resourceName,
                'binary' => (string)$imageData['binary'],
                'width' => (int)$imageData['width'],
                'height' => (int)$imageData['height'],
            ];
            $document['image_order'][] = $imageKey;
        }

        return $imageKey;
    }
}

if (!function_exists('vs_event_pdf_color_components')) {
    function vs_event_pdf_color_components(array $rgb): string
    {
        $r = max(min((int)($rgb[0] ?? 0), 255), 0) / 255;
        $g = max(min((int)($rgb[1] ?? 0), 255), 0) / 255;
        $b = max(min((int)($rgb[2] ?? 0), 255), 0) / 255;

        return number_format($r, 3, '.', '') . ' '
            . number_format($g, 3, '.', '') . ' '
            . number_format($b, 3, '.', '');
    }
}

if (!function_exists('vs_event_pdf_add_command')) {
    function vs_event_pdf_add_command(array &$document, int $pageIndex, string $command): void
    {
        $document['pages'][$pageIndex]['commands'][] = $command;
    }
}

if (!function_exists('vs_event_pdf_draw_rect')) {
    function vs_event_pdf_draw_rect(
        array &$document,
        int $pageIndex,
        float $x,
        float $topY,
        float $width,
        float $height,
        ?array $fillColor = null,
        ?array $strokeColor = null,
        float $lineWidth = 1.0
    ): void {
        $pageHeight = 842.0;
        $pdfY = $pageHeight - $topY - $height;

        $parts = ['q'];
        if ($fillColor !== null) {
            $parts[] = vs_event_pdf_color_components($fillColor) . ' rg';
        }
        if ($strokeColor !== null) {
            $parts[] = vs_event_pdf_color_components($strokeColor) . ' RG';
        }
        $parts[] = number_format($lineWidth, 2, '.', '') . ' w';
        $parts[] = number_format($x, 2, '.', '') . ' '
            . number_format($pdfY, 2, '.', '') . ' '
            . number_format($width, 2, '.', '') . ' '
            . number_format($height, 2, '.', '') . ' re';

        if ($fillColor !== null && $strokeColor !== null) {
            $parts[] = 'B';
        } elseif ($fillColor !== null) {
            $parts[] = 'f';
        } else {
            $parts[] = 'S';
        }

        $parts[] = 'Q';
        vs_event_pdf_add_command($document, $pageIndex, implode("\n", $parts));
    }
}

if (!function_exists('vs_event_pdf_draw_text')) {
    function vs_event_pdf_draw_text(
        array &$document,
        int $pageIndex,
        float $x,
        float $topY,
        string $text,
        float $fontSize = 11.0,
        bool $isBold = false,
        array $color = [34, 34, 34],
        string $align = 'left',
        float $maxWidth = 0.0
    ): void {
        $text = vs_event_pdf_normalize_text($text);
        if ($text === '') {
            return;
        }

        if ($maxWidth > 0 && $align !== 'left') {
            $textWidth = vs_event_pdf_estimate_text_width($text, $fontSize, $isBold);
            if ($align === 'center') {
                $x += max(($maxWidth - $textWidth) / 2, 0);
            } elseif ($align === 'right') {
                $x += max($maxWidth - $textWidth, 0);
            }
        }

        $pageHeight = 842.0;
        $pdfY = $pageHeight - $topY - $fontSize;
        $fontName = $isBold ? 'F2' : 'F1';

        $command = implode("\n", [
            'q',
            vs_event_pdf_color_components($color) . ' rg',
            'BT',
            '/' . $fontName . ' ' . number_format($fontSize, 2, '.', '') . ' Tf',
            '1 0 0 1 ' . number_format($x, 2, '.', '') . ' ' . number_format($pdfY, 2, '.', '') . ' Tm',
            '(' . vs_event_pdf_escape_text($text) . ') Tj',
            'ET',
            'Q',
        ]);

        vs_event_pdf_add_command($document, $pageIndex, $command);
    }
}

if (!function_exists('vs_event_pdf_draw_multiline_text')) {
    function vs_event_pdf_draw_multiline_text(
        array &$document,
        int $pageIndex,
        float $x,
        float $topY,
        array $lines,
        float $fontSize = 11.0,
        bool $isBold = false,
        array $color = [34, 34, 34],
        float $lineHeight = 14.0,
        string $align = 'left',
        float $maxWidth = 0.0
    ): float {
        $renderedTopY = $topY;
        foreach ($lines as $index => $line) {
            $line = (string)$line;
            if ($line === '') {
                continue;
            }
            vs_event_pdf_draw_text(
                $document,
                $pageIndex,
                $x,
                $renderedTopY + ($index * $lineHeight),
                $line,
                $fontSize,
                $isBold,
                $color,
                $align,
                $maxWidth
            );
        }

        return $topY + (max(count($lines), 1) * $lineHeight);
    }
}

if (!function_exists('vs_event_pdf_draw_image')) {
    function vs_event_pdf_draw_image(
        array &$document,
        int $pageIndex,
        string $imageKey,
        float $x,
        float $topY,
        float $width,
        float $height
    ): void {
        if ($imageKey === '' || !isset($document['images'][$imageKey])) {
            return;
        }

        $pageHeight = 842.0;
        $pdfY = $pageHeight - $topY - $height;
        $imageName = (string)$document['images'][$imageKey]['name'];
        $document['pages'][$pageIndex]['images'][$imageKey] = true;

        $command = implode("\n", [
            'q',
            number_format($width, 2, '.', '') . ' 0 0 ' . number_format($height, 2, '.', '') . ' '
                . number_format($x, 2, '.', '') . ' ' . number_format($pdfY, 2, '.', '') . ' cm',
            '/' . $imageName . ' Do',
            'Q',
        ]);

        vs_event_pdf_add_command($document, $pageIndex, $command);
    }
}

if (!function_exists('vs_event_pdf_compile_document')) {
    function vs_event_pdf_compile_document(array $document): string
    {
        if (empty($document['pages'])) {
            return '';
        }

        $objects = [];
        $pageObjectNumbers = [];
        $contentObjectNumbers = [];
        $imageObjectNumbers = [];

        $nextObjectNumber = 5;
        foreach ($document['image_order'] as $imageKey) {
            $imageObjectNumbers[$imageKey] = $nextObjectNumber++;
        }

        foreach ($document['pages'] as $pageIndex => $page) {
            $pageObjectNumbers[$pageIndex] = $nextObjectNumber++;
            $contentObjectNumbers[$pageIndex] = $nextObjectNumber++;
        }

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Count " . count($document['pages']) . " /Kids ["
            . implode(' ', array_map(static function (int $objectNumber): string {
                return $objectNumber . ' 0 R';
            }, $pageObjectNumbers))
            . "] >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        foreach ($document['image_order'] as $imageKey) {
            $imageData = $document['images'][$imageKey];
            $objects[$imageObjectNumbers[$imageKey]] =
                "<< /Type /XObject /Subtype /Image /Width " . (int)$imageData['width']
                . " /Height " . (int)$imageData['height']
                . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen((string)$imageData['binary']) . " >>\n"
                . "stream\n" . (string)$imageData['binary'] . "\nendstream";
        }

        foreach ($document['pages'] as $pageIndex => $page) {
            $resourceParts = ['/Font << /F1 3 0 R /F2 4 0 R >>'];

            if (!empty($page['images'])) {
                $xObjects = [];
                foreach (array_keys($page['images']) as $imageKey) {
                    $imageName = (string)$document['images'][$imageKey]['name'];
                    $xObjects[] = '/' . $imageName . ' ' . $imageObjectNumbers[$imageKey] . ' 0 R';
                }
                $resourceParts[] = '/XObject << ' . implode(' ', $xObjects) . ' >>';
            }

            $resources = '<< ' . implode(' ', $resourceParts) . ' >>';
            $contentStream = implode("\n", $page['commands']) . "\n";

            $objects[$contentObjectNumbers[$pageIndex]] =
                "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream";
            $objects[$pageObjectNumbers[$pageIndex]] =
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources " . $resources
                . " /Contents " . $contentObjectNumbers[$pageIndex] . " 0 R >>";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        $maxObject = max(array_keys($objects));

        for ($i = 1; $i <= $maxObject; $i++) {
            if (!isset($objects[$i])) {
                continue;
            }

            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", (int)($offsets[$i] ?? 0));
        }
        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}

if (!function_exists('vs_event_pdf_color_palette')) {
    function vs_event_pdf_color_palette(): array
    {
        return [
            'maroon' => [128, 0, 0],
            'maroon_dark' => [95, 18, 18],
            'cream' => [255, 250, 243],
            'cream_soft' => [252, 246, 238],
            'gold' => [230, 184, 102],
            'text' => [42, 42, 42],
            'muted' => [92, 88, 83],
            'border' => [228, 210, 210],
            'success_bg' => [237, 249, 240],
            'success_border' => [189, 228, 200],
            'success_text' => [20, 101, 58],
            'pending_bg' => [255, 248, 230],
            'pending_border' => [240, 221, 173],
            'pending_text' => [130, 95, 0],
            'danger_bg' => [255, 239, 241],
            'danger_border' => [241, 194, 203],
            'danger_text' => [161, 37, 54],
            'neutral_bg' => [244, 246, 251],
            'neutral_border' => [216, 223, 235],
            'neutral_text' => [72, 85, 102],
            'white' => [255, 255, 255],
        ];
    }
}

if (!function_exists('vs_event_pdf_card_colors')) {
    function vs_event_pdf_card_colors(string $variant): array
    {
        $palette = vs_event_pdf_color_palette();
        switch ($variant) {
            case 'success':
            case 'approved':
                return [
                    'fill' => $palette['success_bg'],
                    'stroke' => $palette['success_border'],
                    'text' => $palette['success_text'],
                ];
            case 'rejected':
            case 'failed':
            case 'danger':
                return [
                    'fill' => $palette['danger_bg'],
                    'stroke' => $palette['danger_border'],
                    'text' => $palette['danger_text'],
                ];
            case 'pending':
                return [
                    'fill' => $palette['pending_bg'],
                    'stroke' => $palette['pending_border'],
                    'text' => $palette['pending_text'],
                ];
            default:
                return [
                    'fill' => $palette['neutral_bg'],
                    'stroke' => $palette['neutral_border'],
                    'text' => $palette['neutral_text'],
                ];
        }
    }
}

if (!function_exists('vs_event_pdf_create_renderer')) {
    function vs_event_pdf_create_renderer(array $context): array
    {
        $document = vs_event_pdf_new_document();

        return [
            'document' => $document,
            'context' => $context,
            'page_index' => -1,
            'cursor_y' => 0.0,
            'margin' => 28.0,
            'page_width' => 595.0,
            'page_height' => 842.0,
            'bottom_limit' => 802.0,
            'page_number' => 0,
        ];
    }
}

if (!function_exists('vs_event_pdf_start_page')) {
    function vs_event_pdf_start_page(array &$renderer): void
    {
        $renderer['page_index'] = vs_event_pdf_add_page($renderer['document']);
        $renderer['page_number']++;

        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $pageIndex = (int)$renderer['page_index'];
        $palette = vs_event_pdf_color_palette();
        $context = $renderer['context'];

        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin,
            $margin,
            $contentWidth,
            86,
            $palette['cream'],
            $palette['border'],
            1.1
        );
        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin,
            $margin,
            $contentWidth,
            8,
            $palette['maroon'],
            $palette['maroon'],
            0.5
        );

        $logoImageKey = (string)($context['images']['logo'] ?? '');
        if ($logoImageKey !== '' && isset($renderer['document']['images'][$logoImageKey])) {
            $logoData = $renderer['document']['images'][$logoImageKey];
            $logoWidth = 168.0;
            $logoHeight = $logoData['height'] > 0
                ? min(42.0, ($logoWidth * ((float)$logoData['height'] / (float)$logoData['width'])))
                : 34.0;
            if ($logoHeight > 0 && $logoData['height'] > 0) {
                $logoWidth = $logoHeight * ((float)$logoData['width'] / (float)$logoData['height']);
            }
            vs_event_pdf_draw_image($renderer['document'], $pageIndex, $logoImageKey, $margin + 16, $margin + 18, $logoWidth, $logoHeight);
        } else {
            vs_event_pdf_draw_text(
                $renderer['document'],
                $pageIndex,
                $margin + 16,
                $margin + 24,
                (string)($context['business_name'] ?? 'Vishnusudarshana Dharmik Sanskar Kendra'),
                16,
                true,
                $palette['maroon_dark']
            );
        }

        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $margin + 220,
            $margin + 22,
            'Event Booking Confirmation',
            18,
            true,
            $palette['maroon_dark']
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $margin + 220,
            $margin + 47,
            (string)($context['message_body'] ?? ''),
            10.5,
            false,
            $palette['muted']
        );

        $metaX = $margin + $contentWidth - 192;
        $metaWidth = 176;
        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $metaX,
            $margin + 16,
            $metaWidth,
            54,
            $palette['white'],
            $palette['border'],
            0.9
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $metaX + 10,
            $margin + 24,
            'Booking Ref',
            8.5,
            true,
            $palette['maroon']
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $metaX + 10,
            $margin + 38,
            (string)($context['booking_reference'] ?? 'N/A'),
            11.5,
            true,
            $palette['text']
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $metaX + 10,
            $margin + 54,
            'Contact: ' . (string)($context['contact_number_display'] ?? 'Not Available'),
            8.8,
            false,
            $palette['muted']
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $metaX + 10,
            $margin + 66,
            'Generated: ' . (string)($context['generated_at'] ?? ''),
            8.8,
            false,
            $palette['muted']
        );

        $renderer['cursor_y'] = $margin + 102;
    }
}

if (!function_exists('vs_event_pdf_ensure_space')) {
    function vs_event_pdf_ensure_space(array &$renderer, float $requiredHeight): void
    {
        if ($renderer['page_index'] < 0) {
            vs_event_pdf_start_page($renderer);
            return;
        }

        if ((float)$renderer['cursor_y'] + $requiredHeight > (float)$renderer['bottom_limit']) {
            vs_event_pdf_start_page($renderer);
        }
    }
}

if (!function_exists('vs_event_pdf_render_status_card')) {
    function vs_event_pdf_render_status_card(
        array &$renderer,
        string $title,
        string $message,
        string $variant
    ): void {
        $colors = vs_event_pdf_card_colors($variant);
        $palette = vs_event_pdf_color_palette();
        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $height = 54.0;

        vs_event_pdf_ensure_space($renderer, $height + 10);

        $pageIndex = (int)$renderer['page_index'];
        $topY = (float)$renderer['cursor_y'];
        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin,
            $topY,
            $contentWidth,
            $height,
            $colors['fill'],
            $colors['stroke'],
            1.0
        );
        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin + 14,
            $topY + 16,
            12,
            12,
            $colors['text'],
            $colors['text'],
            0.5
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $margin + 34,
            $topY + 15,
            $title,
            12.5,
            true,
            $colors['text']
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $margin + 34,
            $topY + 31,
            $message,
            9.6,
            false,
            $palette['text']
        );

        $renderer['cursor_y'] += $height + 10;
    }
}

if (!function_exists('vs_event_pdf_render_hero')) {
    function vs_event_pdf_render_hero(array &$renderer): void
    {
        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $pageIndex = (int)$renderer['page_index'];
        $context = $renderer['context'];
        $palette = vs_event_pdf_color_palette();

        $leftWidth = 334.0;
        $rightWidth = $contentWidth - $leftWidth - 12.0;

        $eventTitleLines = vs_event_pdf_wrap_text((string)($context['event_name'] ?? 'Event'), 18, $leftWidth - 28, true);
        $packageLines = vs_event_pdf_wrap_text((string)($context['package_name'] ?? 'Package'), 11.5, $leftWidth - 28, false);
        $heroHeight = max(192.0, 92.0 + (count($eventTitleLines) * 18.0) + (count($packageLines) * 14.0));

        vs_event_pdf_ensure_space($renderer, $heroHeight + 12);
        $topY = (float)$renderer['cursor_y'];

        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin,
            $topY,
            $contentWidth,
            $heroHeight,
            $palette['cream_soft'],
            $palette['border'],
            1.0
        );

        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin + 14,
            $topY + 14,
            92,
            18,
            [250, 236, 214],
            [230, 184, 102],
            0.8
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $margin + 24,
            $topY + 18,
            'Confirmed Ticket',
            8.5,
            true,
            $palette['maroon_dark']
        );

        $leftX = $margin + 18;
        $textY = $topY + 44;
        vs_event_pdf_draw_multiline_text(
            $renderer['document'],
            $pageIndex,
            $leftX,
            $textY,
            $eventTitleLines,
            18,
            true,
            $palette['maroon_dark'],
            19
        );

        $packageTop = $textY + (count($eventTitleLines) * 19) + 4;
        vs_event_pdf_draw_multiline_text(
            $renderer['document'],
            $pageIndex,
            $leftX,
            $packageTop,
            $packageLines,
            11.5,
            false,
            $palette['muted'],
            14
        );

        $detailRows = [
            'Attendee' => (string)($context['attendee_name'] ?? 'Devotee'),
            'Event Date' => (string)($context['event_date'] ?? ''),
            'Location' => (string)($context['location'] ?? ''),
            'Persons / Qty' => (string)($context['persons'] ?? '1'),
            'Booking Ref' => (string)($context['booking_reference'] ?? 'N/A'),
        ];

        $detailTop = $packageTop + (count($packageLines) * 14) + 12;
        foreach ($detailRows as $label => $value) {
            if (trim($value) === '') {
                continue;
            }
            vs_event_pdf_draw_text(
                $renderer['document'],
                $pageIndex,
                $leftX,
                $detailTop,
                $label . ':',
                8.8,
                true,
                $palette['maroon']
            );
            $valueLines = vs_event_pdf_wrap_text($value, 9.6, $leftWidth - 112, false);
            vs_event_pdf_draw_multiline_text(
                $renderer['document'],
                $pageIndex,
                $leftX + 72,
                $detailTop,
                $valueLines,
                9.6,
                false,
                $palette['text'],
                12
            );
            $detailTop += max(count($valueLines) * 12, 14);
        }

        $qrCardX = $margin + $leftWidth + 12;
        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $qrCardX,
            $topY + 16,
            $rightWidth - 14,
            $heroHeight - 32,
            $palette['white'],
            $palette['border'],
            0.9
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $qrCardX + 16,
            $topY + 28,
            'Entry QR Ticket',
            11.5,
            true,
            $palette['maroon_dark']
        );

        $qrImageKey = (string)($context['images']['qr'] ?? '');
        if ($qrImageKey !== '' && isset($renderer['document']['images'][$qrImageKey])) {
            $qrBoxSize = min($rightWidth - 46, 138.0);
            vs_event_pdf_draw_rect(
                $renderer['document'],
                $pageIndex,
                $qrCardX + 16,
                $topY + 46,
                $qrBoxSize + 12,
                $qrBoxSize + 12,
                $palette['cream'],
                $palette['border'],
                0.8
            );
            vs_event_pdf_draw_image(
                $renderer['document'],
                $pageIndex,
                $qrImageKey,
                $qrCardX + 22,
                $topY + 52,
                $qrBoxSize,
                $qrBoxSize
            );
            vs_event_pdf_draw_text(
                $renderer['document'],
                $pageIndex,
                $qrCardX + 16,
                $topY + 52 + $qrBoxSize + 18,
                'Show this QR at event entry.',
                8.8,
                false,
                $palette['muted']
            );
        } else {
            vs_event_pdf_draw_text(
                $renderer['document'],
                $pageIndex,
                $qrCardX + 16,
                $topY + 74,
                'QR will be verified',
                10.0,
                true,
                $palette['maroon']
            );
            vs_event_pdf_draw_text(
                $renderer['document'],
                $pageIndex,
                $qrCardX + 16,
                $topY + 92,
                'using your booking',
                9.4,
                false,
                $palette['muted']
            );
            vs_event_pdf_draw_text(
                $renderer['document'],
                $pageIndex,
                $qrCardX + 16,
                $topY + 106,
                'reference if needed.',
                9.4,
                false,
                $palette['muted']
            );
        }

        $renderer['cursor_y'] += $heroHeight + 12;
    }
}

if (!function_exists('vs_event_pdf_measure_info_cell_height')) {
    function vs_event_pdf_measure_info_cell_height(array $cell, float $cellWidth): float
    {
        $label = (string)($cell['label'] ?? '');
        $value = (string)($cell['value'] ?? '');
        $labelLines = vs_event_pdf_wrap_text($label, 8.8, $cellWidth - 18, true);
        $valueLines = vs_event_pdf_wrap_text($value, 10.0, $cellWidth - 18, false);

        return 12 + (count($labelLines) * 11) + 4 + (count($valueLines) * 12) + 12;
    }
}

if (!function_exists('vs_event_pdf_render_info_cell')) {
    function vs_event_pdf_render_info_cell(
        array &$renderer,
        float $x,
        float $topY,
        float $width,
        float $height,
        array $cell
    ): void {
        $pageIndex = (int)$renderer['page_index'];
        $palette = vs_event_pdf_color_palette();
        $label = (string)($cell['label'] ?? '');
        $value = (string)($cell['value'] ?? '');
        $labelLines = vs_event_pdf_wrap_text($label, 8.8, $width - 18, true);
        $valueLines = vs_event_pdf_wrap_text($value, 10.0, $width - 18, false);

        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $x,
            $topY,
            $width,
            $height,
            $palette['white'],
            $palette['border'],
            0.8
        );

        $labelBottom = vs_event_pdf_draw_multiline_text(
            $renderer['document'],
            $pageIndex,
            $x + 10,
            $topY + 11,
            $labelLines,
            8.8,
            true,
            $palette['maroon'],
            11
        );
        vs_event_pdf_draw_multiline_text(
            $renderer['document'],
            $pageIndex,
            $x + 10,
            $labelBottom + 3,
            $valueLines,
            10.0,
            false,
            $palette['text'],
            12
        );
    }
}

if (!function_exists('vs_event_pdf_render_section_title')) {
    function vs_event_pdf_render_section_title(array &$renderer, string $title): void
    {
        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $pageIndex = (int)$renderer['page_index'];
        $palette = vs_event_pdf_color_palette();

        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin,
            (float)$renderer['cursor_y'],
            $contentWidth,
            24,
            $palette['maroon'],
            $palette['maroon'],
            0.8
        );
        vs_event_pdf_draw_text(
            $renderer['document'],
            $pageIndex,
            $margin + 12,
            (float)$renderer['cursor_y'] + 6,
            $title,
            11.0,
            true,
            $palette['white']
        );
        $renderer['cursor_y'] += 30;
    }
}

if (!function_exists('vs_event_pdf_render_two_col_section')) {
    function vs_event_pdf_render_two_col_section(array &$renderer, string $title, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $columnGap = 10.0;
        $halfWidth = ($contentWidth - $columnGap) / 2;

        vs_event_pdf_ensure_space($renderer, 40);
        vs_event_pdf_render_section_title($renderer, $title);

        foreach ($rows as $rowCells) {
            $rowCells = array_values(array_filter($rowCells, static function ($cell): bool {
                return is_array($cell) && trim((string)($cell['label'] ?? '')) !== '';
            }));

            if (empty($rowCells)) {
                continue;
            }

            $cellWidth = count($rowCells) === 1 ? $contentWidth : $halfWidth;
            $rowHeight = 0.0;
            foreach ($rowCells as $cell) {
                $rowHeight = max($rowHeight, vs_event_pdf_measure_info_cell_height($cell, $cellWidth));
            }
            $rowHeight = max($rowHeight, 44.0);

            if ((float)$renderer['cursor_y'] + $rowHeight > (float)$renderer['bottom_limit']) {
                vs_event_pdf_start_page($renderer);
                vs_event_pdf_render_section_title($renderer, $title);
            }

            $topY = (float)$renderer['cursor_y'];
            if (count($rowCells) === 1) {
                vs_event_pdf_render_info_cell($renderer, $margin, $topY, $contentWidth, $rowHeight, $rowCells[0]);
            } else {
                vs_event_pdf_render_info_cell($renderer, $margin, $topY, $halfWidth, $rowHeight, $rowCells[0]);
                vs_event_pdf_render_info_cell($renderer, $margin + $halfWidth + $columnGap, $topY, $halfWidth, $rowHeight, $rowCells[1]);
            }

            $renderer['cursor_y'] += $rowHeight + 8;
        }
    }
}

if (!function_exists('vs_event_pdf_render_list_section')) {
    function vs_event_pdf_render_list_section(array &$renderer, string $title, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $palette = vs_event_pdf_color_palette();

        vs_event_pdf_ensure_space($renderer, 40);
        vs_event_pdf_render_section_title($renderer, $title);

        foreach ($rows as $row) {
            $label = trim((string)($row['label'] ?? ''));
            $value = trim((string)($row['value'] ?? ''));
            if ($label === '') {
                continue;
            }

            $labelLines = vs_event_pdf_wrap_text($label, 8.8, $contentWidth - 18, true);
            $valueLines = vs_event_pdf_wrap_text($value !== '' ? $value : '-', 10.0, $contentWidth - 18, false);
            $height = 12 + (count($labelLines) * 11) + 4 + (count($valueLines) * 12) + 12;
            $height = max($height, 42.0);

            if ((float)$renderer['cursor_y'] + $height > (float)$renderer['bottom_limit']) {
                vs_event_pdf_start_page($renderer);
                vs_event_pdf_render_section_title($renderer, $title);
            }

            $pageIndex = (int)$renderer['page_index'];
            $topY = (float)$renderer['cursor_y'];
            vs_event_pdf_draw_rect(
                $renderer['document'],
                $pageIndex,
                $margin,
                $topY,
                $contentWidth,
                $height,
                $palette['white'],
                $palette['border'],
                0.8
            );
            $labelBottom = vs_event_pdf_draw_multiline_text(
                $renderer['document'],
                $pageIndex,
                $margin + 10,
                $topY + 11,
                $labelLines,
                8.8,
                true,
                $palette['maroon'],
                11
            );
            vs_event_pdf_draw_multiline_text(
                $renderer['document'],
                $pageIndex,
                $margin + 10,
                $labelBottom + 3,
                $valueLines,
                10.0,
                false,
                $palette['text'],
                12
            );

            $renderer['cursor_y'] += $height + 8;
        }
    }
}

if (!function_exists('vs_event_pdf_render_image_section')) {
    function vs_event_pdf_render_image_section(array &$renderer, string $title, string $imageKey, string $caption = ''): void
    {
        if ($imageKey === '' || !isset($renderer['document']['images'][$imageKey])) {
            return;
        }

        $imageData = $renderer['document']['images'][$imageKey];
        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $availableWidth = $contentWidth - 24;
        $availableHeight = 260.0;

        $scale = min(
            $availableWidth / max((float)$imageData['width'], 1.0),
            $availableHeight / max((float)$imageData['height'], 1.0),
            1.0
        );
        $imageWidth = max((float)$imageData['width'] * $scale, 60.0);
        $imageHeight = max((float)$imageData['height'] * $scale, 60.0);

        $sectionHeight = 30 + $imageHeight + ($caption !== '' ? 24 : 10) + 20;
        vs_event_pdf_ensure_space($renderer, $sectionHeight);
        vs_event_pdf_render_section_title($renderer, $title);

        $topY = (float)$renderer['cursor_y'];
        $pageIndex = (int)$renderer['page_index'];
        $palette = vs_event_pdf_color_palette();

        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin,
            $topY,
            $contentWidth,
            $imageHeight + ($caption !== '' ? 34 : 18),
            $palette['white'],
            $palette['border'],
            0.8
        );

        vs_event_pdf_draw_image(
            $renderer['document'],
            $pageIndex,
            $imageKey,
            $margin + (($contentWidth - $imageWidth) / 2),
            $topY + 10,
            $imageWidth,
            $imageHeight
        );

        if ($caption !== '') {
            vs_event_pdf_draw_text(
                $renderer['document'],
                $pageIndex,
                $margin + 10,
                $topY + $imageHeight + 14,
                $caption,
                9.2,
                false,
                $palette['muted'],
                'center',
                $contentWidth - 20
            );
        }

        $renderer['cursor_y'] += $imageHeight + ($caption !== '' ? 44 : 28);
    }
}

if (!function_exists('vs_event_pdf_render_footer_note')) {
    function vs_event_pdf_render_footer_note(array &$renderer, array $lines): void
    {
        $lines = array_values(array_filter(array_map('trim', $lines), static function (string $line): bool {
            return $line !== '';
        }));
        if (empty($lines)) {
            return;
        }

        $margin = (float)$renderer['margin'];
        $contentWidth = (float)$renderer['page_width'] - ($margin * 2);
        $palette = vs_event_pdf_color_palette();
        $wrapped = [];
        foreach ($lines as $line) {
            $wrapped = array_merge($wrapped, vs_event_pdf_wrap_text($line, 9.0, $contentWidth - 20, false));
        }

        $height = 14 + (count($wrapped) * 11) + 10;
        vs_event_pdf_ensure_space($renderer, $height + 4);

        $pageIndex = (int)$renderer['page_index'];
        $topY = (float)$renderer['cursor_y'];
        vs_event_pdf_draw_rect(
            $renderer['document'],
            $pageIndex,
            $margin,
            $topY,
            $contentWidth,
            $height,
            $palette['cream'],
            $palette['border'],
            0.8
        );
        vs_event_pdf_draw_multiline_text(
            $renderer['document'],
            $pageIndex,
            $margin + 10,
            $topY + 10,
            $wrapped,
            9.0,
            false,
            $palette['muted'],
            11
        );

        $renderer['cursor_y'] += $height + 4;
    }
}

if (!function_exists('vs_event_pdf_registration_value')) {
    function vs_event_pdf_registration_value(array $row): string
    {
        $value = trim((string)($row['value'] ?? ''));
        if ($value === '') {
            return '-';
        }

        if (preg_match('/^uploads\//', $value)) {
            return 'Uploaded file: ' . basename($value);
        }

        return $value;
    }
}

if (!function_exists('vs_event_build_registration_ticket_context')) {
    function vs_event_build_registration_ticket_context(PDO $pdo, int $registrationId, array $overrides = []): array
    {
        if ($registrationId <= 0) {
            return [];
        }

        if (is_file(__DIR__ . '/../config/whatsapp_config.php')) {
            require_once __DIR__ . '/../config/whatsapp_config.php';
        }
        if (is_file(__DIR__ . '/../config/admin_config.php')) {
            require_once __DIR__ . '/../config/admin_config.php';
        }

        $stmt = $pdo->prepare("SELECT
            r.*,
            r.booking_reference,
            e.title AS event_title,
            COALESCE(d.event_date, e.event_date) AS selected_event_date,
            e.location,
            p.package_name,
            COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
            ep.payment_method,
            ep.transaction_id,
            ep.screenshot,
            ep.remarks,
            ep.status AS payment_record_status,
            ep.amount AS payment_amount,
            ep.amount_paid,
            ep.remaining_amount,
            ep.payment_type
        FROM event_registrations r
        INNER JOIN events e ON e.id = r.event_id
        LEFT JOIN event_dates d ON d.id = r.event_date_id
        INNER JOIN event_packages p ON p.id = r.package_id
        LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
        LEFT JOIN event_payments ep ON ep.registration_id = r.id
        WHERE r.id = ?
        LIMIT 1");
        $stmt->execute([$registrationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }

        if (strtolower((string)($row['payment_status'] ?? '')) !== 'paid') {
            return [];
        }

        $bookingReference = trim((string)($overrides['booking_reference'] ?? $row['booking_reference'] ?? ''));
        if ($bookingReference === '') {
            $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
            if ($bookingReference !== '') {
                $row['booking_reference'] = $bookingReference;
            }
        }
        if ($bookingReference === '') {
            return [];
        }

        $cancelInfoStmt = $pdo->prepare("SELECT * FROM event_cancellations WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
        $cancelInfoStmt->execute([$registrationId]);
        $cancelInfo = $cancelInfoStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $cancelRequestInfoStmt = $pdo->prepare("SELECT * FROM event_cancellation_requests WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
        $cancelRequestInfoStmt->execute([$registrationId]);
        $cancelRequestInfo = $cancelRequestInfoStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $registrationDataStmt = $pdo->prepare("SELECT field_name, value FROM event_registration_data WHERE registration_id = ? ORDER BY id ASC");
        $registrationDataStmt->execute([$registrationId]);
        $registrationDataRows = $registrationDataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $registrationDataRows = array_values(array_filter($registrationDataRows, static function (array $item): bool {
            $name = (string)($item['field_name'] ?? '');
            return strpos($name, '__event_reminder_') !== 0;
        }));

        $displayEventDate = trim((string)($overrides['event_date'] ?? ''));
        if ($displayEventDate === '') {
            $displayEventDate = vs_event_get_registration_date_display($pdo, $row, (string)($row['selected_event_date'] ?? ''));
        }

        $totalAmount = (float)$row['package_price_total'] * max((int)$row['persons'], 1);
        $amountPaid = (float)($row['amount_paid'] ?? 0);
        if (strtolower((string)($row['payment_status'] ?? '')) === 'paid' && $amountPaid <= 0) {
            $amountPaid = (float)($row['payment_amount'] ?? $totalAmount);
        }
        $remainingAmount = (float)($row['remaining_amount'] ?? 0);
        if ($remainingAmount <= 0 && strtolower((string)($row['payment_status'] ?? '')) !== 'cancelled') {
            $remainingAmount = round(max($totalAmount - $amountPaid, 0), 2);
        }

        if (is_array($cancelInfo)) {
            $cancelInfo['display_refund_amount'] = vs_event_resolve_refund_amount(array_merge($cancelInfo, [
                'payment_status' => (string)($row['payment_status'] ?? ''),
                'verification_status' => (string)($row['verification_status'] ?? ''),
                'paid_amount' => $amountPaid,
            ]));
        }

        $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
        $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
        $paymentMethod = strtolower(trim((string)($row['payment_method'] ?? '')));
        $paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));

        $isWaitlisted = vs_event_is_waitlisted_registration($row);
        $waitlistPosition = $isWaitlisted ? vs_event_get_waitlist_position($pdo, $registrationId) : 0;
        $isCancelled = (!$isWaitlisted && ($paymentStatus === 'cancelled' || $verificationStatus === 'cancelled'));
        $isManualOrCash = in_array($paymentMethod, ['manual upi', 'cash'], true);
        $isPendingManualApproval = $isManualOrCash && (
            in_array($paymentStatus, ['pending', 'pending verification'], true) ||
            in_array($verificationStatus, ['pending', 'pending verification'], true) ||
            in_array($paymentRecordStatus, ['pending', 'pending verification'], true)
        );
        $isRejectedByAdmin = in_array($verificationStatus, ['rejected'], true) || in_array($paymentRecordStatus, ['rejected'], true);

        $submittedManualAmount = round(max((float)($row['payment_amount'] ?? 0), 0), 2);
        $displayAmountPaid = round(max($amountPaid, 0), 2);
        if ($displayAmountPaid > $totalAmount && $totalAmount > 0) {
            $displayAmountPaid = $totalAmount;
        }
        $displayRemainingAmount = round(max((float)$remainingAmount, 0), 2);
        $paymentAmountNote = '';

        if (!$isCancelled && $isPendingManualApproval && !$isRejectedByAdmin && $submittedManualAmount > 0) {
            $displayAmountPaid = round(min($totalAmount, $amountPaid + $submittedManualAmount), 2);
            $displayRemainingAmount = round(max($totalAmount - $displayAmountPaid, 0), 2);
            $paymentAmountNote = 'Payment submission of Rs. '
                . number_format($submittedManualAmount, 0, '.', '')
                . ' is pending verification and will be treated as confirmed paid only after approval.';
        } elseif (!$isCancelled && $isManualOrCash && $isRejectedByAdmin) {
            $displayRemainingAmount = round(max($totalAmount - $displayAmountPaid, 0), 2);
            $paymentAmountNote = $displayAmountPaid <= 0
                ? 'Previous payment verification was rejected. Paid amount is Rs. 0 now. Please pay again.'
                : 'Previous payment verification was rejected. Only verified amounts are counted as paid.';
        }

        $paymentHighlightState = 'approved';
        $paymentHighlightTitle = 'Payment Verified';
        $paymentHighlightMessage = 'Payment is verified and counted as confirmed paid amount.';
        if ($isCancelled) {
            $paymentHighlightState = 'neutral';
            $paymentHighlightTitle = 'Booking Cancelled';
            $paymentHighlightMessage = 'Payment updates are locked for cancelled bookings.';
        } elseif ($isWaitlisted) {
            $paymentHighlightState = 'neutral';
            $paymentHighlightTitle = 'Waitlisted Booking';
            $paymentHighlightMessage = $waitlistPosition > 0
                ? ('Current waitlist position: #' . $waitlistPosition . '. Payment will open after confirmation.')
                : 'Payment options are disabled until this waitlist booking is confirmed.';
        } elseif ($isPendingManualApproval && !$isRejectedByAdmin) {
            $paymentHighlightState = 'pending';
            $paymentHighlightTitle = 'Under Verification';
            $paymentHighlightMessage = 'Payment details are submitted and waiting for admin verification.';
        } elseif ($isRejectedByAdmin) {
            $paymentHighlightState = 'rejected';
            $paymentHighlightTitle = 'Verification Rejected';
            $paymentHighlightMessage = 'Last submitted payment was rejected. Please submit a new payment.';
        }

        $messageTitle = 'Payment Successful';
        $messageBody = 'Your event booking is confirmed. Please keep this booking reference for entry and support.';
        $statusClass = 'success';
        if ($isCancelled) {
            $messageTitle = 'Booking Cancelled';
            $messageBody = 'This booking has been cancelled.';
            $statusClass = 'failed';
        } elseif ($isWaitlisted) {
            $messageTitle = 'Waitlist Joined';
            $messageBody = $waitlistPosition > 0
                ? ('Your booking is waitlisted. Current waitlist position: #' . $waitlistPosition . '.')
                : 'Your booking is waitlisted. You will be notified when a seat is available.';
            $statusClass = 'pending';
        }

        $businessWhatsappRaw = defined('WHATSAPP_BUSINESS_PHONE') ? trim((string)WHATSAPP_BUSINESS_PHONE) : '';
        $adminWhatsappRaw = defined('ADMIN_WHATSAPP') ? trim((string)ADMIN_WHATSAPP) : '';
        $contactNumberRaw = $businessWhatsappRaw !== '' ? $businessWhatsappRaw : $adminWhatsappRaw;
        $contactNumberRaw = preg_replace('/[^0-9]/', '', (string)$contactNumberRaw);
        if (strlen($contactNumberRaw) === 10) {
            $contactNumberRaw = '91' . $contactNumberRaw;
        }
        $contactNumberDisplay = $contactNumberRaw !== '' ? ('+' . $contactNumberRaw) : 'Not Available';

        $qrRelativePath = trim((string)($row['qr_code_path'] ?? ''));
        if ($qrRelativePath === '') {
            $qrRelativePath = vs_event_ensure_registration_qr($pdo, $registrationId);
        } else {
            vs_event_ensure_registration_qr($pdo, $registrationId);
        }

        $registrationFieldRows = [];
        foreach ($registrationDataRows as $dataRow) {
            $registrationFieldRows[] = [
                'label' => (string)($dataRow['field_name'] ?? 'Field'),
                'value' => vs_event_pdf_registration_value($dataRow),
            ];
        }

        $summaryRows = [
            [
                ['label' => 'Registration ID', 'value' => (string)$row['id']],
                ['label' => 'Name', 'value' => (string)($overrides['name'] ?? $row['name'] ?? 'Devotee')],
            ],
            [
                ['label' => 'Phone', 'value' => (string)($row['phone'] ?? '-')],
                ['label' => 'Persons / Qty', 'value' => (string)max((int)($row['persons'] ?? 1), 1)],
            ],
            [
                ['label' => 'Event', 'value' => (string)($overrides['event_name'] ?? $row['event_title'] ?? 'Event')],
                ['label' => 'Package', 'value' => (string)($overrides['package_name'] ?? $row['package_name'] ?? 'Package')],
            ],
            [
                ['label' => 'Event Date', 'value' => $displayEventDate],
                ['label' => 'Location', 'value' => (string)($row['location'] ?? '-')],
            ],
            [
                ['label' => 'Payment Status', 'value' => (string)($row['payment_status'] ?? '-')],
                ['label' => 'Verification', 'value' => (string)($row['verification_status'] ?? '-')],
            ],
            [
                ['label' => 'Payment Method', 'value' => (string)($row['payment_method'] ?? '-')],
                ['label' => 'Payment Type', 'value' => (string)($row['payment_type'] ?? '-')],
            ],
            [
                ['label' => 'Transaction ID', 'value' => (string)($row['transaction_id'] ?? '-')],
                ['label' => 'Payment Record', 'value' => (string)($row['payment_record_status'] ?? '-')],
            ],
            [
                ['label' => 'Total Amount', 'value' => 'Rs. ' . number_format($totalAmount, 0, '.', '')],
                ['label' => 'Paid Amount', 'value' => 'Rs. ' . number_format($displayAmountPaid, 0, '.', '')],
            ],
            [
                ['label' => 'Remaining Amount', 'value' => 'Rs. ' . number_format($displayRemainingAmount, 0, '.', '')],
                ['label' => 'Booked At', 'value' => (string)($row['created_at'] ?? '-')],
            ],
        ];
        if ($paymentAmountNote !== '') {
            $summaryRows[] = [
                ['label' => 'Payment Note', 'value' => $paymentAmountNote],
            ];
        }
        $remarks = trim((string)($row['remarks'] ?? ''));
        if ($remarks !== '') {
            $summaryRows[] = [
                ['label' => 'Payment Remark', 'value' => $remarks],
            ];
        }

        $cancellationRows = [];
        if ($cancelInfo && is_array($cancelInfo)) {
            $cancellationRows = [
                ['label' => 'Type', 'value' => (string)($cancelInfo['cancellation_type'] ?? 'full')],
                ['label' => 'Cancelled Persons', 'value' => (string)($cancelInfo['cancelled_persons'] ?? 0)],
                ['label' => 'Refund Amount', 'value' => 'Rs. ' . number_format((float)($cancelInfo['display_refund_amount'] ?? $cancelInfo['refund_amount'] ?? 0), 0, '.', '')],
                ['label' => 'Refund Status', 'value' => (string)($cancelInfo['refund_status'] ?? '-')],
                ['label' => 'Reason', 'value' => (string)($cancelInfo['cancel_reason'] ?? '-')],
            ];
        }

        $cancellationRequestRows = [];
        if ($cancelRequestInfo && is_array($cancelRequestInfo) && strtolower(trim((string)($cancelRequestInfo['request_status'] ?? ''))) !== 'approved') {
            $cancellationRequestRows = [
                ['label' => 'Type', 'value' => (string)($cancelRequestInfo['request_type'] ?? 'full')],
                ['label' => 'Requested Persons', 'value' => (string)($cancelRequestInfo['requested_persons'] ?? 0)],
                ['label' => 'Status', 'value' => (string)($cancelRequestInfo['request_status'] ?? 'pending')],
                ['label' => 'Requested At', 'value' => (string)($cancelRequestInfo['requested_at'] ?? '-')],
                ['label' => 'Reason', 'value' => (string)($cancelRequestInfo['cancel_reason'] ?? '-')],
            ];
            if (!empty($cancelRequestInfo['decision_note'])) {
                $cancellationRequestRows[] = [
                    'label' => 'Decision Note',
                    'value' => (string)$cancelRequestInfo['decision_note'],
                ];
            }
        }

        $quickFacts = [
            'attendee_name' => (string)($overrides['name'] ?? $row['name'] ?? 'Devotee'),
            'event_name' => (string)($overrides['event_name'] ?? $row['event_title'] ?? 'Event'),
            'package_name' => (string)($overrides['package_name'] ?? $row['package_name'] ?? 'Package'),
            'event_date' => $displayEventDate,
            'location' => (string)($row['location'] ?? ''),
            'persons' => (string)max((int)($row['persons'] ?? 1), 1),
        ];

        $proofRelativePath = trim((string)($row['screenshot'] ?? ''));
        $hashPayload = [
            'layout_version' => 'event_ticket_pdf_v2',
            'booking_reference' => $bookingReference,
            'message_title' => $messageTitle,
            'message_body' => $messageBody,
            'status_class' => $statusClass,
            'payment_highlight_state' => $paymentHighlightState,
            'payment_highlight_title' => $paymentHighlightTitle,
            'payment_highlight_message' => $paymentHighlightMessage,
            'quick_facts' => $quickFacts,
            'summary_rows' => $summaryRows,
            'registration_fields' => $registrationFieldRows,
            'cancellation_rows' => $cancellationRows,
            'cancellation_request_rows' => $cancellationRequestRows,
            'proof_relative_path' => $proofRelativePath,
        ];

        return array_merge($quickFacts, [
            'layout_version' => 'event_ticket_pdf_v2',
            'registration_id' => (int)$row['id'],
            'booking_reference' => $bookingReference,
            'message_title' => $messageTitle,
            'message_body' => $messageBody,
            'status_class' => $statusClass,
            'payment_highlight_state' => $paymentHighlightState,
            'payment_highlight_title' => $paymentHighlightTitle,
            'payment_highlight_message' => $paymentHighlightMessage,
            'contact_number_display' => $contactNumberDisplay,
            'business_name' => 'Vishnusudarshana Dharmik Sanskar Kendra',
            'generated_at' => date('d M Y, h:i A'),
            'logo_relative_path' => 'assets/images/logo/logomain.png',
            'qr_relative_path' => $qrRelativePath,
            'proof_relative_path' => $proofRelativePath,
            'summary_rows' => $summaryRows,
            'registration_fields' => $registrationFieldRows,
            'cancellation_rows' => $cancellationRows,
            'cancellation_request_rows' => $cancellationRequestRows,
            'footer_lines' => [
                'This is a system-generated event booking acknowledgment.',
                'Please keep this PDF available on your phone and present the QR code at event entry.',
            ],
            'hash_payload' => $hashPayload,
        ]);
    }
}

if (!function_exists('vs_event_build_ticket_pdf_binary')) {
    function vs_event_build_ticket_pdf_binary(array $ticketData, string $qrJpegBinary = ''): string
    {
        $renderer = vs_event_pdf_create_renderer($ticketData);

        $logoPath = vs_event_pdf_resolve_project_path((string)($ticketData['logo_relative_path'] ?? ''), ['assets/images/logo']);
        $qrPath = vs_event_pdf_resolve_project_path((string)($ticketData['qr_relative_path'] ?? ''), ['uploads/events/qrcodes']);
        $proofPath = vs_event_pdf_resolve_project_path((string)($ticketData['proof_relative_path'] ?? ''), ['uploads/events/payments', 'uploads/events']);

        $logoKey = '';
        $qrKey = '';
        $proofKey = '';

        if ($logoPath !== '') {
            $logoKey = vs_event_pdf_register_image($renderer['document'], vs_event_pdf_prepare_image_from_path($logoPath, 88));
        }
        if ($qrPath !== '') {
            $qrKey = vs_event_pdf_register_image($renderer['document'], vs_event_pdf_prepare_image_from_path($qrPath, 90));
        }
        if ($proofPath !== '') {
            $proofKey = vs_event_pdf_register_image($renderer['document'], vs_event_pdf_prepare_image_from_path($proofPath, 84));
        }

        $renderer['context']['images'] = [
            'logo' => $logoKey,
            'qr' => $qrKey,
            'proof' => $proofKey,
        ];

        vs_event_pdf_start_page($renderer);
        vs_event_pdf_render_status_card($renderer, (string)($ticketData['message_title'] ?? 'Booking Confirmed'), (string)($ticketData['message_body'] ?? ''), (string)($ticketData['status_class'] ?? 'success'));
        vs_event_pdf_render_status_card($renderer, (string)($ticketData['payment_highlight_title'] ?? 'Payment Verified'), (string)($ticketData['payment_highlight_message'] ?? ''), (string)($ticketData['payment_highlight_state'] ?? 'approved'));
        vs_event_pdf_render_hero($renderer);
        vs_event_pdf_render_two_col_section($renderer, 'Booking, Event & Payment Summary', isset($ticketData['summary_rows']) && is_array($ticketData['summary_rows']) ? $ticketData['summary_rows'] : []);
        vs_event_pdf_render_list_section($renderer, 'Registration Form Data', isset($ticketData['registration_fields']) && is_array($ticketData['registration_fields']) ? $ticketData['registration_fields'] : []);
        vs_event_pdf_render_list_section($renderer, 'Cancellation / Refund', isset($ticketData['cancellation_rows']) && is_array($ticketData['cancellation_rows']) ? $ticketData['cancellation_rows'] : []);
        vs_event_pdf_render_list_section($renderer, 'Cancellation Request', isset($ticketData['cancellation_request_rows']) && is_array($ticketData['cancellation_request_rows']) ? $ticketData['cancellation_request_rows'] : []);
        vs_event_pdf_render_image_section($renderer, 'Payment Proof', $proofKey, 'Stored payment proof linked with this booking.');
        vs_event_pdf_render_footer_note($renderer, isset($ticketData['footer_lines']) && is_array($ticketData['footer_lines']) ? $ticketData['footer_lines'] : []);

        return vs_event_pdf_compile_document($renderer['document']);
    }
}

if (!function_exists('vs_event_ensure_registration_ticket_pdf')) {
    function vs_event_ensure_registration_ticket_pdf(PDO $pdo, int $registrationId, array $overrides = []): string
    {
        if ($registrationId <= 0) {
            return '';
        }

        $ticketData = vs_event_build_registration_ticket_context($pdo, $registrationId, $overrides);
        if (empty($ticketData)) {
            return '';
        }

        $hashSource = json_encode((array)($ticketData['hash_payload'] ?? $ticketData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($hashSource) || $hashSource === '') {
            return '';
        }

        $secret = (string)(getenv('EVENT_QR_SECRET') ?: getenv('APP_KEY') ?: 'vs_event_ticket_pdf_secret');
        $fileHash = substr(sha1($registrationId . '|' . $hashSource . '|' . $secret), 0, 24);
        $relativeDir = 'uploads/events/tickets';
        $relativePath = $relativeDir . '/ticket_' . $fileHash . '.pdf';
        $targetDir = __DIR__ . '/../' . $relativeDir;
        $targetPath = $targetDir . '/ticket_' . $fileHash . '.pdf';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return '';
        }

        if (is_file($targetPath) && filesize($targetPath) > 0) {
            return $relativePath;
        }

        $pdfBinary = vs_event_build_ticket_pdf_binary($ticketData);
        if ($pdfBinary === '') {
            return '';
        }

        $writeOk = @file_put_contents($targetPath, $pdfBinary);
        if ($writeOk === false || !is_file($targetPath)) {
            return '';
        }

        return $relativePath;
    }
}
