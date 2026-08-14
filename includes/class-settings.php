<?php
/**
 * Design Cart SMTP
 * Autor: Paweł Nosko
 * Firma: Design Cart
 * Adres: https://www.designcart.pl/
 */

if (!defined('ABSPATH')) {
    exit;
}

final class DC_SMTP_Settings {
    public const OPTION_KEY = 'dc_smtp_settings';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array {
        return [
            'enabled' => '0',
            'from_email' => (string) get_option('admin_email'),
            'from_name' => (string) get_bloginfo('name'),
            'force_from' => '1',
            'host' => '',
            'encryption' => 'tls',
            'port' => '587',
            'auth' => '1',
            'username' => '',
            'password' => '',
            'autotls' => '1',
            'ssl_verify' => '1',
            'log_enabled' => '1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function get(): array {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge(self::defaults(), array_map('strval', $stored));
    }

    public static function get_field(string $key): string {
        $settings = self::get();

        return $settings[$key] ?? '';
    }

    public static function is_enabled(): bool {
        return self::get_field('enabled') === '1';
    }

    public static function is_configured(): bool {
        $settings = self::get();

        if ($settings['host'] === '' || $settings['from_email'] === '' || !is_email($settings['from_email'])) {
            return false;
        }

        if ($settings['auth'] === '1' && ($settings['username'] === '' || self::password() === '')) {
            return false;
        }

        return true;
    }

    public static function password(): string {
        return self::decrypt(self::get_field('password'));
    }

    /**
     * @param mixed $input
     * @return array<string, string>
     */
    public static function sanitize($input): array {
        $current = self::get();
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();
        $clean = [];

        $toggles = ['enabled', 'force_from', 'auth', 'autotls', 'ssl_verify', 'log_enabled'];
        foreach ($toggles as $key) {
            $clean[$key] = !empty($input[$key]) ? '1' : '0';
        }

        $clean['from_email'] = sanitize_email((string) ($input['from_email'] ?? ''));
        if ($clean['from_email'] === '') {
            $clean['from_email'] = $defaults['from_email'];
        }

        $clean['from_name'] = sanitize_text_field((string) ($input['from_name'] ?? ''));
        $clean['host'] = sanitize_text_field((string) ($input['host'] ?? ''));
        $clean['username'] = sanitize_text_field((string) ($input['username'] ?? ''));

        $encryption = sanitize_key((string) ($input['encryption'] ?? 'tls'));
        $clean['encryption'] = in_array($encryption, ['none', 'ssl', 'tls'], true) ? $encryption : 'tls';

        $port = absint($input['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            $port = $clean['encryption'] === 'ssl' ? 465 : 587;
        }
        $clean['port'] = (string) $port;

        $new_password = (string) ($input['password'] ?? '');
        if ($new_password === '') {
            $clean['password'] = $current['password'];
        } else {
            $clean['password'] = self::encrypt($new_password);
        }

        return $clean;
    }

    public static function encrypt(string $value): string {
        if ($value === '') {
            return '';
        }

        $key = hash('sha256', wp_salt('auth'), true);
        $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $raw = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($raw === false) {
            return base64_encode($value);
        }

        return 'dc1:' . base64_encode($raw);
    }

    public static function decrypt(string $value): string {
        if ($value === '') {
            return '';
        }

        if (strpos($value, 'dc1:') !== 0) {
            $decoded = base64_decode($value, true);

            return $decoded !== false ? $decoded : $value;
        }

        $raw = base64_decode(substr($value, 4), true);
        if ($raw === false) {
            return '';
        }

        $key = hash('sha256', wp_salt('auth'), true);
        $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $plain = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain !== false ? $plain : '';
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function presets(): array {
        return [
            'custom' => [
                'label' => __('Własny', 'design-cart-smtp'),
                'host' => '',
                'encryption' => 'tls',
                'port' => '587',
            ],
            'gmail' => [
                'label' => 'Gmail',
                'host' => 'smtp.gmail.com',
                'encryption' => 'tls',
                'port' => '587',
            ],
            'outlook' => [
                'label' => 'Outlook',
                'host' => 'smtp.office365.com',
                'encryption' => 'tls',
                'port' => '587',
            ],
            'homepl' => [
                'label' => 'home.pl',
                'host' => 'smtp.home.pl',
                'encryption' => 'tls',
                'port' => '587',
            ],
            'nazwapl' => [
                'label' => 'nazwa.pl',
                'host' => 'smtp.nazwa.pl',
                'encryption' => 'ssl',
                'port' => '465',
            ],
            'ovh' => [
                'label' => 'OVH',
                'host' => 'ssl0.ovh.net',
                'encryption' => 'ssl',
                'port' => '465',
            ],
            'cyberfolks' => [
                'label' => 'cyber_Folks',
                'host' => 'mail.cyberfolks.pl',
                'encryption' => 'tls',
                'port' => '587',
            ],
        ];
    }
}
