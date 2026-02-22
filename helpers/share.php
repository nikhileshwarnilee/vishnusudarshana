<?php
declare(strict_types=1);

require_once __DIR__ . '/favicon.php';

if (!function_exists('vs_is_local_host')) {
    function vs_is_local_host(string $host): bool
    {
        $h = strtolower(trim($host));
        if ($h === '') {
            return false;
        }

        return $h === 'localhost'
            || $h === '127.0.0.1'
            || $h === '::1'
            || str_starts_with($h, '127.')
            || str_ends_with($h, '.local');
    }
}

if (!function_exists('vs_request_scheme')) {
    function vs_request_scheme(): string
    {
        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '') {
            return str_contains($forwardedProto, 'https') ? 'https' : 'http';
        }

        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('vs_origin')) {
    function vs_origin(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($host === '') {
            $host = 'localhost';
        }

        return vs_request_scheme() . '://' . $host;
    }
}

if (!function_exists('vs_share_origin')) {
    function vs_share_origin(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if (vs_is_local_host($host)) {
            return 'https://vishnusudarshana.com';
        }

        return vs_origin();
    }
}

if (!function_exists('vs_share_base_prefix')) {
    function vs_share_base_prefix(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if (vs_is_local_host($host)) {
            return '';
        }

        $prefix = rtrim(vs_get_base_url_prefix(), '/');
        if ($prefix === '/' || $prefix === '.') {
            return '';
        }

        return $prefix;
    }
}

if (!function_exists('vs_project_absolute_url')) {
    /**
     * Build project-aware absolute URL from root-relative project path.
     * Example on localhost subfolder install:
     * - vs_project_absolute_url('panchang.php')
     *   => http://localhost/vishnusudarshana/panchang.php
     */
    function vs_project_absolute_url(string $path = ''): string
    {
        $rawPath = trim($path);
        if ($rawPath !== '' && preg_match('#^(?:https?:)?//#i', $rawPath)) {
            return $rawPath;
        }

        $basePrefix = vs_share_base_prefix();
        $pathPart = ltrim(str_replace('\\', '/', $rawPath), '/');
        $origin = vs_share_origin();

        if ($pathPart === '') {
            return $origin . ($basePrefix !== '' ? $basePrefix : '');
        }

        return $origin . ($basePrefix !== '' ? $basePrefix : '') . '/' . $pathPart;
    }
}

if (!function_exists('vs_current_absolute_url')) {
    function vs_current_absolute_url(): string
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if ($requestUri === '') {
            $requestUri = '/';
        }
        if (!str_starts_with($requestUri, '/')) {
            $requestUri = '/' . $requestUri;
        }

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if (vs_is_local_host($host)) {
            $localPrefix = rtrim(vs_get_base_url_prefix(), '/');
            if ($localPrefix !== '' && $localPrefix !== '/' && $localPrefix !== '.') {
                if (str_starts_with($requestUri, $localPrefix . '/')) {
                    $requestUri = substr($requestUri, strlen($localPrefix));
                } elseif ($requestUri === $localPrefix) {
                    $requestUri = '/';
                }
            }
        }

        return vs_share_origin() . $requestUri;
    }
}

if (!function_exists('vs_social_meta_tags')) {
    /**
     * Generates default social sharing meta tags (Open Graph + Twitter).
     * Can be overridden using page variables:
     * - $shareTitle, $shareDescription, $shareUrl, $shareImage, $shareType
     */
    function vs_social_meta_tags(): string
    {
        $title = trim((string)($GLOBALS['shareTitle'] ?? $GLOBALS['pageTitle'] ?? 'Vishnusudarshana'));
        if ($title === '') {
            $title = 'Vishnusudarshana';
        }

        $description = trim((string)($GLOBALS['shareDescription'] ?? 'Spiritual guidance, services, panchang, din vishesh, and daily horoscope updates.'));
        if ($description === '') {
            $description = 'Spiritual guidance, services, panchang, din vishesh, and daily horoscope updates.';
        }

        $url = trim((string)($GLOBALS['shareUrl'] ?? vs_current_absolute_url()));
        if ($url === '') {
            $url = vs_current_absolute_url();
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = vs_project_absolute_url($url);
        }

        $image = trim((string)($GLOBALS['shareImage'] ?? 'assets/images/logo/logo-iconpwa512.png'));
        if ($image === '') {
            $image = 'assets/images/logo/logo-iconpwa512.png';
        }
        if (!preg_match('#^https?://#i', $image)) {
            $image = vs_project_absolute_url($image);
        }

        $type = trim((string)($GLOBALS['shareType'] ?? 'website'));
        if ($type === '') {
            $type = 'website';
        }

        $escTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $escUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $escImage = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
        $escType = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');

        return implode("\n", [
            '<link rel="canonical" href="' . $escUrl . '">',
            '<meta name="description" content="' . $escDescription . '">',
            '<meta property="og:site_name" content="Vishnusudarshana">',
            '<meta property="og:type" content="' . $escType . '">',
            '<meta property="og:title" content="' . $escTitle . '">',
            '<meta property="og:description" content="' . $escDescription . '">',
            '<meta property="og:url" content="' . $escUrl . '">',
            '<meta property="og:image" content="' . $escImage . '">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . $escTitle . '">',
            '<meta name="twitter:description" content="' . $escDescription . '">',
            '<meta name="twitter:image" content="' . $escImage . '">',
        ]);
    }
}
