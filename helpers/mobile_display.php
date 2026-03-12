<?php
/**
 * Helpers for displaying phone numbers with country code in admin UI.
 */

if (!function_exists('vs_mobile_extract_country_context')) {
    /**
     * @param mixed $formData JSON string or associative array
     * @return array{country_code:?string,custom_country_code:?string}
     */
    function vs_mobile_extract_country_context($formData): array
    {
        if (is_string($formData)) {
            $decoded = json_decode($formData, true);
            $formData = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($formData)) {
            return ['country_code' => null, 'custom_country_code' => null];
        }

        return [
            'country_code' => array_key_exists('country_code', $formData) ? (string)$formData['country_code'] : null,
            'custom_country_code' => array_key_exists('custom_country_code', $formData) ? (string)$formData['custom_country_code'] : null,
        ];
    }
}

if (!function_exists('vs_mobile_default_country_code_digits')) {
    function vs_mobile_default_country_code_digits(): string
    {
        $raw = defined('WHATSAPP_COUNTRY_CODE') ? (string)WHATSAPP_COUNTRY_CODE : '91';
        $digits = preg_replace('/[^0-9]/', '', $raw);
        $digits = is_string($digits) ? trim($digits) : '';
        return $digits !== '' ? $digits : '91';
    }
}

if (!function_exists('vs_format_mobile_for_display')) {
    /**
     * Format a mobile number with +country code for display.
     *
     * @param mixed $mobile
     * @param mixed $countryCode
     * @param mixed $customCountryCode
     */
    function vs_format_mobile_for_display($mobile, $countryCode = null, $customCountryCode = null): string
    {
        $raw = trim((string)$mobile);
        if ($raw === '') {
            return '';
        }

        $explicitIntl = (strpos($raw, '+') === 0 || strpos($raw, '00') === 0);
        if (strpos($raw, '00') === 0) {
            $raw = '+' . substr($raw, 2);
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);
        $digits = is_string($digits) ? trim($digits) : '';
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return trim((string)$mobile);
        }

        $countryCodeRaw = strtolower(trim((string)$countryCode));
        if ($countryCodeRaw === 'other') {
            $countryCodeRaw = trim((string)$customCountryCode);
        }
        $countryCodeDigits = preg_replace('/[^0-9]/', '', $countryCodeRaw);
        $countryCodeDigits = is_string($countryCodeDigits) ? trim($countryCodeDigits) : '';

        if (
            !$explicitIntl &&
            $countryCodeDigits !== '' &&
            strpos($digits, $countryCodeDigits) !== 0 &&
            strlen($digits) >= 6 &&
            strlen($digits) <= 12
        ) {
            $digits = $countryCodeDigits . $digits;
        }

        if (!$explicitIntl && $countryCodeDigits === '' && strlen($digits) === 10) {
            $digits = vs_mobile_default_country_code_digits() . $digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return trim((string)$mobile);
        }

        return '+' . $digits;
    }
}

if (!function_exists('vs_format_mobile_from_form_data')) {
    /**
     * Format mobile using country code from form data if present.
     *
     * @param mixed $mobile
     * @param mixed $formData
     */
    function vs_format_mobile_from_form_data($mobile, $formData = null): string
    {
        $ctx = vs_mobile_extract_country_context($formData);
        return vs_format_mobile_for_display(
            $mobile,
            $ctx['country_code'] ?? null,
            $ctx['custom_country_code'] ?? null
        );
    }
}

