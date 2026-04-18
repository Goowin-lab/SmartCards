/**
 * Validaciones en el lado del cliente
 * para limitar imágenes (perfil a 2MB y portada a 5MB)
 */
// Helper de traducciones con fallback (evita "undefined")
window.smartcardsL10n = window.smartcardsL10n || {};
function t(key, def) {
  try {
    var v = window.smartcardsL10n[key];
    return v === undefined || v === null || v === "" ? def : String(v);
  } catch (e) {
    return def;
  }
}

var __scNonCriticalTasksStarted = false;
window.__scBuyWithIAP = null;

function waitForSmartcardsUserAndBlock() {
  let attempts = 0;

  const interval = setInterval(function () {
    attempts++;

    if (typeof window.smartcardsUser !== "undefined") {
      if (Number(window.smartcardsUser.credits) <= 0) {
        const form = document.getElementById("smartcards-form");

        if (form) {
          form.style.opacity = "0.3";
          form.style.pointerEvents = "none";
        }
      }

      clearInterval(interval);
    }

    if (attempts > 50) {
      clearInterval(interval);
    }
  }, 50);
}

waitForSmartcardsUserAndBlock();

document.addEventListener("click", function (e) {
  const btn = e.target.closest('.sc-google-btn[href*="add-to-cart"]');
  if (!btn) return;

  e.preventDefault();

  const url = btn.getAttribute("href");

  fetch(url, { credentials: "same-origin" }).then(() => {
    window.location.href = "/carrito/";
  });
});

function initShare() {
  document.querySelectorAll(".share-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const perfilURL = this.getAttribute("data-url");

      const popupHTML =
        '<div class="share-popup-overlay">' +
        '<div class="share-popup">' +
        "<h3>" +
        smartcardsL10n.share_title +
        "</h3>" +
        '<button class="share-option whatsapp">' +
        smartcardsL10n.whatsapp +
        "</button>" +
        '<button class="share-option copiar">' +
        smartcardsL10n.copy_link +
        "</button>" +
        '<button class="share-option email">' +
        smartcardsL10n.email +
        "</button>" +
        '<button class="close-popup">' +
        smartcardsL10n.close +
        "</button>" +
        "</div>" +
        "</div>";

      document.body.insertAdjacentHTML("beforeend", popupHTML);

      document.querySelector(".share-option.whatsapp").onclick = () => {
        const text = encodeURIComponent(perfilURL);
        const ua = navigator.userAgent || "";
        const link = /Android/i.test(ua)
          ? `whatsapp://send?text=${text}`
          : `https://api.whatsapp.com/send?text=${text}`;
        window.location.href = link;
        closePopup();
      };

      document.querySelector(".share-option.copiar").onclick = () => {
        navigator.clipboard.writeText(perfilURL).then(() => {
          alert(smartcardsL10n.copied_link);
          closePopup();
        });
      };

      document.querySelector(".share-option.email").onclick = () => {
        window.location.href =
          "mailto:?subject=" +
          encodeURIComponent(smartcardsL10n.email_subject) +
          "&body=" +
          encodeURIComponent(smartcardsL10n.email_body + " " + perfilURL);
        closePopup();
      };

      document.querySelector(".close-popup").onclick = closePopup;

      function closePopup() {
        var overlay = document.querySelector(".share-popup-overlay");
        if (overlay) overlay.remove();
      }
    });
  });
}

function initOptions() {
  document.querySelectorAll(".options-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const perfilURL = this.getAttribute("data-url");

      const popupHTML =
        '<div class="options-popup-overlay">' +
        '<div class="options-popup">' +
        '<button class="delete">' +
        smartcardsL10n.delete_card +
        "</button>" +
        '<button class="cancel">' +
        smartcardsL10n.cancel +
        "</button>" +
        "</div>" +
        "</div>";

      document.body.insertAdjacentHTML("beforeend", popupHTML);

      document.querySelector(".delete").onclick = () => {
        if (confirm(smartcardsL10n.confirm_delete)) {
          fetch(`${smartcardsL10n.ajax_url}?action=borrar_smartcard_perfil`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ perfil_url: perfilURL }),
          })
            .then((r) => r.json())
            .then((response) => {
              if (response.success) {
                alert(smartcardsL10n.deleted_success);
                location.reload();
              } else {
                alert(smartcardsL10n.deleted_error);
              }
            })
            .catch((err) => {
              console.error(err);
              alert(smartcardsL10n.request_error);
            });
        }
      };

      document.querySelector(".cancel").onclick = () => {
        var overlay = document.querySelector(".options-popup-overlay");
        if (overlay) overlay.remove();
      };
    });
  });
}

function initInvite() {
  const inviteBtn = document.querySelector(".invite-whatsapp");
  if (!inviteBtn) return;

  inviteBtn.addEventListener("click", function (e) {
    e.preventDefault();

    const ua = navigator.userAgent || "";
    const link = /Android/i.test(ua)
      ? this.href.replace("https://api.whatsapp.com/", "whatsapp://")
      : this.href;

    window.location.href = link;
  });
}

function runNonCriticalTasks() {
  if (__scNonCriticalTasksStarted) return;
  __scNonCriticalTasksStarted = true;

  const slider = document.querySelector(".smartcards-slider");
  if (slider) {
    let isDown = false;
    let startX;
    let scrollLeft;

    slider.addEventListener("mousedown", (e) => {
      isDown = true;
      slider.classList.add("active");
      startX = e.pageX - slider.offsetLeft;
      scrollLeft = slider.scrollLeft;
    });

    slider.addEventListener("mouseleave", () => {
      isDown = false;
      slider.classList.remove("active");
    });

    slider.addEventListener("mouseup", () => {
      isDown = false;
      slider.classList.remove("active");
    });

    slider.addEventListener("mousemove", (e) => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - slider.offsetLeft;
      const walk = (x - startX) * 2;
      slider.scrollLeft = scrollLeft - walk;
    });
  }

  initShare();
  initOptions();
  initInvite();

  const isFn = (f) => typeof f === "function";
  const isIOS = () => /iPhone|iPad|iPod/i.test(navigator.userAgent);

  const N = window.Natively || window.natively || {};
  const IAP = N.iap || N.purchases || N;

  const buyVariants = [
    (sku) =>
      IAP?.iap && isFn(IAP.iap.buy) ? IAP.iap.buy({ productId: sku }) : null,
    (sku) =>
      IAP?.iap && isFn(IAP.iap.purchase) ? IAP.iap.purchase(sku) : null,
    (sku) => (isFn(IAP.purchase) ? IAP.purchase(sku) : null),
    (sku) => (isFn(IAP.buy) ? IAP.buy({ productId: sku }) : null),
  ];

  async function buyWithIAP(sku, fallbackUrl) {
    const RC = window.Purchases || window.revenuecat || window.RC;
    const hasRC = !!(
      RC &&
      (isFn(RC.purchaseProduct) || isFn(RC.purchasePackage))
    );
    const hasIAP = buyVariants.some((fn) => isFn(fn));
    const onIOS = isIOS();

    console.log("[IAP] start", { sku, onIOS, hasRC, hasIAP, fallbackUrl });

    if (hasRC) {
      try {
        const res = RC.purchaseProduct
          ? await RC.purchaseProduct(sku)
          : await RC.purchasePackage(sku);
        console.log("[IAP] RC OK", res);
        window.dispatchEvent(
          new CustomEvent("sc_iap_completed", {
            detail: {
              productId: sku,
              transactionId:
                res?.transactionIdentifier ||
                res?.originalTransactionId ||
                null,
              platform: /Android/i.test(navigator.userAgent)
                ? "android"
                : "ios",
              receipt: res?.appStoreReceipt || null,
              purchaseToken: res?.purchaseInfo?.purchaseToken || null,
            },
          }),
        );
        return;
      } catch (e) {
        console.warn("[IAP] RC error", e);
      }
    }

    if (hasIAP) {
      for (const tryBuy of buyVariants) {
        try {
          if (!isFn(tryBuy)) continue;
          const r = await tryBuy(sku);
          console.log("[IAP] Natively OK", r);
          const txId =
            r?.transactionId ||
            r?.originalTransactionId ||
            r?.transactionID ||
            null;
          const receipt = r?.receipt || r?.appStoreReceipt || null;
          window.dispatchEvent(
            new CustomEvent("sc_iap_completed", {
              detail: {
                productId: sku,
                transactionId: txId,
                platform: /Android/i.test(navigator.userAgent)
                  ? "android"
                  : "ios",
                receipt,
                purchaseToken: r?.purchaseToken || r?.token || null,
              },
            }),
          );
          return;
        } catch (e) {
          console.warn("[IAP] intento de compra falló", e);
        }
      }
      if (onIOS) {
        alert(
          "No se pudo iniciar la compra In-App. Verifica que IAP esté activo en el build y que el Product ID exista: " +
            sku,
        );
        return;
      }
    }

    try {
      const okNP = await buyViaNativelyPurchases(sku);
      if (okNP) return;
    } catch (e) {
      console.warn("[IAP] NativelyPurchases error", e);
    }

    if (onIOS) {
      alert(
        "La compra debe completarse como Compra In-App en iOS. Revisa SKUs y que el build tenga IAP habilitado.",
      );
      return;
    }

    if (fallbackUrl) window.location.href = fallbackUrl;
  }

  window.__scBuyWithIAP = buyWithIAP;

  document.addEventListener("click", function (ev) {
    const btn = ev.target.closest(".js-iap-purchase");
    if (!btn) return;
    ev.preventDefault();

    const sku = btn.getAttribute("data-sku");
    let fb = btn.getAttribute("data-fallback");

    if (isIOS()) {
      btn.removeAttribute("data-fallback");
      fb = "";
    }

    btn.disabled = true;
    btn.classList?.add("is-loading");

    buyWithIAP(sku, fb).finally(() => {
      btn.disabled = false;
      btn.classList?.remove("is-loading");
    });
  });

  document.addEventListener("click", function (ev) {
    if (ev.target.closest(".js-open-credit-options")) {
      const group = document.querySelector(".sc-btn-stack--options");
      if (group) group.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  });

  const appleBtn = document.getElementById("sc-apple-login");
  if (appleBtn) {
    if (
      !(
        window.Natively &&
        window.Natively.auth &&
        typeof window.Natively.auth.apple === "function"
      )
    ) {
      appleBtn.style.display = "none";
    } else {
      appleBtn.addEventListener("click", async () => {
        try {
          const r = await window.Natively.auth.apple();
          const res = await fetch("/wp-json/sc/v1/oauth/apple", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({
              authorizationCode: r.authorizationCode,
              identityToken: r.identityToken,
              email: r.user?.email || null,
              fullName: r.user?.name || null,
            }),
          });
          const j = await res.json();
          if (j.ok) window.location.href = "/app-dashboard/";
          else alert("No pudimos ingresar con Apple: " + (j.error || ""));
        } catch (e) {
          console.warn("Apple Sign-In error", e);
          alert("No pudimos completar el ingreso con Apple.");
        }
      });
    }
  }
}

// Tracking de analíticas del perfil público
function initSmartCardTracking() {
  if (window.__scProfileTrackingInit) return;
  window.__scProfileTrackingInit = true;

  var TRACK_ACTION = "sc_track_event";

  function trackEvent(payload) {
    try {
      var l10n = window.smartcardsL10n || {};
      if (!l10n.ajax_url || !l10n.track_nonce) return;
      if (!payload || !payload.profile_id || !payload.event_type) return;
      var url = l10n.ajax_url + "?action=" + TRACK_ACTION;

      var body = {
        nonce: l10n.track_nonce,
        profile_id: Number(payload.profile_id),
        event_type: String(payload.event_type),
      };

      if (payload.button_key) body.button_key = String(payload.button_key);
      if (payload.url) body.url = String(payload.url);
      var json = JSON.stringify(body);

      if (typeof navigator.sendBeacon === "function") {
        var blob = new Blob([json], { type: "application/json" });
        navigator.sendBeacon(url, blob);
        return;
      }

      fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        keepalive: true,
        body: json,
      }).catch(function (err) {
        console.warn("[SmartCards] trackEvent fetch error", err);
      });
    } catch (err) {
      console.warn("[SmartCards] trackEvent error", err);
    }
  }

  function initProfileTracking() {
    var profileRoot = document.querySelector(
      ".perfil-publico[data-sc-profile-id]",
    );
    if (!profileRoot) return;

    var profileId = parseInt(
      profileRoot.getAttribute("data-sc-profile-id") || "",
      10,
    );
    if (!profileId) return;

    // 1) profile_view una sola vez por sesión
    var viewedKey = "sc_viewed_" + profileId;
    var shouldTrackView = true;
    try {
      if (window.sessionStorage && sessionStorage.getItem(viewedKey)) {
        shouldTrackView = false;
      }
    } catch (err) {
      console.warn("[SmartCards] sessionStorage unavailable", err);
    }
    if (shouldTrackView) {
      trackEvent({ profile_id: profileId, event_type: "profile_view" });
      try {
        if (window.sessionStorage) sessionStorage.setItem(viewedKey, "1");
      } catch (err) {
        console.warn("[SmartCards] sessionStorage set failed", err);
      }
    }

    // Guardar contacto (delegación segura)
    document.addEventListener(
      "click",
      function (e) {
        var btn = e.target.closest("a.btn-contacto-link");
        if (!btn) return;

        var profileRoot = document.querySelector(
          ".perfil-publico[data-sc-profile-id]",
        );
        if (!profileRoot) return;

        var profileId = parseInt(
          profileRoot.getAttribute("data-sc-profile-id") || "",
          10,
        );
        if (!profileId) return;

        var href = btn.getAttribute("href") || "";

        trackEvent({
          profile_id: profileId,
          event_type: "save_contact_click",
          url: href,
        });
      },
      false,
    );

    // 3) button_click en redes sociales
    var socialBtns = document.querySelectorAll("a.btn-red-social");

    for (var i = 0; i < socialBtns.length; i++) {
      (function (btn) {
        btn.addEventListener(
          "click",
          function (e) {
            e.preventDefault();

            var href = btn.getAttribute("href") || "";

            var classes = btn.className.split(" ");
            var buttonKey = "";

            for (var j = 0; j < classes.length; j++) {
              if (classes[j] !== "btn-red-social") {
                buttonKey = classes[j];
                break;
              }
            }

            trackEvent({
              profile_id: profileId,
              event_type: "button_click",
              button_key: buttonKey,
              url: href,
            });

            // Pequeño delay para asegurar envío en móviles
            setTimeout(function () {
              window.open(href, "_blank");
            }, 180);
          },
          false,
        );
      })(socialBtns[i]);
    }
  }

  initProfileTracking();
}

function initFormValidation() {
  // Validar foto de perfil
  var inputFile = document.getElementById("profile_picture");
  if (inputFile) {
    inputFile.addEventListener("change", function () {
      var file = this.files[0];
      if (file && file.size > 2 * 1024 * 1024) {
        alert(smartcardsL10n.img_too_big_profile);
        this.value = "";
      }
    });
  }

  // Validar foto de portada
  var inputPortada = document.getElementById("portada");
  if (inputPortada) {
    inputPortada.addEventListener("change", function () {
      var file = this.files[0];
      if (file && file.size > 5 * 1024 * 1024) {
        alert(smartcardsL10n.img_too_big_cover);
        this.value = ""; // Resetea el campo
      }
    });
  }

  if ("requestIdleCallback" in window) {
    requestIdleCallback(runNonCriticalTasks);
  } else {
    setTimeout(runNonCriticalTasks, 1000);
  }
}

// Lógica del botón "Generar Perfil Público"
const formulario = document.getElementById("smartcards-form");
const boton = document.getElementById("btn-generar-perfil");

if (formulario && boton) {
  formulario.addEventListener("submit", function (e) {
    e.preventDefault();

    // Mensaje con contador 15s
    scStartGenerating(boton, 15);
    scAnnounce(
      "Creando Perfil Público. Tiempo de espera aproximado: 15 segundos.",
    );
    scArmHardTimeout(boton, 60000); // 60s de tope

    var formData = new FormData(formulario);

    fetch(smartcardsL10n.ajax_url + "?action=procesar_formulario", {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (response) {
        if (boton._scHardTO) {
          clearTimeout(boton._scHardTO);
          boton._scHardTO = null;
        }

        if (response && response.success) {
          scAnnounce("Perfil creado con éxito.");

          var perfil_url =
            (response &&
              response.data &&
              (response.data.perfil_url ||
                response.data.public_url ||
                response.data.permalink)) ||
            null;

          if (!perfil_url) {
            console.error("❌ No se encontró perfil_url válido", response);
            alert("Error generando el perfil. Intenta nuevamente.");
            return;
          }

          console.log("🔗 URL recibida:", perfil_url);
          console.log("📦 Respuesta completa:", response);

          // Guardar en sesión (no bloqueante)
          fetch(smartcardsL10n.ajax_url + "?action=guardar_url_perfil", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ perfil_url: perfil_url }),
            credentials: "same-origin",
          }).catch(function () {});

          // Redirigir
          setTimeout(function () {
            window.location.href = perfil_url + "?mostrar_popup=1";
          }, 1200);
        } else {
          scAnnounce("Hubo un problema al crear el perfil.");
          var msgErr =
            response && response.data && response.data.message
              ? response.data.message
              : smartcardsL10n && smartcardsL10n.error_creating_profile
                ? smartcardsL10n.error_creating_profile
                : "No fue posible crear tu perfil.";
          alert(msgErr);
        }
      })
      .catch(function () {
        if (boton._scHardTO) {
          clearTimeout(boton._scHardTO);
          boton._scHardTO = null;
        }
        scAnnounce("Error de red.");
        var msgNet =
          smartcardsL10n && smartcardsL10n.request_error
            ? smartcardsL10n.request_error
            : "Error de red. Intenta de nuevo.";
        alert(msgNet);
      })
      .finally(function () {
        scStopGenerating(boton); // restaura SIEMPRE el botón
      });
  });
}

// Genera QR solo si existe #qr-container y no existe formulario (página pública)
function initQRCodeOnPublicProfile() {
  if (
    !document.getElementById("qr-container") ||
    document.getElementById("smartcards-form")
  ) {
    return;
  }

  function runDeferredQR() {
    if (typeof QRCodeStyling === "undefined") {
      setTimeout(function () {
        generarQR(window.location.href);
      }, 500);
      return;
    }

    generarQR(window.location.href);
  }

  if ("requestIdleCallback" in window) {
    requestIdleCallback(function () {
      runDeferredQR();
    });
  } else {
    setTimeout(function () {
      runDeferredQR();
    }, 500);
  }
}

// Función que genera el QR estilizado con logo
function generarQR(urlPerfil) {
  const qrCode = new QRCodeStyling({
    width: 270,
    height: 270,
    data: urlPerfil,
    image:
      "https://app.smartcards.com.co/wp-content/uploads/2025/03/fav-smartcard.svg",
    dots: {
      color: "#000000", // QR negro
      type: "classy-rounded", // estilo QR tradicional. Puedes usar: square, dots, rounded, extra-rounded, classy, classy-rounded
    },
    backgroundOptions: {
      color: "#ffffff",
    },
    imageOptions: {
      crossOrigin: "anonymous",
      margin: 10,
      imageSize: 0.5,
    },
  });

  const qrContainer = document.getElementById("qr-container");
  if (qrContainer) {
    qrContainer.innerHTML = "";
    qrContainer.style.display = "flex";
    qrContainer.style.justifyContent = "center";
    qrContainer.style.border = "2px solid #000";
    qrContainer.style.borderRadius = "10px";
    qrContainer.style.padding = "10px";
    qrContainer.style.marginTop = "20px";
    qrCode.append(qrContainer);
  }
}

function initReviewModalFromUrl() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("mostrar_popup") === "1") {
    scShowReviewModal(); // ← usar el modal nuevo
  }
}

// Mostrar popup personalizado después de generar perfil público correctamente
function mostrarPopupRevision() {
  const popup = document.createElement("div");
  popup.className = "smart-popup";

  // HTML traducible con datos localizados desde PHP
  popup.innerHTML =
    "<h4>" +
    smartcardsL10n.popup_message +
    "</h4>" +
    '<div class="smart-popup-buttons">' +
    '<button class="btn-approve">' +
    smartcardsL10n.approved +
    "</button>" +
    '<button class="btn-error">' +
    smartcardsL10n.has_error +
    "</button>" +
    "</div>";

  document.body.appendChild(popup);

  // Acción botón aprobado
  popup.querySelector(".btn-approve").onclick = function () {
    window.location.href = "https://app.smartcards.com.co/dashboard/"; // Cambia por tu URL de Dashboard
  };

  // Acción botón error
  popup.querySelector(".btn-error").onclick = function () {
    fetch(smartcardsL10n.ajax_url + "?action=perfil_con_error", {
      method: "POST",
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((response) => {
        if (response.success) {
          alert(response.data.message);

          // Cerrar el popup
          popup.remove();

          // Redirigir después de una pausa breve (opcional)
          setTimeout(function () {
            window.location.href =
              "https://app.smartcards.com.co/formulario-activacion/"; // Cambia por tu URL de formulario de activación
          }, 500);
        } else {
          alert(response.data.message);
        }
      });
  };
}

// --- Plan C: NativelyPurchases (Bridge de Natively) ---
async function buyViaNativelyPurchases(sku) {
  // Si el bridge no existe, no hacemos nada.
  if (typeof window.NativelyPurchases !== "function") return false;

  return new Promise((resolve, reject) => {
    try {
      const np = new window.NativelyPurchases();

      // Opcional: log para verificar inicialización
      if (typeof np.packagePrice === "function") {
        np.packagePrice(sku, function (r) {
          console.log("[IAP] NativelyPurchases.price", r);
        });
      }

      // Callback común de resultado de compra
      const onResult = function (r) {
        console.log("[IAP] NativelyPurchases.purchase", r);
        const ok = r && (r.status === "SUCCESS" || r.success === true);

        if (!ok) {
          return reject((r && (r.error || r.message)) || "FAILED");
        }

        // Dispara tu evento estándar para acreditar créditos en WP
        window.dispatchEvent(
          new CustomEvent("sc_iap_completed", {
            detail: {
              productId: sku,
              platform: /Android/i.test(navigator.userAgent)
                ? "android"
                : "ios",
              provider: "natively",
              transactionId: r.transactionId || r.id || null,
              receipt: r.receipt || r.transactionReceipt || null,
              purchaseToken: r.purchaseToken || r.token || null,
            },
          }),
        );

        resolve(true);
      };

      // Algunos SDKs exponen purchasePackage, otros purchaseProduct.
      if (typeof np.purchasePackage === "function") {
        np.purchasePackage(sku, onResult);
      } else if (typeof np.purchaseProduct === "function") {
        np.purchaseProduct(sku, onResult);
      } else {
        reject("NativelyPurchases: método de compra no disponible");
      }
    } catch (e) {
      reject(e);
    }
  });
}

/* ============================================================
 * In-App Purchases unificado + sin fallback iOS + acreditación
 * Reemplaza tu sección IAP con este bloque.
 * ============================================================ */

/** Compat: shortcodes antiguos pueden llamar window.scPurchase(SKU) */
window.scPurchase = function (productId) {
  if (typeof window.__scBuyWithIAP === "function") {
    window.__scBuyWithIAP(productId, "");
  }
};

/** Escucha compra completada y avisa al backend (suma créditos) */
window.addEventListener("sc_iap_completed", function (e) {
  try {
    const ua = navigator.userAgent || "";
    const isIOS = /iPhone|iPad|iPod/i.test(ua);
    const isAndroid = /Android/i.test(ua);
    const platform =
      e.detail?.platform || (isIOS ? "ios" : isAndroid ? "android" : "web");

    // Si por algún motivo se disparó el evento en web/desktop, NO llames al endpoint.
    if (platform === "web") return;

    var payload = {
      productId: e.detail?.productId || "",
      transactionId: e.detail?.transactionId || null,
      platform: platform,
      receipt: e.detail?.receipt || null,
      purchaseToken: e.detail?.purchaseToken || null,
    };

    fetch(
      (window.smartcardsL10n?.ajax_url || "/wp-admin/admin-ajax.php") +
        "?action=sc_sc_iap_complete",
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(payload),
      },
    )
      .then((r) => r.json())
      .then((res) => {
        if (res?.success) {
          alert("✅ Compra aplicada. Tus créditos fueron actualizados.");
          try {
            location.reload();
          } catch (_) {}
        } else {
          alert(
            "❌ No se pudo aplicar la compra: " +
              (res?.data?.message || "Error desconocido"),
          );
        }
      })
      .catch((err) => {
        console.error("Error al acreditar compra:", err);
        alert("❌ Error de red al aplicar la compra.");
      });
  } catch (e2) {
    console.error("sc_iap_completed handler error:", e2);
  }
});

/** Opcional: setear identidad/tags de push (como lo tenías) */
(function setPushIdentity() {
  if (typeof smartcardsUser === "undefined") return;
  if (!smartcardsUser?.id) return;
  const n = window.Natively || window.natively;
  if (!n || !n.push) return;
  try {
    n.push.setExternalId(String(smartcardsUser.id));
    if (typeof smartcardsUser.credits !== "undefined") {
      n.push.setTag("credits", String(smartcardsUser.credits));
      n.push.setTag("has_credits", smartcardsUser.credits > 0 ? "yes" : "no");
    }
    if (smartcardsUser.profile_status)
      n.push.setTag("profile_status", String(smartcardsUser.profile_status));
    if (smartcardsUser.lang)
      n.push.setTag("lang", String(smartcardsUser.lang).toLowerCase());
    let country = (smartcardsUser.country || "").toUpperCase();
    if (!country && navigator.language) {
      const parts = navigator.language.split("-");
      if (parts[1]) country = parts[1].toUpperCase();
    }
    if (country) n.push.setTag("country", country);
  } catch (e) {
    console.error("OneSignal tags error:", e);
  }
})();

// ===== Loading del botón con contador de 15s =====
function scStartGenerating(btn, seconds = 15) {
  if (!btn) return;
  btn.dataset.originalLabel = btn.textContent.trim();
  btn.disabled = true;
  btn.classList.add("loading");
  btn.setAttribute("aria-busy", "true");

  let remaining = seconds;

  const render = () => {
    btn.innerHTML =
      '<span class="sc-spinner"></span> ' +
      "Creando Perfil Público… " +
      '<span class="sc-wait">Tiempo de espera: ' +
      remaining +
      " segundos</span>";
  };

  render();
  const t = setInterval(() => {
    remaining = Math.max(0, remaining - 1);
    render();
    if (remaining === 0) clearInterval(t);
  }, 1000);

  btn._scTimer = t; // para limpiar después
}

function scStopGenerating(btn) {
  if (!btn) return;
  if (btn._scTimer) {
    clearInterval(btn._scTimer);
    delete btn._scTimer;
  }
  btn.classList.remove("loading");
  btn.removeAttribute("aria-busy");
  btn.disabled = false;
  btn.innerHTML = btn.dataset.originalLabel || "Generar Perfil Público";
}

// Región de estado para lectores de pantalla
(function ensureLiveRegion() {
  if (document.getElementById("sc-live-status")) return;
  const r = document.createElement("div");
  r.id = "sc-live-status";
  r.setAttribute("role", "status");
  r.setAttribute("aria-live", "polite");
  r.style.position = "absolute";
  r.style.left = "-9999px";
  document.body.appendChild(r);
})();

function scAnnounce(msg) {
  const r = document.getElementById("sc-live-status");
  if (r) r.textContent = msg;
}

// Extiende tus helpers:
const _scStop = scStopGenerating;
scStopGenerating = function (btn) {
  scAnnounce("Proceso finalizado.");
  _scStop(btn);
};

// Freno de seguridad si el servidor tarda demasiado
function scArmHardTimeout(btn, ms = 60000) {
  // 60s
  if (!btn) return;
  if (btn._scHardTO) clearTimeout(btn._scHardTO);
  btn._scHardTO = setTimeout(() => {
    _scStop(btn);
    alert("Tardó más de lo esperado. Intenta nuevamente.");
  }, ms);
}

// ===== Modal "Revisa tu perfil" (post-creación) =====
function scShowReviewModal(perfilUrl) {
  // Normaliza url (puede venir con ?mostrar_popup=1)
  try {
    var u = new URL(perfilUrl || window.location.href);
    u.searchParams.delete("mostrar_popup");
    perfilUrl = u.toString();
  } catch (_) {
    perfilUrl =
      perfilUrl || window.location.href.replace(/(\?|&)mostrar_popup=1/, "");
  }

  var overlay = document.createElement("div");
  overlay.className = "sc-modal-overlay";

  var modal = document.createElement("div");
  modal.className = "sc-modal";

  var p = document.createElement("p");
  p.className = "sc-modal-desc";
  p.textContent = t(
    "review_modal_message",
    "🔔 Verifica que todos los detalles de tu perfil público estén correctos, incluyendo el botón “Guardar contacto”, los botones sociales, la foto de portada y la foto de perfil.",
  );

  var btns = document.createElement("div");
  btns.className = "sc-modal-btns";

  var bOk = document.createElement("button");
  bOk.className = "sc-btn sc-btn-copy";
  bOk.textContent = t("review_ok", "Aprobado ✅");

  var bErr = document.createElement("button");
  bErr.className = "sc-btn sc-btn-email";
  bErr.textContent = t("review_issue", "Mi perfil tiene un error ❌");

  btns.appendChild(bOk);
  btns.appendChild(bErr);
  modal.appendChild(p);
  modal.appendChild(btns);
  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  // Importante: NO cerrar al click fuera; se queda hasta elegir.
  // overlay.addEventListener('click', ... )  ← intencionalmente NO

  // Acción: Aprobado → solo cerrar
  bOk.addEventListener("click", function () {
    bOk.disabled = bErr.disabled = true;
    overlay.remove();
  });

  // Acción: Rechazar → devolver crédito, borrar perfil + vcf y redirigir
  bErr.addEventListener("click", function () {
    bOk.disabled = bErr.disabled = true;

    var fd = new FormData();
    fd.append("action", "sc_reject_profile");
    fd.append("perfil_url", perfilUrl);

    fetch(
      window.smartcardsL10n && smartcardsL10n.ajax_url
        ? smartcardsL10n.ajax_url
        : "/wp-admin/admin-ajax.php",
      {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      },
    )
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (res && res.success) {
          alert(
            res.data && res.data.msg
              ? res.data.msg
              : "Se devolvió el crédito y se eliminó el perfil.",
          );
          window.location.href =
            res.data && res.data.redirect ? res.data.redirect : "/dashboard/";
        } else {
          alert(
            res && res.data && res.data.msg
              ? res.data.msg
              : "No fue posible procesar el rechazo.",
          );
          bOk.disabled = bErr.disabled = false;
        }
      })
      .catch(function () {
        alert(t("request_error", "Error de red. Intenta de nuevo."));
        bOk.disabled = bErr.disabled = false;
      });
  });
}

function initReviewModalListener() {
  try {
    var u = new URL(window.location.href);
    if (u.searchParams.get("mostrar_popup") === "1")
      scShowReviewModal(u.toString());
  } catch (e) {
    if (window.location.search.indexOf("mostrar_popup=1") !== -1)
      scShowReviewModal(window.location.href);
  }
}

// === IAP smoke test (quitar luego) ===
window.__iapDiag = function () {
  return {
    hasNativelyPurchases: typeof window.NativelyPurchases === "function",
    typeNatively: typeof window.natively,
    hasRC: !!(window.Purchases || window.revenuecat || window.RC),
    ua: navigator.userAgent,
  };
};

// Llamar desde la consola del WebView en TestFlight:
// __iapDiag()

function initSmartCardAnalyticsChart() {
  var chart;
  var canvas = document.getElementById("sc-visits-chart");
  if (!canvas) return;

  var ctx = canvas.getContext("2d");
  if (typeof Chart === "undefined") return;

  function fetchChartData(profileId, days) {
    var ajaxBase =
      window.smartcardsL10n && smartcardsL10n.ajax_url
        ? smartcardsL10n.ajax_url
        : "/wp-admin/admin-ajax.php";

    var url =
      ajaxBase +
      "?action=sc_get_visits_range&profile_id=" +
      profileId +
      "&days=" +
      days;

    fetch(url)
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        if (json.success) {
          var labels = [];
          var data = [];

          json.data.forEach(function (item) {
            labels.push(item.day);
            data.push(item.total);
          });

          if (chart) {
            chart.destroy();
          }

          canvas.style.height = "320px";
          canvas.height = 320;

          var gradient = ctx.createLinearGradient(0, 0, 0, 320);
          gradient.addColorStop(0, "rgba(1,163,80,0.35)");
          gradient.addColorStop(1, "rgba(1,163,80,0.02)");

          chart = new Chart(ctx, {
            type: "line",
            data: {
              labels: labels,
              datasets: [
                {
                  label: "Visitas",
                  data: data,
                  borderColor: "#01A350",
                  backgroundColor: gradient,
                  tension: 0.4,
                  fill: true,
                  pointRadius: 4,
                  pointBackgroundColor: "#01A350",
                  pointHoverRadius: 6,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              animation: {
                duration: 1200,
                easing: "easeOutQuart",
              },
              scales: {
                x: { ticks: { color: "#444" }, grid: { display: false } },
                y: {
                  ticks: { color: "#444" },
                  grid: { color: "rgba(0,0,0,0.05)" },
                },
              },
              plugins: {
                legend: { display: false },
                tooltip: {
                  backgroundColor: "#111",
                  titleColor: "#fff",
                  bodyColor: "#fff",
                  padding: 14,
                  borderColor: "#01A350",
                  borderWidth: 1,
                  cornerRadius: 10,
                },
              },
            },
          });
        }
      });
  }

  var profileId =
    window.smartcardsL10n && smartcardsL10n.profile_id
      ? parseInt(smartcardsL10n.profile_id, 10)
      : 0;

  if (!profileId) return;

  fetchChartData(profileId, 30);

  document.querySelectorAll(".sc-chart-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".sc-chart-btn").forEach(function (b) {
        b.classList.remove("active");
      });
      btn.classList.add("active");
      var days = btn.getAttribute("data-days");
      fetchChartData(profileId, days);
    });
  });
}

function initNoCreditsGate() {
  if (typeof window.smartcardsUser === "undefined") return;
  if (Number(window.smartcardsUser.credits) > 0) return;

  var form = document.getElementById("smartcards-form");
  if (!form) return;

  const wrapper = document.createElement("div");

  wrapper.innerHTML = `
<div class="sc-no-credits-box">

  <h2>🚫 No tienes créditos disponibles</h2>

  <p>Para crear tu Smart Card necesitas al menos 1 crédito.</p>

  <p class="sc-highlight">
    ✨ Activa un crédito y publica tu perfil en segundos.
  </p>

  <div class="sc-buy-options">

    <a href="/?add-to-cart=1935" class="sc-google-btn sc-google-btn--green">
      🪙 Comprar 1 crédito
      <span class="sc-price">$24.900</span>
    </a>

    <a href="/?add-to-cart=4946" class="sc-google-btn sc-google-btn--primary">
      🔥 Comprar 5 créditos
      <span class="sc-price">$99.900</span>
    </a>

    <a href="/?add-to-cart=4947" class="sc-google-btn sc-google-btn--green">
      🪙 Comprar 10 créditos
      <span class="sc-price">$169.900</span>
    </a>

  </div>

  <a href="/dashboard/" class="sc-secondary-btn">
    Volver al dashboard
  </a>

</div>
`;

  form.replaceWith(wrapper.firstElementChild || wrapper);
}

function initSmartCardsFrontend() {
  initNoCreditsGate();
  initSmartCardTracking();
  initFormValidation();
  initQRCodeOnPublicProfile();
  initReviewModalFromUrl();
  initReviewModalListener();
  initSmartCardAnalyticsChart();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    initSmartCardsFrontend();
  });
} else {
  initSmartCardsFrontend();
}
