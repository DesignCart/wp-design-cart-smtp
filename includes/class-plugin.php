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

final class DC_SMTP_Plugin {
    /** @var self|null */
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'maybe_upgrade']);
        add_action('init', [$this, 'register_hooks']);
        add_action('before_woocommerce_init', [$this, 'declare_woocommerce_compatibility']);
        add_filter('plugin_action_links_' . DC_SMTP_BASENAME, [$this, 'action_links']);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'design-cart-smtp',
            false,
            dirname(DC_SMTP_BASENAME) . '/languages'
        );
    }

    public function maybe_upgrade(): void {
        DC_SMTP_Logger::maybe_upgrade();
    }

    public function register_hooks(): void {
        DC_SMTP_Mailer::instance()->boot();
        DC_SMTP_WooCommerce::instance()->boot();
        DC_SMTP_Admin::instance()->boot();
    }

    public function declare_woocommerce_compatibility(): void {
        if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            DC_SMTP_FILE,
            true
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            DC_SMTP_FILE,
            true
        );
    }

    /**
     * @param array<int, string> $links
     * @return array<int, string>
     */
    public function action_links(array $links): array {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('options-general.php?page=design-cart-smtp')) . '">' .
            esc_html__('Ustawienia', 'design-cart-smtp') .
            '</a>'
        );

        return $links;
    }
}
