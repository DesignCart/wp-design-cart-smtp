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

final class DC_SMTP_Admin {
    /** @var self|null */
    private static $instance = null;

    /** @var array<string, string> */
    private $settings = [];

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_dc_smtp_send_test', [$this, 'ajax_send_test']);
        add_action('wp_ajax_dc_smtp_clear_log', [$this, 'ajax_clear_log']);
    }

    public function register_page(): void {
        add_options_page(
            __('Design Cart SMTP', 'design-cart-smtp'),
            __('Design Cart SMTP', 'design-cart-smtp'),
            'manage_options',
            'design-cart-smtp',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            'dc_smtp_group',
            DC_SMTP_Settings::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => ['DC_SMTP_Settings', 'sanitize'],
                'default' => DC_SMTP_Settings::defaults(),
            ]
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_design-cart-smtp') {
            return;
        }

        $base = DC_SMTP_URL . 'assets/admin/';

        wp_enqueue_style(
            'design-cart-smtp-font-awesome',
            $base . 'vendor/font-awesome/font-awesome.min.css',
            [],
            '4.7.0'
        );
        wp_enqueue_style(
            'design-cart-smtp-interface',
            $base . 'css/dc-interface.css',
            ['design-cart-smtp-font-awesome'],
            DC_SMTP_VERSION
        );
        wp_enqueue_style(
            'design-cart-smtp-overrides',
            $base . 'css/admin-overrides.css',
            ['design-cart-smtp-interface'],
            DC_SMTP_VERSION
        );
        wp_enqueue_script(
            'design-cart-smtp-interface',
            $base . 'js/dc-interface.js',
            [],
            DC_SMTP_VERSION,
            true
        );
        wp_enqueue_script(
            'design-cart-smtp-admin',
            $base . 'js/admin.js',
            ['jquery', 'design-cart-smtp-interface'],
            DC_SMTP_VERSION,
            true
        );

        wp_localize_script('design-cart-smtp-admin', 'dcSmtpAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dc_smtp_admin'),
            'presets' => DC_SMTP_Settings::presets(),
            'i18n' => [
                'sending' => __('Wysyłanie…', 'design-cart-smtp'),
            'sendTest' => __('Wyślij e-mail testowy', 'design-cart-smtp'),
            'sendWc' => __('Test w stylu WooCommerce', 'design-cart-smtp'),
            'errorList' => __('Rozpoznane błędy', 'design-cart-smtp'),
            'phpmailer' => __('Komunikat PHPMailer', 'design-cart-smtp'),
            'smtpLog' => __('Log SMTP', 'design-cart-smtp'),
                'clearing' => __('Czyszczenie…', 'design-cart-smtp'),
                'cleared' => __('Dziennik został wyczyszczony.', 'design-cart-smtp'),
                'error' => __('Nie udało się wykonać żądania.', 'design-cart-smtp'),
            ],
        ]);
    }

    public function ajax_send_test(): void {
        check_ajax_referer('dc_smtp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            $this->send_test_error(
                __('Brak uprawnień.', 'design-cart-smtp'),
                'capability',
                __('Zaloguj się jako administrator, aby wysyłać test.', 'design-cart-smtp')
            );
        }

        $to = sanitize_email((string) wp_unslash($_POST['to'] ?? ''));
        $subject = sanitize_text_field((string) wp_unslash($_POST['subject'] ?? ''));
        $message = wp_kses_post((string) wp_unslash($_POST['message'] ?? ''));
        $mode = sanitize_key((string) wp_unslash($_POST['mode'] ?? 'standard'));

        if (!is_email($to)) {
            $this->send_test_error(
                __('Podaj poprawny adres e-mail odbiorcy.', 'design-cart-smtp'),
                'recipient',
                __('Pole „Odbiorca” musi zawierać pełny adres, np. admin@example.com.', 'design-cart-smtp')
            );
        }

        if (!DC_SMTP_Settings::is_enabled()) {
            $this->send_test_error(
                __('SMTP jest wyłączony.', 'design-cart-smtp'),
                'disabled',
                __('W zakładce Ogólne włącz SMTP i zapisz ustawienia.', 'design-cart-smtp')
            );
        }

        if (!DC_SMTP_Settings::is_configured()) {
            $this->send_test_error(
                __('Konfiguracja SMTP jest niekompletna.', 'design-cart-smtp'),
                'config',
                __('Uzupełnij host, e-mail nadawcy oraz — jeśli wymagane — login i hasło, potem zapisz.', 'design-cart-smtp')
            );
        }

        if ($mode === 'woocommerce') {
            $result = DC_SMTP_WooCommerce::send_style_test($to);
        } else {
            if ($subject === '') {
                $subject = sprintf(
                    /* translators: %s: site name */
                    __('[%s] Test Design Cart SMTP', 'design-cart-smtp'),
                    wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
                );
            }
            if ($message === '') {
                $message = $this->default_test_html();
            }

            $result = DC_SMTP_Mailer::instance()->send_test($to, $subject, $message, true);
        }

        if ($result['ok']) {
            wp_send_json_success($result);
        }

        wp_send_json_error($result);
    }

    private function send_test_error(string $title, string $code, string $hint): void {
        wp_send_json_error([
            'message' => $title,
            'error' => $title,
            'errors' => [
                [
                    'code' => $code,
                    'title' => $title,
                    'hint' => $hint,
                ],
            ],
            'debug' => '',
        ]);
    }

    public function ajax_clear_log(): void {
        check_ajax_referer('dc_smtp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Brak uprawnień.', 'design-cart-smtp')]);
        }

        DC_SMTP_Logger::clear();
        wp_send_json_success(['message' => __('Dziennik został wyczyszczony.', 'design-cart-smtp')]);
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->settings = DC_SMTP_Settings::get();
        $logo = DC_SMTP_URL . 'assets/admin/images/dc-logo-white.png';

        echo '<div class="wrap dc-smtp-admin-wrap">';
        echo '<h1>' . esc_html__('Design Cart SMTP', 'design-cart-smtp') . '</h1>';
        echo '<div class="dc-page dc-smtp-admin-page">';

        $this->render_hero($logo);

        echo '<div class="dc-page__wrap">';
        echo '<div class="dc-form-card">';
        echo '<div class="dc-interface">';

        $this->render_notices();

        echo '<form class="dc-form dc-form--full" id="dc-smtp-settings-form" method="post" action="options.php">';
        settings_fields('dc_smtp_group');

        $this->render_nav();

        echo '<div class="dc-form-card__body">';
        $this->render_tab_general();
        $this->render_tab_smtp();
        $this->render_tab_test();
        $this->render_tab_woocommerce();
        $this->render_tab_log();

        echo '<div class="dc-actions">';
        echo '<button type="submit" class="dc-btn dc-btn--primary"><i class="fa fa-save"></i> ' .
            esc_html__('Zapisz', 'design-cart-smtp') . '</button>';
        echo '</div>';

        echo '</div></form></div></div></div></div></div>';
    }

    private function render_hero(string $logo): void {
        echo '<div class="dc-hero">';
        echo '<div class="dc-hero__mesh"></div>';
        echo '<img class="dc-hero__logo" src="' . esc_url($logo) . '" alt="" aria-hidden="true" width="384" height="384">';
        echo '<div class="dc-hero__orb dc-hero__orb--1"></div>';
        echo '<div class="dc-hero__orb dc-hero__orb--2"></div>';
        echo '<div class="dc-hero__inner">';
        echo '<div class="dc-hero__row">';
        echo '<div class="dc-hero__brand">';
        echo '<span class="dc-hero__icon"><i class="fa fa-paper-plane"></i></span>';
        echo '<div>';
        echo '<p class="dc-hero__eyebrow">Design Cart</p>';
        echo '<h1 class="dc-hero__title">SMTP</h1>';
        echo '</div></div>';
        echo '<div class="dc-hero__actions">';
        echo '<button type="submit" form="dc-smtp-settings-form" class="dc-btn dc-btn--light"><i class="fa fa-save"></i> ' .
            esc_html__('Zapisz', 'design-cart-smtp') . '</button>';
        echo '<a class="dc-btn dc-btn--ghost" href="' . esc_url(admin_url('plugins.php')) . '" title="' .
            esc_attr__('Wtyczki', 'design-cart-smtp') . '"><i class="fa fa-arrow-left"></i></a>';
        echo '</div></div>';
        echo '<ul class="dc-hero__bc">';
        echo '<li><a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Wtyczki', 'design-cart-smtp') . '</a></li>';
        echo '<li><a href="' . esc_url(admin_url('options-general.php')) . '">' . esc_html__('Ustawienia', 'design-cart-smtp') . '</a></li>';
        echo '<li>' . esc_html__('Design Cart SMTP', 'design-cart-smtp') . '</li>';
        echo '</ul></div></div>';
    }

    private function render_notices(): void {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="dc-smtp-flash dc-smtp-flash--ok"><i class="fa fa-check-circle"></i> ' .
                esc_html__('Ustawienia zostały zapisane.', 'design-cart-smtp') . '</div>';
        }

        foreach ($this->conflicting_plugins() as $name) {
            echo '<div class="dc-smtp-flash dc-smtp-flash--warn"><i class="fa fa-exclamation-triangle"></i> ';
            echo esc_html(
                sprintf(
                    /* translators: %s: plugin name */
                    __('Wykryto inną wtyczkę SMTP: %s. Wyłącz ją, aby uniknąć konfliktów.', 'design-cart-smtp'),
                    $name
                )
            );
            echo '</div>';
        }
    }

    private function render_nav(): void {
        $tabs = [
            'tab-general' => ['fa fa-cog', __('Ogólne', 'design-cart-smtp')],
            'tab-smtp' => ['fa fa-server', __('Serwer SMTP', 'design-cart-smtp')],
            'tab-test' => ['fa fa-paper-plane', __('Test', 'design-cart-smtp')],
            'tab-woocommerce' => ['fa fa-shopping-cart', __('WooCommerce', 'design-cart-smtp')],
            'tab-log' => ['fa fa-list-alt', __('Dziennik', 'design-cart-smtp')],
        ];

        echo '<nav class="dc-nav dc-tabs" role="tablist">';
        $first = true;
        foreach ($tabs as $id => $meta) {
            $active = $first ? ' dc-active' : '';
            $selected = $first ? 'true' : 'false';
            echo '<button type="button" class="dc-nav__btn dc-tabs__btn' . $active . '" data-dc-tab="' . esc_attr($id) . '" role="tab" aria-selected="' . $selected . '">';
            echo '<i class="' . esc_attr($meta[0]) . '"></i> ' . esc_html($meta[1]);
            echo '</button>';
            $first = false;
        }
        echo '</nav>';
    }

    private function render_tab_general(): void {
        $s = $this->settings;
        $last = DC_SMTP_Logger::last_status();

        echo '<div id="tab-general" class="dc-tab-panel dc-active" role="tabpanel">';

        $this->section_start('fa fa-heartbeat', __('Status', 'design-cart-smtp'), __('Szybki podgląd konfiguracji', 'design-cart-smtp'));
        echo '<div class="dc-smtp-status-grid">';
        $this->status_card(
            __('SMTP', 'design-cart-smtp'),
            $s['enabled'] === '1' && DC_SMTP_Settings::is_configured(),
            $s['enabled'] === '1' ? __('Włączony', 'design-cart-smtp') : __('Wyłączony', 'design-cart-smtp'),
            $s['enabled'] === '1' && !DC_SMTP_Settings::is_configured() ? __('Brakuje hosta lub danych logowania.', 'design-cart-smtp') : ''
        );
        $this->status_card(
            __('WooCommerce', 'design-cart-smtp'),
            DC_SMTP_WooCommerce::is_active(),
            DC_SMTP_WooCommerce::is_active() ? __('Wykryty', 'design-cart-smtp') : __('Nieaktywny', 'design-cart-smtp'),
            DC_SMTP_WooCommerce::is_active()
                ? __('Maile sklepu idą przez wp_mail() i ten SMTP.', 'design-cart-smtp')
                : __('Wtyczka działa też bez WooCommerce.', 'design-cart-smtp')
        );
        $last_ok = $last && $last->status === 'success';
        $this->status_card(
            __('Ostatnia wysyłka', 'design-cart-smtp'),
            $last_ok,
            $last ? ($last_ok ? __('Sukces', 'design-cart-smtp') : __('Błąd', 'design-cart-smtp')) : __('Brak', 'design-cart-smtp'),
            $last ? $last->subject : __('Wyślij test, aby potwierdzić konfigurację.', 'design-cart-smtp')
        );
        echo '</div>';
        $this->section_end();

        $this->section_start('fa fa-toggle-on', __('Przełącznik', 'design-cart-smtp'), __('Włącz routing poczty przez SMTP', 'design-cart-smtp'));
        $this->toggle('enabled', __('Włącz SMTP', 'design-cart-smtp'), __('WordPress i WooCommerce będą wysyłać wiadomości przez podany serwer.', 'design-cart-smtp'));
        $this->toggle('log_enabled', __('Zapisuj dziennik wysyłek', 'design-cart-smtp'), __('Ostatnie 100 wiadomości (sukces / błąd).', 'design-cart-smtp'));
        $this->section_end();

        $this->section_start('fa fa-user', __('Nadawca', 'design-cart-smtp'), __('Adres widoczny dla odbiorców i wymagany przez większość serwerów SMTP', 'design-cart-smtp'));
        echo '<div class="dc-row dc-row--2">';
        $this->text_field('from_email', __('E-mail nadawcy', 'design-cart-smtp'), 'email', true, 'sklep@example.com');
        $this->text_field('from_name', __('Nazwa nadawcy', 'design-cart-smtp'), 'text', false, (string) get_bloginfo('name'));
        echo '</div>';
        $this->toggle(
            'force_from',
            __('Wymuś nadawcę', 'design-cart-smtp'),
            __('Zalecane. Nadpisuje From z WooCommerce i innych wtyczek, żeby serwer SMTP nie odrzucał wiadomości.', 'design-cart-smtp')
        );
        $this->section_end();

        echo '</div>';
    }

    private function render_tab_smtp(): void {
        $s = $this->settings;
        $presets = DC_SMTP_Settings::presets();

        echo '<div id="tab-smtp" class="dc-tab-panel" role="tabpanel">';

        $this->section_start('fa fa-magic', __('Gotowe profile', 'design-cart-smtp'), __('Uzupełniają host, port i szyfrowanie. Login i hasło wpisujesz sam.', 'design-cart-smtp'));
        echo '<div class="dc-field"><span class="dc-label">' . esc_html__('Dostawca', 'design-cart-smtp') . '</span>';
        echo '<div class="dc-switch-group dc-switch-group--solid dc-smtp-presets" role="radiogroup">';
        foreach ($presets as $key => $preset) {
            echo '<label class="dc-switch-btn">';
            echo '<input class="dc-switch-btn__input" type="radio" name="dc_smtp_preset" value="' . esc_attr($key) . '"' . checked($key, 'custom', false) . '>';
            echo '<span class="dc-switch-btn__label">' . esc_html($preset['label']) . '</span>';
            echo '</label>';
        }
        echo '</div></div>';
        $this->section_end();

        $this->section_start('fa fa-server', __('Połączenie', 'design-cart-smtp'), __('Dane serwera pocztowego', 'design-cart-smtp'));
        $this->text_field('host', __('Host SMTP', 'design-cart-smtp'), 'text', true, 'smtp.example.com');

        echo '<div class="dc-field"><span class="dc-label">' . esc_html__('Szyfrowanie', 'design-cart-smtp') . '</span>';
        echo '<div class="dc-switch-group dc-switch-group--solid" role="radiogroup">';
        foreach (['none' => 'Brak', 'ssl' => 'SSL', 'tls' => 'TLS'] as $value => $label) {
            echo '<label class="dc-switch-btn">';
            echo '<input class="dc-switch-btn__input dc-smtp-encryption" type="radio" name="' . esc_attr($this->name('encryption')) . '" value="' . esc_attr($value) . '"' . checked($s['encryption'], $value, false) . '>';
            echo '<span class="dc-switch-btn__label">' . esc_html($label) . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="dc-hint">' . esc_html__('TLS / port 587 albo SSL / port 465 — najczęstszy wybór.', 'design-cart-smtp') . '</p>';
        echo '</div>';

        echo '<div class="dc-row dc-row--2">';
        $this->text_field('port', __('Port', 'design-cart-smtp'), 'number', true, '587');
        echo '<div class="dc-field"></div>';
        echo '</div>';
        $this->toggle('autotls', __('Auto TLS', 'design-cart-smtp'), __('Włącz STARTTLS, gdy serwer go oferuje.', 'design-cart-smtp'));
        $this->toggle('ssl_verify', __('Weryfikuj certyfikat SSL', 'design-cart-smtp'), __('Wyłącz tylko gdy hosting ma niepoprawny certyfikat.', 'design-cart-smtp'));
        $this->section_end();

        $this->section_start('fa fa-lock', __('Uwierzytelnianie', 'design-cart-smtp'), __('Login do skrzynki SMTP', 'design-cart-smtp'));
        $this->toggle('auth', __('Wymagaj logowania', 'design-cart-smtp'));
        echo '<div class="dc-smtp-auth-fields">';
        echo '<div class="dc-row dc-row--2">';
        $this->text_field('username', __('Użytkownik', 'design-cart-smtp'), 'text', false, 'sklep@example.com');
        $this->password_field();
        echo '</div></div>';
        $this->section_end();

        echo '</div>';
    }

    private function render_tab_test(): void {
        $admin_email = (string) get_option('admin_email');

        echo '<div id="tab-test" class="dc-tab-panel" role="tabpanel">';
        $this->section_start('fa fa-paper-plane', __('Wyślij test', 'design-cart-smtp'), __('Sprawdza SMTP bez ruszania zamówień WooCommerce', 'design-cart-smtp'));

        echo '<div class="dc-row dc-row--2">';
        echo '<div class="dc-field"><label class="dc-label dc-label--required" for="dc-smtp-test-to">' . esc_html__('Odbiorca', 'design-cart-smtp') . '</label>';
        echo '<input class="dc-input" type="email" id="dc-smtp-test-to" value="' . esc_attr($admin_email) . '" placeholder="admin@example.com">';
        echo '</div>';
        echo '<div class="dc-field"><label class="dc-label" for="dc-smtp-test-subject">' . esc_html__('Temat', 'design-cart-smtp') . '</label>';
        echo '<input class="dc-input" type="text" id="dc-smtp-test-subject" value="' . esc_attr(
            sprintf(
                /* translators: %s: site name */
                __('[%s] Test Design Cart SMTP', 'design-cart-smtp'),
                wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
            )
        ) . '">';
        echo '</div></div>';

        echo '<div class="dc-field"><label class="dc-label" for="dc-smtp-test-message">' . esc_html__('Treść', 'design-cart-smtp') . '</label>';
        echo '<textarea class="dc-textarea" id="dc-smtp-test-message" rows="5">' . esc_textarea(
            __('To jest testowa wiadomość z wtyczki Design Cart SMTP. Jeśli ją widzisz, konfiguracja działa.', 'design-cart-smtp')
        ) . '</textarea></div>';

        echo '<div class="dc-actions" style="border:0;padding-top:0;margin-top:0;">';
        echo '<button type="button" class="dc-btn dc-btn--primary" id="dc-smtp-send-test"><i class="fa fa-paper-plane"></i> ' .
            esc_html__('Wyślij e-mail testowy', 'design-cart-smtp') . '</button>';
        if (DC_SMTP_WooCommerce::is_active()) {
            echo '<button type="button" class="dc-btn dc-btn--secondary" id="dc-smtp-send-wc"><i class="fa fa-shopping-cart"></i> ' .
                esc_html__('Test w stylu WooCommerce', 'design-cart-smtp') . '</button>';
        }
        echo '</div>';

        echo '<div id="dc-smtp-test-result" class="dc-smtp-test-result" hidden></div>';
        echo '<div id="dc-smtp-errors" class="dc-smtp-errors" hidden></div>';
        echo '<div id="dc-smtp-error-info" class="dc-smtp-error-info" hidden></div>';
        echo '<div id="dc-smtp-debug-wrap" class="dc-smtp-debug-wrap" hidden>';
        echo '<h4>' . esc_html__('Log SMTP', 'design-cart-smtp') . '</h4>';
        echo '<pre id="dc-smtp-debug" class="dc-smtp-debug"></pre>';
        echo '</div>';

        $this->section_end();
        echo '</div>';
    }

    private function render_tab_woocommerce(): void {
        echo '<div id="tab-woocommerce" class="dc-tab-panel" role="tabpanel">';

        $active = DC_SMTP_WooCommerce::is_active();
        $this->section_start(
            'fa fa-shopping-cart',
            __('Kompatybilność', 'design-cart-smtp'),
            __('WooCommerce korzysta z wp_mail() — SMTP obejmuje zamówienia, konta i powiadomienia sklepu.', 'design-cart-smtp')
        );

        if ($active) {
            echo '<div class="dc-smtp-flash dc-smtp-flash--ok"><i class="fa fa-check-circle"></i> ' .
                esc_html__('WooCommerce jest aktywny. Transakcyjne e-maile sklepu przechodzą przez Design Cart SMTP.', 'design-cart-smtp') .
                '</div>';
            echo '<p class="dc-hint" style="margin-bottom:1rem;">' .
                esc_html__('Treść i szablony maili nadal edytujesz w WooCommerce. Ta wtyczka zmienia tylko drogę wysyłki (SMTP zamiast PHP mail). Włącz „Wymuś nadawcę”, jeśli serwer wymaga zgodności z loginem.', 'design-cart-smtp') .
                '</p>';
            echo '<p><a class="dc-btn dc-btn--secondary" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=email')) . '"><i class="fa fa-envelope-o"></i> ' .
                esc_html__('Ustawienia e-mail WooCommerce', 'design-cart-smtp') . '</a></p>';
        } else {
            echo '<div class="dc-smtp-flash dc-smtp-flash--warn"><i class="fa fa-info-circle"></i> ' .
                esc_html__('WooCommerce nie jest włączony. SMTP i tak obsłuży maile WordPress (formularze, reset hasła, powiadomienia).', 'design-cart-smtp') .
                '</div>';
        }

        $this->section_end();

        if ($active) {
            $this->section_start('fa fa-envelope', __('Wiadomości sklepu', 'design-cart-smtp'), __('Te typy pójdą przez SMTP, gdy są włączone w WooCommerce', 'design-cart-smtp'));
            echo '<div class="dc-smtp-table-wrap"><table class="dc-smtp-table"><thead><tr>';
            echo '<th>' . esc_html__('E-mail', 'design-cart-smtp') . '</th>';
            echo '<th>' . esc_html__('ID', 'design-cart-smtp') . '</th>';
            echo '<th>' . esc_html__('Status', 'design-cart-smtp') . '</th>';
            echo '</tr></thead><tbody>';
            foreach (DC_SMTP_WooCommerce::email_types() as $email) {
                echo '<tr>';
                echo '<td>' . esc_html($email['title']) . '</td>';
                echo '<td><code>' . esc_html($email['id']) . '</code></td>';
                echo '<td>';
                if ($email['enabled']) {
                    echo '<span class="dc-smtp-pill dc-smtp-pill--ok">' . esc_html__('Włączony', 'design-cart-smtp') . '</span>';
                } else {
                    echo '<span class="dc-smtp-pill">' . esc_html__('Wyłączony', 'design-cart-smtp') . '</span>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
            $this->section_end();
        }

        echo '</div>';
    }

    private function render_tab_log(): void {
        $rows = DC_SMTP_Logger::recent();

        echo '<div id="tab-log" class="dc-tab-panel" role="tabpanel">';
        $this->section_start('fa fa-list-alt', __('Ostatnie wysyłki', 'design-cart-smtp'), __('WordPress, WooCommerce i testy', 'design-cart-smtp'));

        echo '<div class="dc-smtp-log-toolbar">';
        echo '<button type="button" class="dc-btn dc-btn--secondary" id="dc-smtp-clear-log"><i class="fa fa-trash-o"></i> ' .
            esc_html__('Wyczyść dziennik', 'design-cart-smtp') . '</button>';
        echo '</div>';

        echo '<div class="dc-smtp-table-wrap" id="dc-smtp-log-wrap">';
        if (!$rows) {
            echo '<p class="dc-hint" style="padding:1rem;">' . esc_html__('Brak wpisów. Wyślij test albo poczekaj na pierwszą wiadomość ze sklepu.', 'design-cart-smtp') . '</p>';
        } else {
            echo '<table class="dc-smtp-table"><thead><tr>';
            echo '<th>' . esc_html__('Data', 'design-cart-smtp') . '</th>';
            echo '<th>' . esc_html__('Do', 'design-cart-smtp') . '</th>';
            echo '<th>' . esc_html__('Temat', 'design-cart-smtp') . '</th>';
            echo '<th>' . esc_html__('Źródło', 'design-cart-smtp') . '</th>';
            echo '<th>' . esc_html__('Status', 'design-cart-smtp') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $ok = $row->status === 'success';
                echo '<tr>';
                echo '<td>' . esc_html($row->sent_at) . '</td>';
                echo '<td>' . esc_html($row->to_email) . '</td>';
                echo '<td>' . esc_html($row->subject) . '</td>';
                echo '<td>' . esc_html($this->source_label((string) $row->source, (string) $row->email_id)) . '</td>';
                echo '<td><span class="dc-smtp-pill ' . ($ok ? 'dc-smtp-pill--ok' : 'dc-smtp-pill--err') . '">';
                echo esc_html($ok ? __('OK', 'design-cart-smtp') : __('Błąd', 'design-cart-smtp'));
                echo '</span>';
                if (!$ok && $row->error_message) {
                    echo '<div class="dc-hint">' . esc_html($row->error_message) . '</div>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        $this->section_end();
        echo '</div>';
    }

    private function section_start(string $icon, string $title, string $sub = ''): void {
        echo '<div class="dc-section-card dc-section">';
        echo '<div class="dc-section-card__head">';
        echo '<span class="dc-section-card__icon"><i class="' . esc_attr($icon) . '"></i></span>';
        echo '<div><h3 class="dc-section-card__title">' . esc_html($title) . '</h3>';
        if ($sub !== '') {
            echo '<p class="dc-section-card__sub">' . esc_html($sub) . '</p>';
        }
        echo '</div></div>';
    }

    private function section_end(): void {
        echo '</div>';
    }

    private function status_card(string $label, bool $ok, string $value, string $hint = ''): void {
        echo '<div class="dc-smtp-status-card">';
        echo '<p class="dc-smtp-status-card__label">' . esc_html($label) . '</p>';
        echo '<p class="dc-smtp-status-card__value ' . ($ok ? 'is-ok' : 'is-off') . '">' . esc_html($value) . '</p>';
        if ($hint !== '') {
            echo '<p class="dc-smtp-status-card__hint">' . esc_html($hint) . '</p>';
        }
        echo '</div>';
    }

    private function toggle(string $key, string $label, string $hint = ''): void {
        $checked = $this->settings[$key] === '1';
        echo '<label class="dc-toggle" style="margin-top:0.35rem;">';
        echo '<input class="dc-toggle__input" type="checkbox" name="' . esc_attr($this->name($key)) . '" value="1"' . checked($checked, true, false) . '>';
        echo '<span class="dc-toggle__track"></span>';
        echo '<span>' . esc_html($label);
        if ($hint !== '') {
            echo '<span class="dc-check__desc">' . esc_html($hint) . '</span>';
        }
        echo '</span></label>';
    }

    private function text_field(string $key, string $label, string $type = 'text', bool $required = false, string $placeholder = ''): void {
        $id = 'dc_smtp_' . $key;
        $req = $required ? ' dc-label--required' : '';
        echo '<div class="dc-field">';
        echo '<label class="dc-label' . $req . '" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        echo '<input class="dc-input" type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($this->name($key)) . '" value="' .
            esc_attr($this->settings[$key] ?? '') . '" placeholder="' . esc_attr($placeholder) . '"' . ($type === 'number' ? ' min="1" max="65535"' : '') . '>';
        echo '</div>';
    }

    private function password_field(): void {
        $has = DC_SMTP_Settings::password() !== '';
        echo '<div class="dc-field">';
        echo '<label class="dc-label" for="dc_smtp_password">' . esc_html__('Hasło', 'design-cart-smtp') . '</label>';
        echo '<div class="dc-smtp-password">';
        echo '<input class="dc-input" type="password" id="dc_smtp_password" name="' . esc_attr($this->name('password')) . '" value="" autocomplete="new-password" placeholder="' .
            esc_attr($has ? __('Pozostaw puste, aby nie zmieniać', 'design-cart-smtp') : '') . '">';
        echo '<button type="button" class="dc-smtp-password__toggle" id="dc-smtp-toggle-password" aria-label="' .
            esc_attr__('Pokaż hasło', 'design-cart-smtp') . '"><i class="fa fa-eye"></i></button>';
        echo '</div>';
        if ($has) {
            echo '<p class="dc-hint">' . esc_html__('Hasło jest zapisane. Wpisz nowe tylko wtedy, gdy chcesz je zmienić.', 'design-cart-smtp') . '</p>';
        }
        echo '</div>';
    }

    private function name(string $key): string {
        return DC_SMTP_Settings::OPTION_KEY . '[' . $key . ']';
    }

    private function source_label(string $source, string $email_id): string {
        if ($source === 'woocommerce') {
            return $email_id !== '' ? 'WooCommerce · ' . $email_id : 'WooCommerce';
        }
        if ($source === 'test') {
            return __('Test', 'design-cart-smtp');
        }

        return 'WordPress';
    }

    /**
     * @return array<int, string>
     */
    private function conflicting_plugins(): array {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $known = [
            'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
            'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
            'fluent-smtp/fluent-smtp.php' => 'FluentSMTP',
            'post-smtp/postman-smtp.php' => 'Post SMTP',
            'smtp-mailer/main.php' => 'SMTP Mailer',
        ];

        $found = [];
        foreach ($known as $file => $name) {
            if (is_plugin_active($file)) {
                $found[] = $name;
            }
        }

        return $found;
    }

    private function default_test_html(): string {
        $name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);

        return '<div style="font-family:Segoe UI,sans-serif;line-height:1.6;color:#262c38">' .
            '<h2 style="color:#0d6b5c">' . esc_html__('Test Design Cart SMTP', 'design-cart-smtp') . '</h2>' .
            '<p>' . esc_html__('To jest testowa wiadomość HTML. Konfiguracja SMTP działa.', 'design-cart-smtp') . '</p>' .
            '<p style="color:#64748b;font-size:13px">' . esc_html($name) . ' · Design Cart</p></div>';
    }
}
