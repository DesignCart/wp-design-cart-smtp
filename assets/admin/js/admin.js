/**
 * Design Cart SMTP
 * Autor: Paweł Nosko
 * Firma: Design Cart
 * Adres: https://www.designcart.pl/
 */

(function ($) {
  "use strict";

  var cfg = window.dcSmtpAdmin || {};
  var ports = { none: "25", ssl: "465", tls: "587" };

  function i18n(key, fallback) {
    return (cfg.i18n && cfg.i18n[key]) || fallback;
  }

  function setEncryption(value) {
    var input = document.querySelector('.dc-smtp-encryption[value="' + value + '"]');
    if (input) {
      input.checked = true;
    }
  }

  function applyPreset(key) {
    var presets = cfg.presets || {};
    var preset = presets[key];
    if (!preset) {
      return;
    }

    if (preset.host) {
      $("#dc_smtp_host").val(preset.host);
    }
    if (preset.encryption) {
      setEncryption(preset.encryption);
    }
    if (preset.port) {
      $("#dc_smtp_port").val(preset.port);
    }
  }

  function toggleAuthFields() {
    var on = $('input[name="dc_smtp_settings[auth]"]').is(":checked");
    $(".dc-smtp-auth-fields").css("display", on ? "" : "none");
  }

  function escapeHtml(text) {
    return $("<div>").text(text || "").html();
  }

  function showResult(ok, payload) {
    var $result = $("#dc-smtp-test-result");
    var $errors = $("#dc-smtp-errors");
    var $info = $("#dc-smtp-error-info");
    var $debug = $("#dc-smtp-debug");
    var $debugWrap = $("#dc-smtp-debug-wrap");
    var message = (payload && payload.message) || i18n("error", "Błąd");
    var errors = (payload && payload.errors) || [];
    var error = (payload && payload.error) || "";
    var debug = (payload && payload.debug) || "";

    $result
      .removeAttr("hidden")
      .removeClass("dc-smtp-flash--ok dc-smtp-flash--err")
      .addClass("dc-smtp-flash " + (ok ? "dc-smtp-flash--ok" : "dc-smtp-flash--err"))
      .html(
        '<i class="fa ' +
          (ok ? "fa-check-circle" : "fa-times-circle") +
          '"></i> ' +
          escapeHtml(message)
      );

    if (!ok && errors.length) {
      var html = "<h4>" + escapeHtml(i18n("errorList", "Rozpoznane błędy")) + "</h4><ol>";
      errors.forEach(function (item) {
        html +=
          "<li><strong>" +
          escapeHtml(item.title || "") +
          "</strong>" +
          (item.hint ? '<span class="dc-smtp-errors__hint">' + escapeHtml(item.hint) + "</span>" : "") +
          "</li>";
      });
      html += "</ol>";
      $errors.removeAttr("hidden").html(html);
    } else {
      $errors.attr("hidden", "hidden").empty();
    }

    if (!ok && error) {
      $info
        .removeAttr("hidden")
        .html(
          "<h4>" +
            escapeHtml(i18n("phpmailer", "Komunikat PHPMailer")) +
            "</h4><pre>" +
            escapeHtml(error) +
            "</pre>"
        );
    } else {
      $info.attr("hidden", "hidden").empty();
    }

    if (debug) {
      $debugWrap.removeAttr("hidden");
      $debug.text(debug);
    } else {
      $debugWrap.attr("hidden", "hidden");
      $debug.text("");
    }
  }

  function sendTest(mode, $button, idleLabel) {
    var to = $("#dc-smtp-test-to").val();
    var subject = $("#dc-smtp-test-subject").val();
    var message = $("#dc-smtp-test-message").val();

    $button.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> ' + i18n("sending", "Wysyłanie…"));

    $.post(cfg.ajaxUrl, {
      action: "dc_smtp_send_test",
      nonce: cfg.nonce,
      to: to,
      subject: subject,
      message: message,
      mode: mode,
    })
      .done(function (res) {
        var payload = (res && res.data) || {};
        showResult(!!(res && res.success), payload);
      })
      .fail(function () {
        showResult(false, { message: i18n("error", "Nie udało się wykonać żądania.") });
      })
      .always(function () {
        $button.prop("disabled", false).html(idleLabel);
      });
  }

  $(function () {
    toggleAuthFields();
    $('input[name="dc_smtp_settings[auth]"]').on("change", toggleAuthFields);

    $(".dc-smtp-presets input").on("change", function () {
      applyPreset(this.value);
    });

    $(document).on("change", ".dc-smtp-encryption", function () {
      if (this.checked && ports[this.value]) {
        $("#dc_smtp_port").val(ports[this.value]);
      }
    });

    $("#dc-smtp-toggle-password").on("click", function () {
      var $input = $("#dc_smtp_password");
      var show = $input.attr("type") === "password";
      $input.attr("type", show ? "text" : "password");
      $(this).find("i").toggleClass("fa-eye fa-eye-slash");
    });

    $("#dc-smtp-test-to, #dc-smtp-test-subject").on("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        $("#dc-smtp-send-test").trigger("click");
      }
    });

    $("#dc-smtp-send-test").on("click", function () {
      sendTest(
        "standard",
        $(this),
        '<i class="fa fa-paper-plane"></i> ' + i18n("sendTest", "Wyślij e-mail testowy")
      );
    });

    $("#dc-smtp-send-wc").on("click", function () {
      sendTest(
        "woocommerce",
        $(this),
        '<i class="fa fa-shopping-cart"></i> ' + i18n("sendWc", "Test w stylu WooCommerce")
      );
    });

    $("#dc-smtp-clear-log").on("click", function () {
      var $button = $(this);
      $button.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> ' + i18n("clearing", "Czyszczenie…"));

      $.post(cfg.ajaxUrl, {
        action: "dc_smtp_clear_log",
        nonce: cfg.nonce,
      })
        .done(function (res) {
          if (res && res.success) {
            $("#dc-smtp-log-wrap").html(
              '<p class="dc-hint" style="padding:1rem;">' +
                $("<div>").text((res.data && res.data.message) || i18n("cleared", "Dziennik został wyczyszczony.")).html() +
                "</p>"
            );
          }
        })
        .always(function () {
          $button.prop("disabled", false).html('<i class="fa fa-trash-o"></i> Wyczyść dziennik');
        });
    });
  });
})(jQuery);
