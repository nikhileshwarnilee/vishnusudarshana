<?php
declare(strict_types=1);

if (!function_exists('vs_settings_ensure_table')) {
    function vs_settings_ensure_table(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS system_settings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(120) NOT NULL,
                setting_value TEXT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('vs_settings_get_value')) {
    function vs_settings_get_value(PDO $pdo, string $key): ?string
    {
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }
}

if (!function_exists('vs_settings_upsert')) {
    function vs_settings_upsert(PDO $pdo, string $key, string $value): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
    }
}

if (!function_exists('vs_settings_marquee_definitions')) {
    function vs_settings_marquee_definitions(): array
    {
        return [
            'home_page_marquee_html' => [
                'label' => 'Home Page Marquee',
                'description' => 'Shown on the public home page above the main cards.',
                'default' => 'Go to the Services section menu to book a token for an in-person visit, schedule online appointments, and explore other related services. | प्रत्यक्ष भेटीसाठी टोकन बुक करण्यासाठी, ऑनलाइन अपॉइंटमेंट ठरवण्यासाठी आणि इतर संबंधित सेवांचा वापर करण्यासाठी Services विभागातील मेनूला भेट द्या. | प्रत्यक्ष मुलाक़ात के लिए टोकन बुक करने, ऑनलाइन अपॉइंटमेंट तय करने और अन्य संबंधित सेवाओं के लिए Services सेक्शन मेनू पर जाएँ। | ప్రత్యక్ష సందర్శన కోసం టోకెన్ బుక్ చేయడానికి, ఆన్‌లైన్ అపాయింట్‌మెంట్‌లను షెడ్యూల్ చేయడానికి మరియు ఇతర సంబంధిత సేవలను పొందడానికి Services విభాగం మెనూను సందర్శించండి. | ನೇರ ಭೇಟಿಗಾಗಿ ಟೋಕನ್ ಬುಕ್ ಮಾಡಲು, ಆನ್‌ಲೈನ್ ಅಪಾಯಿಂಟ್‌ಮೆಂಟ್‌ಗಳನ್ನು ನಿಗದಿಪಡಿಸಲು ಮತ್ತು ಇತರ ಸಂಬಂಧಿತ ಸೇವೆಗಳನ್ನು ಪಡೆಯಲು Services ವಿಭಾಗದ ಮೆನುವಿಗೆ ಹೋಗಿ. | રૂબરૂ મુલાકાત માટે ટોકન બુક કરવા, ઑનલાઇન અપૉઇન્ટમેન્ટ નક્કી કરવા અને અન્ય સંબંધિત સેવાઓ માટે Services વિભાગના મેનુ પર જાઓ.',
            ],
            'services_page_marquee_html' => [
                'label' => 'Online Services Marquee',
                'description' => 'Shown on the public Online Services page.',
                'default' => 'For in-person office token services, visit <a href="offlineservices.php">Offline Services</a> page.',
            ],
            'offline_services_page_marquee_html' => [
                'label' => 'Offline Services Marquee',
                'description' => 'Shown on the public Offline Services page.',
                'default' => 'For online appointments and digital services, visit <a href="services.php">Online Services</a> page.',
            ],
        ];
    }
}

if (!function_exists('vs_settings_limit_text')) {
    function vs_settings_limit_text(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }
}

if (!function_exists('vs_settings_sanitize_marquee_href')) {
    function vs_settings_sanitize_marquee_href(string $href): string
    {
        $value = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $value);
        $lower = strtolower($normalized);

        if (
            str_starts_with($lower, 'javascript:') ||
            str_starts_with($lower, 'data:') ||
            str_starts_with($lower, 'vbscript:')
        ) {
            return '';
        }

        if (
            preg_match('#^(https?:)?//#i', $normalized) ||
            str_starts_with($lower, '/') ||
            str_starts_with($lower, './') ||
            str_starts_with($lower, '../') ||
            str_starts_with($lower, '#') ||
            str_starts_with($lower, 'mailto:') ||
            str_starts_with($lower, 'tel:')
        ) {
            return $normalized;
        }

        if (preg_match('/^[a-z0-9][a-z0-9\-._\/?#=&%+]*$/i', $normalized)) {
            return $normalized;
        }

        return '';
    }
}

if (!function_exists('vs_settings_normalize_marquee_html_fallback')) {
    function vs_settings_normalize_marquee_html_fallback(string $html): string
    {
        $normalized = preg_replace('/<\s*(p|div|h[1-6]|ul|ol)\b[^>]*>/i', '', $html) ?? $html;
        $normalized = preg_replace('/<\s*\/\s*(p|div|h[1-6]|ul|ol)\s*>/i', '<br>', $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*li\b[^>]*>/i', '&#8226; ', $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*\/\s*li\s*>/i', '<br>', $normalized) ?? $normalized;
        $normalized = strip_tags($normalized, '<a><br><strong><b><em><i><u><span>');
        $normalized = preg_replace('/<(strong|b|em|i|u|span)\b[^>]*>/i', '<$1>', $normalized) ?? $normalized;

        $normalized = preg_replace_callback(
            '/<a\b[^>]*href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)<\/a>/is',
            static function (array $matches): string {
                $href = $matches[2] ?? $matches[3] ?? $matches[4] ?? '';
                $safeHref = vs_settings_sanitize_marquee_href($href);
                $innerHtml = $matches[5] ?? '';

                if ($safeHref === '') {
                    return strip_tags($innerHtml, '<br><strong><b><em><i><u><span>');
                }

                return '<a href="' . htmlspecialchars($safeHref, ENT_QUOTES, 'UTF-8') . '">' . $innerHtml . '</a>';
            },
            $normalized
        ) ?? $normalized;

        $normalized = preg_replace('/(?:\s*<br\/?>\s*){3,}/i', '<br><br>', $normalized) ?? $normalized;
        $normalized = preg_replace('/^(?:\s*<br\/?>\s*)+|(?:\s*<br\/?>\s*)+$/i', '', $normalized) ?? $normalized;

        return trim($normalized);
    }
}

if (!function_exists('vs_settings_render_marquee_node')) {
    function vs_settings_render_marquee_node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        $innerHtml = vs_settings_render_marquee_children($node);

        if (in_array($tag, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $trimmedInner = trim($innerHtml);
            return $trimmedInner === '' ? '' : $trimmedInner . '<br>';
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            $trimmedInner = trim($innerHtml);
            return $trimmedInner === '' ? '' : $trimmedInner . '<br>';
        }

        if ($tag === 'li') {
            $trimmedInner = trim($innerHtml);
            return $trimmedInner === '' ? '' : '&#8226; ' . $trimmedInner . '<br>';
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if (in_array($tag, ['strong', 'b', 'em', 'i', 'u', 'span'], true)) {
            return '<' . $tag . '>' . $innerHtml . '</' . $tag . '>';
        }

        if ($tag === 'a') {
            $safeHref = vs_settings_sanitize_marquee_href((string) ($node->attributes?->getNamedItem('href')?->nodeValue ?? ''));
            $trimmedInner = trim($innerHtml);

            if ($trimmedInner === '') {
                return '';
            }

            if ($safeHref === '') {
                return $trimmedInner;
            }

            $target = strtolower(trim((string) ($node->attributes?->getNamedItem('target')?->nodeValue ?? '')));
            $attributes = ' href="' . htmlspecialchars($safeHref, ENT_QUOTES, 'UTF-8') . '"';

            if ($target === '_blank') {
                $attributes .= ' target="_blank" rel="noopener noreferrer"';
            }

            return '<a' . $attributes . '>' . $trimmedInner . '</a>';
        }

        return $innerHtml;
    }
}

if (!function_exists('vs_settings_render_marquee_children')) {
    function vs_settings_render_marquee_children(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= vs_settings_render_marquee_node($childNode);
        }

        return $html;
    }
}

if (!function_exists('vs_settings_normalize_marquee_html')) {
    function vs_settings_normalize_marquee_html($value): string
    {
        $html = trim((string) $value);
        if ($html === '') {
            return '';
        }

        $html = vs_settings_limit_text($html, 12000);

        if (!class_exists('DOMDocument')) {
            return vs_settings_normalize_marquee_html_fallback($html);
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div data-marquee-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($previousUseInternalErrors !== null) {
            libxml_use_internal_errors($previousUseInternalErrors);
        } else {
            libxml_clear_errors();
        }
        libxml_clear_errors();

        if (!$loaded) {
            return vs_settings_normalize_marquee_html_fallback($html);
        }

        $container = null;
        foreach ($dom->getElementsByTagName('div') as $divNode) {
            if ($divNode->getAttribute('data-marquee-root') === '1') {
                $container = $divNode;
                break;
            }
        }

        if (!$container) {
            return vs_settings_normalize_marquee_html_fallback($html);
        }

        $normalized = vs_settings_render_marquee_children($container);
        $normalized = preg_replace('/(?:\s*<br>\s*){3,}/i', '<br><br>', $normalized) ?? $normalized;
        $normalized = preg_replace('/^(?:\s*<br>\s*)+|(?:\s*<br>\s*)+$/i', '', $normalized) ?? $normalized;

        return trim($normalized);
    }
}

if (!function_exists('vs_settings_get_marquee_html')) {
    function vs_settings_get_marquee_html(PDO $pdo, string $key): string
    {
        $definitions = vs_settings_marquee_definitions();
        $defaultValue = $definitions[$key]['default'] ?? '';

        try {
            vs_settings_ensure_table($pdo);
            $storedValue = vs_settings_get_value($pdo, $key);
        } catch (Throwable $e) {
            return $defaultValue;
        }

        if ($storedValue === null) {
            return $defaultValue;
        }

        return vs_settings_normalize_marquee_html($storedValue);
    }
}

if (!function_exists('vs_settings_get_marquee_form_values')) {
    function vs_settings_get_marquee_form_values(PDO $pdo): array
    {
        $definitions = vs_settings_marquee_definitions();
        $values = [];

        try {
            vs_settings_ensure_table($pdo);

            foreach ($definitions as $key => $definition) {
                $storedValue = vs_settings_get_value($pdo, $key);
                $values[$key] = $storedValue === null ? (string) ($definition['default'] ?? '') : (string) $storedValue;
            }
        } catch (Throwable $e) {
            foreach ($definitions as $key => $definition) {
                $values[$key] = (string) ($definition['default'] ?? '');
            }
        }

        return $values;
    }
}
