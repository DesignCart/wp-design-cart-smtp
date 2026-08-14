<h1>Design Cart SMTP</h1>

<p><strong>Route WordPress and WooCommerce mail through a real SMTP mailbox — with diagnostic tests and a send log.</strong></p>

<h2>GitHub About / short description</h2>
<p>Paste into the repository <strong>About</strong> description field:</p>
<p>WordPress SMTP plugin with diagnostic test emails, a send log, and WooCommerce support. Presets for Gmail, Outlook, and Polish hosts. By Paweł Nosko / Design Cart.</p>

<hr>

<h2>Links</h2>
<ul>
  <li><strong>Download (Design Cart):</strong> <a href="https://www.designcart.pl/laboratorium/355-jak-skonfigurowac-smtp-w-wordpress-darmowy-plugin-testowaniem-polaczenia.html">https://www.designcart.pl/laboratorium/355-jak-skonfigurowac-smtp-w-wordpress-darmowy-plugin-testowaniem-polaczenia.html</a></li>
   
  <li><strong>Documentation / project page:</strong> <a href="https://www.designcart.pl/laboratorium/355-jak-skonfigurowac-smtp-w-wordpress-darmowy-plugin-testowaniem-polaczenia.html">https://www.designcart.pl/laboratorium/355-jak-skonfigurowac-smtp-w-wordpress-darmowy-plugin-testowaniem-polaczenia.html</a></li>
  <li><strong>Author — Paweł Nosko:</strong> <a href="https://www.designcart.pl/pawel-nosko.html">https://www.designcart.pl/pawel-nosko.html</a></li>
  <li><strong>Studio — Design Cart:</strong> <a href="https://www.designcart.pl/">https://www.designcart.pl/</a></li>
</ul>

<hr>

<h2>What it is</h2>
<p>WordPress sends mail with PHP <code>mail()</code> by default. Hosts and inbox providers treat that as unauthenticated traffic, so contact-form messages and WooCommerce order emails bounce or land in spam.</p>
<p>More and more hosts disable or throttle <code>mail()</code> on purpose. Spammers break into WordPress sites (outdated plugins, nulled themes, stolen FTP) and fire thousands of messages through <code>mail()</code> with no mailbox login — poisoning the shared server IP. Operators then block the function for everyone. SMTP is what they expect instead: authenticated sending from a real mailbox, not an anonymous PHP script.</p>
<p><strong>Design Cart SMTP</strong> switches <code>wp_mail()</code> / PHPMailer to SMTP: host, port, TLS or SSL, and mailbox login. WooCommerce already uses <code>wp_mail()</code>, so shop mail follows the same path. Templates stay in WooCommerce — this plugin only changes transport.</p>
<p>Full documentation (Polish): <a href="https://www.designcart.pl/laboratorium/355-jak-skonfigurowac-smtp-w-wordpress-darmowy-plugin-testowaniem-polaczenia.html">Jak skonfigurować SMTP w WordPress? Darmowy plugin z testowaniem połączenia</a>.</p>
<p>Author: <a href="https://www.designcart.pl/pawel-nosko.html"><strong>Paweł Nosko</strong></a> · Company: <a href="https://www.designcart.pl/"><strong>Design Cart</strong></a> · License: GPL-2.0-or-later · Version: 1.0.0</p>

<h2>Why it is different</h2>
<p>Most SMTP plugins stop at “send a test”. This one is built for shops that have to <em>know</em> why mail failed:</p>
<ul>
  <li><strong>Diagnostic test</strong> — standard test plus a WooCommerce-style test (store email wrapper, no fake order). Failures return a mapped error list, the raw PHPMailer <code>ErrorInfo</code>, and a full SMTP transcript (password redacted).</li>
  <li><strong>Send log</strong> — last 100 messages with date, recipient, subject, source (WordPress / WooCommerce / test), status, and error text.</li>
  <li><strong>Provider presets</strong> — Gmail, Outlook, home.pl, nazwa.pl, OVH, cyber_Folks (host, port, encryption filled in; you type login and password).</li>
  <li><strong>Force From</strong> — overrides WooCommerce From so the envelope matches the SMTP mailbox (avoids 550 / relay denials).</li>
  <li><strong>WooCommerce-aware</strong> — status card, list of shop email types, link to native WC email settings, HPOS compatibility declared.</li>
</ul>

<h2>Requirements</h2>
<ul>
  <li>WordPress 6.2+</li>
  <li>PHP 7.4+</li>
  <li>WooCommerce optional (8.0+ if used; HPOS compatible)</li>
  <li>SMTP credentials from your host or mail provider</li>
</ul>
<p>Disable other SMTP plugins (WP Mail SMTP, Easy WP SMTP, FluentSMTP, Post SMTP, SMTP Mailer). Two mailers fighting over PHPMailer will break tests.</p>

<h2>Installation</h2>
<ol>
  <li>Download the ZIP from Design Cart or this GitHub repository.</li>
  <li>WordPress → <strong>Plugins → Add Plugin → Upload Plugin</strong>.</li>
  <li>Upload the ZIP (root folder must be <code>design-cart-smtp</code> with <code>design-cart-smtp.php</code> inside).</li>
  <li>Activate <strong>Design Cart SMTP</strong>.</li>
  <li>Open <strong>Settings → Design Cart SMTP</strong>.</li>
</ol>
<p>Manual / FTP: copy <code>design-cart-smtp/</code> into <code>/wp-content/plugins/</code>, then activate. Activation creates the log table <code>{prefix}dc_smtp_log</code>.</p>

<h2>Quick start</h2>
<ol>
  <li><strong>General</strong> — enable SMTP, keep the log on, set From email / name, leave Force From on.</li>
  <li><strong>SMTP server</strong> — pick a preset or type host, encryption, port, username, password.</li>
  <li>Click <strong>Save</strong> (hero or footer). Tests use saved settings only.</li>
  <li><strong>Test</strong> — send a test to an inbox you control. If WooCommerce is active, also run the WooCommerce-style test.</li>
  <li>Read the result: mapped errors, PHPMailer message, SMTP log. Check <strong>Log</strong> for the row.</li>
</ol>

<h2>Settings screens</h2>
<p>Admin UI is Polish. Tab names below are English equivalents.</p>

<h3>General (Ogólne)</h3>
<p><img src="https://www.designcart.pl/images/laboratorium/dc_wp_smtp/1-tab-ogolne.webp" alt="General tab — status cards and SMTP toggles"></p>
<dl>
  <dt><strong>Status cards</strong> (read-only)</dt>
  <dd>SMTP on/off, WooCommerce detected or not, last send success/error/none (subject from the log).</dd>
  <dt><strong>Enable SMTP</strong></dt>
  <dd>Master switch. Off = default PHP mail. On = PHPMailer uses the SMTP server. Applies to WordPress and WooCommerce.</dd>
  <dt><strong>Save send log</strong></dt>
  <dd>Keeps the last 100 messages. Turning it off stops new rows; it does not wipe the table.</dd>
  <dt><strong>From email</strong> (required)</dt>
  <dd>Envelope sender, e.g. <code>shop@yourdomain.com</code>. Must usually match the SMTP login.</dd>
  <dt><strong>From name</strong></dt>
  <dd>Display name next to the address (defaults to the site title).</dd>
  <dt><strong>Force From</strong></dt>
  <dd>On by default. Overwrites From set by WooCommerce and other plugins so the SMTP server does not reject the message.</dd>
</dl>

<h3>SMTP server (Serwer SMTP)</h3>
<p><img src="https://www.designcart.pl/images/laboratorium/dc_wp_smtp/2-tab-serwer-smtp.webp" alt="SMTP server tab — provider presets and connection fields"></p>
<dl>
  <dt><strong>Provider</strong></dt>
  <dd>Presets: Custom, Gmail (<code>smtp.gmail.com</code>, TLS, 587), Outlook, home.pl, nazwa.pl (SSL, 465), OVH (SSL, 465), cyber_Folks. Fills host / port / encryption only. Gmail typically needs an app password.</dd>
  <dt><strong>SMTP host</strong> (required)</dt>
  <dd>Server hostname from your host panel (may differ from the preset, e.g. <code>s140.cyber-folks.pl</code> — use Custom).</dd>
  <dt><strong>Encryption</strong></dt>
  <dd>None / SSL / TLS. Changing it auto-fills the usual port (25 / 465 / 587).</dd>
  <dt><strong>Port</strong> (required)</dt>
  <dd>Typically 587 (TLS) or 465 (SSL).</dd>
  <dt><strong>Auto TLS</strong></dt>
  <dd>STARTTLS when the server offers it. Forced off if encryption is None.</dd>
  <dt><strong>Verify SSL certificate</strong></dt>
  <dd>On by default. Turn off only for a broken or self-signed certificate on the host.</dd>
  <dt><strong>Require login</strong></dt>
  <dd>Almost always on. Shows username and password when enabled.</dd>
  <dt><strong>Username</strong></dt>
  <dd>Usually the full mailbox address.</dd>
  <dt><strong>Password</strong></dt>
  <dd>Stored encrypted. Leave empty on later saves to keep the current password. Eye icon shows/hides the field.</dd>
</dl>

<h3>Test</h3>
<p><img src="https://www.designcart.pl/images/laboratorium/dc_wp_smtp/3-tab-test.webp" alt="Test tab — recipient, subject, body, send buttons"></p>
<p>Sends through the same SMTP path as live mail. Does not create WooCommerce orders.</p>
<dl>
  <dt><strong>Recipient</strong> (required)</dt>
  <dd>Defaults to the WordPress admin email. Use an inbox you can open (ideally not the From address).</dd>
  <dt><strong>Subject</strong></dt>
  <dd>Default: <code>[Site name] Test Design Cart SMTP</code>.</dd>
  <dt><strong>Body</strong></dt>
  <dd>Used for the standard HTML test. The WooCommerce-style test wraps a store template instead.</dd>
  <dt><strong>Send test email</strong></dt>
  <dd>Standard <code>wp_mail()</code> test. Log source: Test.</dd>
  <dt><strong>WooCommerce-style test</strong></dt>
  <dd>Visible when WooCommerce is active. Uses <code>WC()->mailer()-&gt;wrap_message()</code>. Log source: WooCommerce · <code>dc_smtp_wc_test</code>.</dd>
</dl>
<p>After send you get:</p>
<ol>
  <li>Success or failure headline.</li>
  <li><strong>Mapped errors</strong> — auth (535), TLS required (530), connection/timeout, SSL certificate, sender/relay (550/553/554), recipient rejected, rate limit, fell back to PHP mail, SMTP off / incomplete config, empty error (another plugin hijacked <code>wp_mail</code>).</li>
  <li><strong>PHPMailer message</strong> — raw <code>ErrorInfo</code>, password redacted.</li>
  <li><strong>SMTP log</strong> — client/server transcript (EHLO, STARTTLS, AUTH, MAIL FROM, RCPT TO).</li>
</ol>

<h3>WooCommerce</h3>
<p><img src="https://www.designcart.pl/images/laboratorium/dc_wp_smtp/4-tab-woocommerce.webp" alt="WooCommerce tab — compatibility status and shop email list"></p>
<dl>
  <dt><strong>Compatibility</strong></dt>
  <dd>Confirms WooCommerce is active and transactional mail uses this SMTP. Content and templates stay under <strong>WooCommerce → Settings → Emails</strong> (button on this tab). Enable Force From if the server requires From = login.</dd>
  <dt><strong>Shop messages</strong></dt>
  <dd>Table of WC email types: title, ID (e.g. <code>new_order</code>), enabled/disabled. Disabled types will not send even with perfect SMTP. The plugin does not toggle them.</dd>
</dl>
<p>A status notice is also shown on the WooCommerce Emails settings screen.</p>

<h3>Log (Dziennik)</h3>
<p><img src="https://www.designcart.pl/images/laboratorium/dc_wp_smtp/5-tab-dziennik.webp" alt="Log tab — recent sends with OK and error rows"></p>
<dl>
  <dt><strong>Clear log</strong></dt>
  <dd>Deletes all rows. Does not turn logging off.</dd>
  <dt><strong>Date</strong></dt>
  <dd>WordPress timezone.</dd>
  <dt><strong>To</strong></dt>
  <dd>Recipient(s).</dd>
  <dt><strong>Subject</strong></dt>
  <dd>Message subject.</dd>
  <dt><strong>Source</strong></dt>
  <dd>Test, WordPress, or <code>WooCommerce · {email id}</code>.</dd>
  <dt><strong>Status</strong></dt>
  <dd>OK or Error, with the SMTP error text under failures.</dd>
</dl>
<p>The log is a diagnostic trail, not a marketing archive. It does not store message bodies. Cap: 100 rows.</p>

<h2>WooCommerce notes</h2>
<ul>
  <li>Shop mail goes through <code>wp_mail()</code> → this SMTP setup.</li>
  <li>Force From maps <code>woocommerce_email_from_address</code> / <code>woocommerce_email_from_name</code>.</li>
  <li>Reply-To (e.g. customer address on new-order admin mail) is left intact.</li>
  <li>HPOS (custom order tables) and cart/checkout blocks compatibility are declared.</li>
</ul>

<h2>Troubleshooting</h2>
<dl>
  <dt>Authentication failed</dt>
  <dd>Wrong user/password. Login is usually the full email. Gmail: app password. Re-enter the password and Save — an empty field keeps the stored value.</dd>
  <dt>Could not connect</dt>
  <dd>Bad host, wrong port, or the host blocks outbound 465/587. Try TLS/587 vs SSL/465.</dd>
  <dt>SSL certificate error</dt>
  <dd>Match encryption to the port. Last resort: disable certificate verification and fix the cert on the host.</dd>
  <dt>Sender rejected</dt>
  <dd>From ≠ SMTP login. Enable Force From.</dd>
  <dt>Test OK, shop mail silent</dt>
  <dd>That email type may be disabled in WooCommerce. Check the WooCommerce tab. Watch for another plugin on <code>pre_wp_mail</code>.</dd>
</dl>

<h2>Uninstall</h2>
<p>Removing the plugin deletes the settings option and drops <code>{prefix}dc_smtp_log</code>.</p>

<h2>License</h2>
<p>GPL-2.0-or-later. Author: <a href="https://www.designcart.pl/pawel-nosko.html">Paweł Nosko</a> / <a href="https://www.designcart.pl/">Design Cart</a>. Project page: <a href="https://www.designcart.pl/laboratorium/355-jak-skonfigurowac-smtp-w-wordpress-darmowy-plugin-testowaniem-polaczenia.html">Design Cart SMTP on designcart.pl</a>.</p>
