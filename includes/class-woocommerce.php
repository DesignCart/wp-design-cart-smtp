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

final class DC_SMTP_WooCommerce {
    /** @var self|null */
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function is_active(): bool {
        return class_exists('WooCommerce') && function_exists('WC');
    }

    public function boot(): void {
        add_filter('woocommerce_mail_callback_params', [$this, 'mark_context'], 10, 2);
        add_filter('woocommerce_email_from_address', [$this, 'filter_from_address'], 999, 2);
        add_filter('woocommerce_email_from_name', [$this, 'filter_from_name'], 999, 2);
        add_action('admin_notices', [$this, 'render_email_settings_notice']);
    }

    /**
     * @param array<int, mixed> $params
     * @param mixed $email
     * @return array<int, mixed>
     */
    public function mark_context(array $params, $email): array {
        $id = '';
        if (is_object($email) && isset($email->id)) {
            $id = (string) $email->id;
        }

        DC_SMTP_Logger::set_context('woocommerce', $id);

        return $params;
    }

    /**
     * @param mixed $email
     */
    public function filter_from_address(string $address, $email = null): string {
        if (!DC_SMTP_Settings::is_enabled()) {
            return $address;
        }

        $settings = DC_SMTP_Settings::get();
        if ($settings['force_from'] === '1' && is_email($settings['from_email'])) {
            return $settings['from_email'];
        }

        return $address;
    }

    /**
     * @param mixed $email
     */
    public function filter_from_name(string $name, $email = null): string {
        if (!DC_SMTP_Settings::is_enabled()) {
            return $name;
        }

        $settings = DC_SMTP_Settings::get();
        if ($settings['force_from'] === '1' && $settings['from_name'] !== '') {
            return $settings['from_name'];
        }

        return $name;
    }

    public function render_email_settings_notice(): void {
        if (!self::is_active()) {
            return;
        }

        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
        if (!$screen || $screen->id !== 'woocommerce_page_wc-settings' || $tab !== 'email') {
            return;
        }

        $url = admin_url('options-general.php?page=design-cart-smtp');
        $enabled = DC_SMTP_Settings::is_enabled() && DC_SMTP_Settings::is_configured();

        echo '<div class="notice ' . ($enabled ? 'notice-success' : 'notice-warning') . '" style="padding:12px 16px;margin:16px 0;">';
        echo '<p style="margin:0;"><strong>Design Cart SMTP.</strong> ';
        if ($enabled) {
            echo esc_html__('Wiadomości WooCommerce są wysyłane przez SMTP.', 'design-cart-smtp');
        } else {
            echo esc_html__('Wtyczka jest zainstalowana, ale SMTP nie jest jeszcze włączony lub skonfigurowany.', 'design-cart-smtp');
        }
        echo ' <a href="' . esc_url($url) . '">' . esc_html__('Otwórz ustawienia SMTP', 'design-cart-smtp') . '</a>';
        echo '</p></div>';
    }

    /**
     * @return array<int, array{id:string, title:string, enabled:bool}>
     */
    public static function email_types(): array {
        if (!self::is_active() || !WC()->mailer()) {
            return [];
        }

        $emails = WC()->mailer()->get_emails();
        if (!is_array($emails)) {
            return [];
        }

        $list = [];
        foreach ($emails as $email) {
            if (!is_object($email)) {
                continue;
            }

            $list[] = [
                'id' => (string) ($email->id ?? ''),
                'title' => (string) ($email->get_title() ?? $email->id ?? ''),
                'enabled' => method_exists($email, 'is_enabled') ? (bool) $email->is_enabled() : true,
            ];
        }

        return $list;
    }

    /**
     * @return array{ok:bool, message:string, error?:string, errors?:array<int, array{code:string, title:string, hint:string}>, debug:string}
     */
    public static function send_style_test(string $to): array {
        if (!self::is_active()) {
            return [
                'ok' => false,
                'message' => __('WooCommerce nie jest aktywny.', 'design-cart-smtp'),
                'error' => __('WooCommerce nie jest aktywny.', 'design-cart-smtp'),
                'errors' => [
                    [
                        'code' => 'woocommerce',
                        'title' => __('WooCommerce nie jest aktywny.', 'design-cart-smtp'),
                        'hint' => __('Włącz WooCommerce, aby wysłać test w szablonie sklepu. Zwykły test SMTP działa bez niego.', 'design-cart-smtp'),
                    ],
                ],
                'debug' => '',
            ];
        }

        $heading = __('Test Design Cart SMTP', 'design-cart-smtp');
        $body = '<p>' . esc_html__('To jest testowa wiadomość wysłana przez mailer WooCommerce (wrap_message + send).', 'design-cart-smtp') . '</p>';
        $body .= '<p>' . esc_html__('Jeśli ją widzisz, zamówienia, konta i powiadomienia sklepu pójdą tą samą drogą SMTP.', 'design-cart-smtp') . '</p>';

        $mailer = WC()->mailer();
        $wrapped = $mailer->wrap_message($heading, $body);

        DC_SMTP_Logger::set_context('woocommerce', 'dc_smtp_wc_test');

        $mailer_instance = DC_SMTP_Mailer::instance();
        $result = $mailer_instance->send_test(
            $to,
            sprintf(
                /* translators: %s: site name */
                __('[%s] Test e-mail WooCommerce', 'design-cart-smtp'),
                wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
            ),
            $wrapped,
            true,
            'woocommerce',
            'dc_smtp_wc_test'
        );

        if ($result['ok']) {
            $result['message'] = sprintf(
                /* translators: %s: recipient email */
                __('Test w stylu WooCommerce został wysłany na adres %s.', 'design-cart-smtp'),
                $to
            );
        }

        return $result;
    }
}
