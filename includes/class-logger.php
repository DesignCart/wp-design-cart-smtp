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

final class DC_SMTP_Logger {
    public const TABLE_VERSION = '1.0.0';
    public const TABLE_VERSION_OPTION = 'dc_smtp_log_db_version';
    public const MAX_ROWS = 100;

    private static string $source = 'wordpress';
    private static string $email_id = '';

    public static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'dc_smtp_log';
    }

    public static function install(): void {
        global $wpdb;

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sent_at datetime NOT NULL,
            to_email varchar(255) NOT NULL DEFAULT '',
            subject varchar(500) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'success',
            source varchar(50) NOT NULL DEFAULT 'wordpress',
            email_id varchar(120) NOT NULL DEFAULT '',
            error_message text NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY sent_at (sent_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option(self::TABLE_VERSION_OPTION, self::TABLE_VERSION);
    }

    public static function maybe_upgrade(): void {
        if (get_option(self::TABLE_VERSION_OPTION) !== self::TABLE_VERSION) {
            self::install();
        }
    }

    public static function set_context(string $source, string $email_id = ''): void {
        self::$source = $source !== '' ? $source : 'wordpress';
        self::$email_id = $email_id;
    }

    public static function reset_context(): void {
        self::$source = 'wordpress';
        self::$email_id = '';
    }

    public static function current_source(): string {
        return self::$source;
    }

    public static function current_email_id(): string {
        return self::$email_id;
    }

    public static function log(string $to, string $subject, string $status, string $error = ''): void {
        if (DC_SMTP_Settings::get_field('log_enabled') !== '1') {
            self::reset_context();
            return;
        }

        global $wpdb;

        $wpdb->insert(
            self::table_name(),
            [
                'sent_at' => current_time('mysql'),
                'to_email' => self::clip($to, 255),
                'subject' => self::clip($subject, 500),
                'status' => $status === 'failed' ? 'failed' : 'success',
                'source' => self::clip(self::$source, 50),
                'email_id' => self::clip(self::$email_id, 120),
                'error_message' => $error,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        self::trim();
        self::reset_context();
    }

    public static function trim(): void {
        global $wpdb;

        $table = self::table_name();
        $keep = (int) self::MAX_ROWS;
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($count <= $keep) {
            return;
        }

        $excess = $count - $keep;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} ORDER BY sent_at ASC, id ASC LIMIT %d",
                $excess
            )
        );
    }

    /**
     * @return array<int, object>
     */
    public static function recent(int $limit = 50): array {
        global $wpdb;

        $table = self::table_name();
        $limit = max(1, min(100, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY sent_at DESC, id DESC LIMIT %d",
                $limit
            )
        );

        return is_array($rows) ? $rows : [];
    }

    public static function clear(): void {
        global $wpdb;

        $wpdb->query('DELETE FROM ' . self::table_name());
    }

    private static function clip(string $value, int $max): string {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    public static function last_status(): ?object {
        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY sent_at DESC, id DESC LIMIT 1");

        return $row ?: null;
    }
}
