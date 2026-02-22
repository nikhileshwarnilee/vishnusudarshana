<?php
declare(strict_types=1);

require_once __DIR__ . '/favicon.php';

if (!function_exists('vs_blog_base_prefix')) {
    function vs_blog_base_prefix(?string $basePrefix = null): string
    {
        $base = $basePrefix ?? vs_get_base_url_prefix();
        if ($base === '/' || $base === '.') {
            return '';
        }
        return rtrim($base, '/');
    }
}

if (!function_exists('vs_blog_normalize_cover_image_for_storage')) {
    /**
     * Keep blog cover image DB values normalized to filename (or nested path under uploads/blogs).
     * Accepts legacy values like uploads/blogs/<file>, /uploads/blogs/<file>, or /subfolder/uploads/blogs/<file>.
     */
    function vs_blog_normalize_cover_image_for_storage(?string $coverImage): string
    {
        $value = trim((string)$coverImage);
        if ($value === '') {
            return '';
        }

        $value = str_replace('\\', '/', $value);
        if (preg_match('#^(?:https?:)?//#i', $value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        $value = preg_replace('/[?#].*$/', '', $value);
        $value = ltrim($value, '/');

        $basePrefix = trim(vs_blog_base_prefix(), '/');
        if ($basePrefix !== '' && str_starts_with($value, $basePrefix . '/')) {
            $value = substr($value, strlen($basePrefix) + 1);
        }

        if (str_starts_with($value, 'uploads/blogs/')) {
            $value = substr($value, strlen('uploads/blogs/'));
        }

        return ltrim($value, '/');
    }
}

if (!function_exists('vs_blog_cover_image_url')) {
    function vs_blog_cover_image_url(?string $coverImage, ?string $basePrefix = null): string
    {
        $value = trim((string)$coverImage);
        if ($value === '') {
            return '';
        }

        $value = str_replace('\\', '/', $value);
        if (preg_match('#^(?:https?:)?//#i', $value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        $value = preg_replace('/[?#].*$/', '', $value);
        $value = ltrim($value, '/');
        $base = vs_blog_base_prefix($basePrefix);
        $baseTrim = trim($base, '/');

        if ($baseTrim !== '' && str_starts_with($value, $baseTrim . '/')) {
            return '/' . $value;
        }

        if (str_starts_with($value, 'uploads/')) {
            return ($base !== '' ? $base : '') . '/' . $value;
        }

        $normalized = vs_blog_normalize_cover_image_for_storage($value);
        if ($normalized === '') {
            return '';
        }

        return ($base !== '' ? $base : '') . '/uploads/blogs/' . ltrim($normalized, '/');
    }
}

if (!function_exists('vs_blog_cover_image_file_path')) {
    function vs_blog_cover_image_file_path(?string $coverImage): string
    {
        $normalized = vs_blog_normalize_cover_image_for_storage($coverImage);
        if (
            $normalized === '' ||
            preg_match('#^(?:https?:)?//#i', $normalized) ||
            str_starts_with($normalized, 'data:')
        ) {
            return '';
        }

        $normalized = ltrim(str_replace('\\', '/', $normalized), '/');
        if (str_starts_with($normalized, 'uploads/')) {
            return __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        }

        return __DIR__ . '/../uploads/blogs/' . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }
}

if (!function_exists('vs_blog_normalize_content_media_urls')) {
    /**
     * Normalizes relative uploads URLs in saved blog HTML so it works in both
     * root install (/...) and subfolder install (/project/...).
     */
    function vs_blog_normalize_content_media_urls(string $html, ?string $basePrefix = null): string
    {
        if ($html === '') {
            return $html;
        }

        $base = vs_blog_base_prefix($basePrefix);
        $baseTrim = trim($base, '/');

        return (string)preg_replace_callback(
            '/\b(src|href)\s*=\s*(["\'])([^"\']+)\2/i',
            static function (array $matches) use ($base, $baseTrim): string {
                $attr = $matches[1];
                $quote = $matches[2];
                $url = trim(str_replace('\\', '/', $matches[3]));

                if (
                    $url === '' ||
                    preg_match('#^(?:https?:)?//#i', $url) ||
                    str_starts_with($url, 'data:') ||
                    str_starts_with($url, 'mailto:') ||
                    str_starts_with($url, 'tel:') ||
                    str_starts_with($url, '#')
                ) {
                    return $matches[0];
                }

                $withoutLead = ltrim($url, '/');
                if ($baseTrim !== '' && str_starts_with($withoutLead, $baseTrim . '/')) {
                    return $matches[0];
                }

                $normalized = preg_replace('#^(?:\.\./)+#', '', $withoutLead);
                if (!str_starts_with($normalized, 'uploads/')) {
                    return $matches[0];
                }

                $finalUrl = ($base !== '' ? $base : '') . '/' . $normalized;
                return $attr . '=' . $quote . $finalUrl . $quote;
            },
            $html
        );
    }
}
