=== Design Cart SMTP ===
Contributors: pawelnosko, designcart
Tags: smtp, email, woocommerce, mail, wp_mail
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to: 10.1

Proste przekierowanie poczty WordPress i WooCommerce na SMTP — z testem wysyłki i dziennikiem.

== Description ==

Design Cart SMTP przestawia komunikację e-mail WordPress na serwer SMTP. WooCommerce korzysta z `wp_mail()`, więc zamówienia, konta klientów i powiadomienia sklepu idą tą samą drogą.

**Autor:** Paweł Nosko (Design Cart)
**Firma:** [Design Cart](https://www.designcart.pl/)

Funkcje:

* Host, port, TLS/SSL i logowanie SMTP
* Gotowe profile (Gmail, Outlook, home.pl, nazwa.pl, OVH, cyber_Folks)
* Wymuszenie nadawcy — ważne dla WooCommerce i filtrów antyspamowych
* Test zwykły oraz test w stylu szablonu WooCommerce
* Dziennik ostatnich wysyłek (WordPress / WooCommerce / test)
* Kompatybilność z WooCommerce, w tym HPOS (Custom Order Tables)

== Installation ==

1. Wgraj folder `design-cart-smtp` do `/wp-content/plugins/`.
2. Włącz wtyczkę w menu **Wtyczki**.
3. Przejdź do **Ustawienia → Design Cart SMTP**.
4. Uzupełnij dane serwera, włącz SMTP i wyślij e-mail testowy.

== Frequently Asked Questions ==

= Czy działa z WooCommerce? =

Tak. WooCommerce wysyła maile przez `wp_mail()`. Wtyczka konfiguruje PHPMailer (SMTP) i nadpisuje From, gdy włączysz „Wymuś nadawcę”.

= Czy treść maili sklepu edytuję tutaj? =

Nie. Szablony i włączanie konkretnych wiadomości zostają w **WooCommerce → Ustawienia → E-mail**. Ta wtyczka zmienia tylko transport.

== Changelog ==

= 1.0.0 =
* Pierwsza wersja.
