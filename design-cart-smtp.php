<?php
/**
 * Design Cart SMTP
 * Autor: Paweł Nosko
 * Firma: Design Cart
 * Adres: https://www.designcart.pl/
 *
 * Plugin Name:       Design Cart SMTP
 * Plugin URI:        https://www.designcart.pl/
 * Description:       Proste przekierowanie poczty WordPress i WooCommerce na SMTP — z testem wysyłki i dziennikiem.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * Author:            Paweł Nosko
 * Author URI:        https://www.designcart.pl/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       design-cart-smtp
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DC_SMTP_VERSION', '1.0.0');
define('DC_SMTP_FILE', __FILE__);
define('DC_SMTP_DIR', plugin_dir_path(__FILE__));
define('DC_SMTP_URL', plugin_dir_url(__FILE__));
define('DC_SMTP_BASENAME', plugin_basename(__FILE__));

require_once DC_SMTP_DIR . 'includes/class-settings.php';
require_once DC_SMTP_DIR . 'includes/class-logger.php';
require_once DC_SMTP_DIR . 'includes/class-mailer.php';
require_once DC_SMTP_DIR . 'includes/class-woocommerce.php';
require_once DC_SMTP_DIR . 'includes/class-admin.php';
require_once DC_SMTP_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['DC_SMTP_Logger', 'install']);

DC_SMTP_Plugin::instance()->boot();
