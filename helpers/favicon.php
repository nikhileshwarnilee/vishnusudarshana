<?php
declare(strict_types=1);

if (!function_exists('vs_get_base_url_prefix')) {
    /**
     * Returns project base URL prefix for both root and subfolder installs.
     * Examples:
     * - /index.php => ''
     * - /vishnusudarshana/index.php => '/vishnusudarshana'
     * - /vishnusudarshana/admin/index.php => '/vishnusudarshana'
     */
    function vs_get_base_url_prefix(): string
    {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName === '') {
            return '';
        }

        $markers = [
            '/admin/',
            '/ajax/',
            '/api/',
            '/privacy/',
            '/scripts/',
            '/webhooks/',
            '/vendor/',
            '/uploads/',
            '/forms/',
        ];

        foreach ($markers as $marker) {
            $pos = strpos($scriptName, $marker);
            if ($pos !== false) {
                $prefix = rtrim(substr($scriptName, 0, $pos), '/');
                return ($prefix === '/' || $prefix === '.') ? '' : $prefix;
            }
        }

        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return '';
        }
        return $dir;
    }
}

if (!function_exists('vs_favicon_tags')) {
    function vs_favicon_tags(?string $basePrefix = null): string
    {
        $base = $basePrefix ?? vs_get_base_url_prefix();
        $manifestUrl = $base . '/manifest.json';
        $icon32Url = $base . '/assets/images/logo/logo-icon.png';
        $icon192Url = $base . '/assets/images/logo/logo-iconpwa192.png';
        $appleIconUrl = $base . '/assets/images/logo/logo-iconpwa512.png';

        $manifestUrl = htmlspecialchars($manifestUrl, ENT_QUOTES, 'UTF-8');
        $icon32Url = htmlspecialchars($icon32Url, ENT_QUOTES, 'UTF-8');
        $icon192Url = htmlspecialchars($icon192Url, ENT_QUOTES, 'UTF-8');
        $appleIconUrl = htmlspecialchars($appleIconUrl, ENT_QUOTES, 'UTF-8');

        return implode("\n", [
            '<meta name="theme-color" content="#800000">',
            '<link rel="manifest" href="' . $manifestUrl . '">',
            '<link rel="icon" type="image/png" sizes="32x32" href="' . $icon32Url . '">',
            '<link rel="icon" type="image/png" sizes="192x192" href="' . $icon192Url . '">',
            '<link rel="shortcut icon" href="' . $icon32Url . '">',
            '<link rel="apple-touch-icon" href="' . $appleIconUrl . '">',
        ]);
    }
}

