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

final class DC_SMTP_Mailer {
    /** @var self|null */
    private static $instance = null;

    private string $last_to = '';
    private string $last_subject = '';
    private bool $debug_mode = false;
    private string $debug_buffer = '';
    private string $last_error_info = '';
    /** @var \WP_Error|null */
    private $last_wp_error = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        add_action('phpmailer_init', [$this, 'configure'], 999);
        add_filter('wp_mail', [$this, 'capture_mail']);
        add_filter('wp_mail_from', [$this, 'filter_from'], 999);
        add_filter('wp_mail_from_name', [$this, 'filter_from_name'], 999);
        add_action('wp_mail_failed', [$this, 'on_failed']);
        add_action('wp_mail_succeeded', [$this, 'on_succeeded']);
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    public function capture_mail(array $atts): array {
        $this->last_to = $this->stringify_recipients($atts['to'] ?? '');
        $this->last_subject = (string) ($atts['subject'] ?? '');

        return $atts;
    }

    public function filter_from(string $from): string {
        if (!DC_SMTP_Settings::is_enabled()) {
            return $from;
        }

        $settings = DC_SMTP_Settings::get();
        if ($settings['force_from'] !== '1' || !is_email($settings['from_email'])) {
            return $from;
        }

        return $settings['from_email'];
    }

    public function filter_from_name(string $name): string {
        if (!DC_SMTP_Settings::is_enabled()) {
            return $name;
        }

        $settings = DC_SMTP_Settings::get();
        if ($settings['force_from'] !== '1' || $settings['from_name'] === '') {
            return $name;
        }

        return $settings['from_name'];
    }

    /**
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public function configure($phpmailer): void {
        if (!DC_SMTP_Settings::is_enabled() || !DC_SMTP_Settings::is_configured()) {
            return;
        }

        $settings = DC_SMTP_Settings::get();

        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['host'];
        $phpmailer->Port = (int) $settings['port'];
        $phpmailer->Timeout = 20;

        if ($settings['encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($settings['encryption'] === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
        }

        $phpmailer->SMTPAutoTLS = $settings['autotls'] === '1' && $settings['encryption'] !== 'none';

        if ($settings['auth'] === '1') {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $settings['username'];
            $phpmailer->Password = DC_SMTP_Settings::password();
        } else {
            $phpmailer->SMTPAuth = false;
        }

        if ($settings['ssl_verify'] !== '1') {
            $phpmailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        if ($settings['force_from'] === '1' && is_email($settings['from_email'])) {
            $phpmailer->setFrom(
                $settings['from_email'],
                $settings['from_name'] !== '' ? $settings['from_name'] : $phpmailer->FromName,
                false
            );
        }

        if ($this->debug_mode) {
            $this->debug_buffer = '';
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function ($str) {
                $this->debug_buffer .= $str . "\n";
            };
        }
    }

    public function on_failed(\WP_Error $error): void {
        $this->last_wp_error = $error;
        $message = $error->get_error_message();
        if ($message !== '') {
            $this->last_error_info = $message;
        }

        DC_SMTP_Logger::log(
            $this->last_to,
            $this->last_subject,
            'failed',
            $message
        );
    }

    /**
     * @param array<string, mixed> $mail_data
     */
    public function on_succeeded($mail_data): void {
        $to = $this->last_to;
        $subject = $this->last_subject;

        if (is_array($mail_data)) {
            if ($to === '') {
                $to = $this->stringify_recipients($mail_data['to'] ?? '');
            }
            if ($subject === '') {
                $subject = (string) ($mail_data['subject'] ?? '');
            }
        }

        DC_SMTP_Logger::log($to, $subject, 'success');
    }

    /**
     * @return array{ok:bool, message:string, error:string, errors:array<int, array{code:string, title:string, hint:string}>, debug:string}
     */
    public function send_test(
        string $to,
        string $subject,
        string $message,
        bool $html = true,
        string $source = 'test',
        string $email_id = 'smtp_test'
    ): array {
        $this->debug_mode = true;
        $this->debug_buffer = '';
        $this->last_error_info = '';
        $this->last_wp_error = null;
        DC_SMTP_Logger::set_context($source, $email_id);

        $headers = [];
        if ($html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $sent = wp_mail($to, $subject, $message, $headers);

        global $phpmailer;
        if (is_object($phpmailer) && isset($phpmailer->ErrorInfo) && is_string($phpmailer->ErrorInfo) && $phpmailer->ErrorInfo !== '') {
            $this->last_error_info = $phpmailer->ErrorInfo;
        }

        $debug = $this->redact_debug($this->debug_buffer);
        $error = $this->redact_debug($this->collect_error_text());
        $errors = $this->diagnose($error, $debug);

        $this->debug_mode = false;
        $this->debug_buffer = '';

        if ($sent) {
            return [
                'ok' => true,
                'message' => sprintf(
                    /* translators: %s: recipient email */
                    __('E-mail testowy został wysłany na adres %s.', 'design-cart-smtp'),
                    $to
                ),
                'error' => '',
                'errors' => [],
                'debug' => $debug,
            ];
        }

        $first = $errors[0]['title'] ?? '';

        return [
            'ok' => false,
            'message' => $first !== ''
                ? $first
                : __('Wysyłka nie powiodła się.', 'design-cart-smtp'),
            'error' => $error,
            'errors' => $errors,
            'debug' => $debug,
        ];
    }

    private function collect_error_text(): string {
        $parts = [];

        if ($this->last_error_info !== '') {
            $parts[] = $this->last_error_info;
        }

        if ($this->last_wp_error instanceof \WP_Error) {
            foreach ($this->last_wp_error->get_error_messages() as $message) {
                $parts[] = (string) $message;
            }

            $data = $this->last_wp_error->get_error_data();
            if (is_array($data) && !empty($data['phpmailer_exception_code'])) {
                $parts[] = (string) $data['phpmailer_exception_code'];
            }
        }

        return implode("\n", array_unique(array_filter(array_map('trim', $parts))));
    }

    /**
     * @return array<int, array{code:string, title:string, hint:string}>
     */
    private function diagnose(string $error, string $debug): array {
        $haystack = strtolower($error . "\n" . $debug);
        $found = [];

        $rules = [
            [
                'code' => 'auth',
                'needles' => ['535', '534', 'authentication failed', 'invalid credentials', 'username and password not accepted', 'please log in'],
                'title' => __('Błąd uwierzytelniania (login / hasło).', 'design-cart-smtp'),
                'hint' => __('Sprawdź użytkownika i hasło SMTP. Gmail wymaga hasła aplikacji, nie zwykłego hasła do konta.', 'design-cart-smtp'),
            ],
            [
                'code' => 'auth_required',
                'needles' => ['530', 'authentication required', 'must issue a starttls', '5.7.0'],
                'title' => __('Serwer wymaga logowania lub TLS.', 'design-cart-smtp'),
                'hint' => __('Włącz uwierzytelnianie oraz TLS (port 587) albo SSL (port 465).', 'design-cart-smtp'),
            ],
            [
                'code' => 'connect',
                'needles' => ['could not connect', 'connection refused', 'smtp connect() failed', 'failed to connect', 'network is unreachable', 'no route to host', 'connection timed out', 'timed out'],
                'title' => __('Brak połączenia z hostem SMTP.', 'design-cart-smtp'),
                'hint' => __('Zły host lub port, firewall albo hosting blokuje wychodzące SMTP. Typowo 587 (TLS) lub 465 (SSL).', 'design-cart-smtp'),
            ],
            [
                'code' => 'ssl',
                'needles' => ['certificate', 'stream_socket_enable_crypto', 'peer certificate', 'self signed', 'unable to get local issuer'],
                'title' => __('Problem z certyfikatem SSL/TLS.', 'design-cart-smtp'),
                'hint' => __('Dopasuj szyfrowanie do portu (TLS/587 lub SSL/465). W ostateczności wyłącz „Weryfikuj certyfikat SSL”.', 'design-cart-smtp'),
            ],
            [
                'code' => 'from',
                'needles' => ['550', '553', '554', 'sender', 'from address', 'not allowed to send', 'relay access denied', 'spf', '5.7.1', '5.1.0'],
                'title' => __('Serwer odrzucił nadawcę lub przekazanie (relay).', 'design-cart-smtp'),
                'hint' => __('Adres „Od” musi być skrzynką, na którą się logujesz. Włącz „Wymuś nadawcę”.', 'design-cart-smtp'),
            ],
            [
                'code' => 'recipient',
                'needles' => ['551', '552', 'user unknown', 'mailbox unavailable', 'recipient rejected'],
                'title' => __('Odbiorca został odrzucony przez serwer.', 'design-cart-smtp'),
                'hint' => __('Sprawdź adres testowy. Niektóre serwery nie wysyłają na zewnętrzne skrzynki z konta testowego.', 'design-cart-smtp'),
            ],
            [
                'code' => 'rate',
                'needles' => ['421', '450', '451', '452', 'rate limit', 'try again later', 'too many'],
                'title' => __('Serwer tymczasowo odrzucił wysyłkę.', 'design-cart-smtp'),
                'hint' => __('Limit, przepełniona kolejka albo szara lista. Poczekaj chwilę i wyślij test ponownie.', 'design-cart-smtp'),
            ],
            [
                'code' => 'phpmail',
                'needles' => ['could not instantiate mail function'],
                'title' => __('WordPress nie użył SMTP (spadł na PHP mail).', 'design-cart-smtp'),
                'hint' => __('Włącz SMTP, zapisz ustawienia i uzupełnij host. Wyłącz inne wtyczki SMTP, jeśli są aktywne.', 'design-cart-smtp'),
            ],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['needles'] as $needle) {
                if ($needle !== '' && strpos($haystack, $needle) !== false) {
                    $found[$rule['code']] = [
                        'code' => $rule['code'],
                        'title' => $rule['title'],
                        'hint' => $rule['hint'],
                    ];
                    break;
                }
            }
        }

        if ($found === [] && ($error !== '' || $debug !== '')) {
            $found['unknown'] = [
                'code' => 'unknown',
                'title' => __('Serwer SMTP zwrócił błąd.', 'design-cart-smtp'),
                'hint' => __('Szczegóły są w komunikacie PHPMailer i w logu SMTP poniżej.', 'design-cart-smtp'),
            ];
        }

        if ($error === '' && $debug === '') {
            $found['empty'] = [
                'code' => 'empty',
                'title' => __('wp_mail() zwróciło błąd bez komunikatu.', 'design-cart-smtp'),
                'hint' => __('Inna wtyczka mogła przechwycić pocztę (pre_wp_mail). Wyłącz konkurencyjne SMTP i sprawdź dziennik serwera.', 'design-cart-smtp'),
            ];
        }

        return array_values($found);
    }

    private function redact_debug(string $debug): string {
        $password = DC_SMTP_Settings::password();
        if ($password !== '') {
            $debug = str_replace($password, '********', $debug);
        }

        $debug = preg_replace('/AUTH(?:\s+\S+){1,4}.*/i', 'AUTH ********', $debug) ?? $debug;
        $debug = preg_replace('/Password:\s*.+/i', 'Password: ********', $debug) ?? $debug;

        return trim($debug);
    }

    /**
     * @param mixed $to
     */
    private function stringify_recipients($to): string {
        if (is_array($to)) {
            $parts = [];
            foreach ($to as $item) {
                $parts[] = is_string($item) ? $item : '';
            }

            return implode(', ', array_filter($parts));
        }

        return (string) $to;
    }
}
