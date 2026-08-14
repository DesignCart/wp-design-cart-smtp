<?php
/**
 * Design Cart SMTP
 * Autor: Paweł Nosko
 * Firma: Design Cart
 * Adres: https://www.designcart.pl/
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

delete_option('dc_smtp_settings');
delete_option('dc_smtp_log_db_version');

$table = $wpdb->prefix . 'dc_smtp_log';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
