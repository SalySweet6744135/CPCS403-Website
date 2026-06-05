/*
Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
ID: 2206712, 2207221, 2205679
Section: CPCS403
Date: 31-05-2026
File: scripts/main.js
Purpose: Global JavaScript — navigation toggle, feedback form AJAX, session-aware nav links
*/

(function () {
  "use strict";

  /** Root-relative prefix for API calls (works from /, /pages/, /admin/). */
  const apiRoot = (() => {
    const path = window.location.pathname || "";
    if (path.includes("/pages/") || path.includes("/admin/")) return "..";
    return ".";
  })();

  const apiUrl = (endpoint) => `${apiRoot}/${endpoint.replace(/^\//, "")}`;

  // Show Dashboard nav item for admins when page is static HTML
  (function revealAdminNav(){
    try{
      fetch(apiUrl("api/whoami.php"), { credentials: 'include' })
        .then(r => r.json())
        .then(j => {
          if (j && j.role === 'admin') {
            const el = document.getElementById('nav-dashboard');
            if (el) el.style.display = '';
          }
        }).catch(()=>{});
    }catch(e){}
  })();

  // ===== Common elements (may be missing on some pages) =====
  const navToggle = document.querySelector(".nav-toggle");
  const trackForm = document.getElementById("trackForm");
  const trackingInput = document.getElementById("trackingNumber");
  const carrierSelect = document.getElementById("carrier") || document.getElementById("preferredCarrier");
  const demoMode = document.getElementById("demoMode");

  const trackingError = document.getElementById("trackingError");
  const carrierError = document.getElementById("carrierError");

  const loadingBox = document.getElementById("loadingBox");
  const resultArea = document.getElementById("resultArea");

  const statusBadge = document.getElementById("statusBadge");
  const rCarrier = document.getElementById("rCarrier");
  const rTracking = document.getElementById("rTracking");
  const rEta = document.getElementById("rEta");
  const rUpdate = document.getElementById("rUpdate");

  const newSearchBtn = document.getElementById("newSearchBtn");
  const steps = Array.from(document.querySelectorAll(".step"));

  // ===== Helpers =====
  const labelCarrier = (val) => {
    const map = { aramex: "Aramex", dhl: "DHL", fedex: "FedEx", smsa: "SMSA" };
    return map[val] || "—";
  };

  const setBadge = (text, type = "info") => {
    if (!statusBadge) return;
    statusBadge.textContent = text;
    statusBadge.className = "badge";
    if (type === "ok") statusBadge.classList.add("ok");
    if (type === "warn") statusBadge.classList.add("warn");
  };

  const clearErrors = () => {
    if (trackingError) trackingError.textContent = "";
    if (carrierError) carrierError.textContent = "";
  };

  const isValidTracking = (v) => {
    const value = (v || "").trim();
    return value.length >= 6 && /^[A-Za-z0-9-]+$/.test(value);
  };

  const validate = () => {
    clearErrors();
    let ok = true;

    if (trackingInput && !isValidTracking(trackingInput.value)) {
      if (trackingError) trackingError.textContent = "Enter a valid tracking number (min 6 characters, letters/numbers).";
      ok = false;
    }
    if (carrierSelect && !carrierSelect.value) {
      if (carrierError) carrierError.textContent = "Please select a carrier.";
      ok = false;
    }
    return ok;
  };

  const showLoading = (on) => {
    if (!loadingBox) return;
    loadingBox.hidden = !on;
    loadingBox.style.display = on ? "" : "none";
  };
  const showResult = (on) => {
    if (!resultArea) return;
    resultArea.hidden = !on;
    resultArea.style.display = on ? "" : "none";
  };

  const resetTimeline = () => {
    steps.forEach((s) => s.classList.remove("done", "active"));
  };

  const setTimeline = (key) => {
    const order = ["created", "picked", "transit", "out", "delivered"];
    const idx = order.indexOf(key);
    resetTimeline();

    steps.forEach((li) => {
      const i = order.indexOf(li.dataset.step);
      if (i < idx) li.classList.add("done");
      if (i === idx) li.classList.add("active");
    });
  };

  // Save last carrier — wrapped in try/catch for iOS private mode
  const STORAGE_KEY = "shipsmart_last_carrier";
  if (carrierSelect) {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) carrierSelect.value = saved;
    } catch (e) {}

    carrierSelect.addEventListener("change", () => {
      try {
        if (carrierSelect.value) localStorage.setItem(STORAGE_KEY, carrierSelect.value);
      } catch (e) {}
    });
  }

  // Demo scenarios
  const demo = [
    { status: "In Transit", type: "info", step: "transit", etaDays: 3 },
    { status: "Out for Delivery", type: "warn", step: "out", etaDays: 1 },
    { status: "Delivered", type: "ok", step: "delivered", etaDays: 0 },
  ];

  const statusToTimeline = (status) => {
    const s = String(status || "").toLowerCase().replace(/[\s-]/g, "_");
    if (s.includes("deliver")) return "delivered";
    if (s.includes("out_for") || s === "out") return "out";
    if (s.includes("transit") || s.includes("picked")) return s.includes("picked") ? "picked" : "transit";
    return "created";
  };

  const statusToBadge = (status) => {
    const s = String(status || "").toLowerCase();
    if (s.includes("deliver")) return { text: "Delivered", type: "ok" };
    if (s.includes("out_for") || s.includes("out for")) return { text: "Out for Delivery", type: "warn" };
    if (s.includes("transit")) return { text: "In Transit", type: "info" };
    if (s.includes("picked")) return { text: "Picked Up", type: "info" };
    return { text: status || "Found", type: "info" };
  };

  const renderShipmentResult = (shipment) => {
    showLoading(false);
    const carrierVal = shipment.carrier || carrierSelect?.value;
    if (rCarrier) rCarrier.textContent = labelCarrier(carrierVal);
    if (rTracking) rTracking.textContent = (shipment.tracking_number || trackingInput?.value || "").trim().toUpperCase();
    if (rEta) {
      const eta = shipment.estimated_delivery;
      rEta.textContent = eta ? new Date(eta + "T00:00:00").toLocaleDateString() : "—";
    }
    if (rUpdate) {
      rUpdate.textContent = shipment.last_updated
        ? new Date(shipment.last_updated).toLocaleString()
        : new Date().toLocaleString();
    }
    const badge = statusToBadge(shipment.status);
    setBadge(badge.text, badge.type);
    setTimeline(statusToTimeline(shipment.status));
    showResult(true);
  };

  const renderDemo = () => {
    const pick = demo[Math.floor(Math.random() * demo.length)];
    const now = new Date();
    const eta = new Date(now);
    eta.setDate(eta.getDate() + pick.etaDays);

    renderShipmentResult({
      carrier: carrierSelect?.value,
      tracking_number: trackingInput?.value,
      estimated_delivery: pick.etaDays === 0 ? now.toISOString().slice(0, 10) : eta.toISOString().slice(0, 10),
      last_updated: now.toISOString(),
      status: pick.status,
    });
    setBadge(pick.status, pick.type);
    setTimeline(pick.step);
  };

  const trackViaApi = () => {
    showResult(false);
    resetTimeline();
    clearErrors();
    setBadge("Loading", "info");
    showLoading(true);

    const q = (trackingInput?.value || "").trim();
    const carrier = carrierSelect?.value || "";
    const params = new URLSearchParams({ q });
    if (carrier) params.set("carrier", carrier);

    fetch(`${apiUrl("api/search.php")}?${params.toString()}`)
      .then((res) => {
        if (!res.ok) throw new Error("Search failed");
        return res.json();
      })
      .then((data) => {
        showLoading(false);
        if (!Array.isArray(data) || data.length === 0) {
          setBadge("Not Found", "warn");
          if (trackingError) {
            trackingError.textContent = "No shipment found in the database for this tracking number.";
          }
          return;
        }
        renderShipmentResult(data[0]);
      })
      .catch(() => {
        showLoading(false);
        setBadge("Error", "warn");
        if (trackingError) trackingError.textContent = "Could not reach the search API. Is PHP running?";
      });
  };

  const trackBtn = document.getElementById("trackBtn");

  // ===== Nav toggle =====
  navToggle?.addEventListener("click", () => {
    const opened = document.body.classList.toggle("menu-open");
    navToggle.setAttribute("aria-expanded", String(opened));
  });

  // ===== Convert header nav to side-nav on wide screens =====
  const setupSideNav = () => {
    // only on desktop widths
    if (window.innerWidth < 861) {
      document.body.classList.remove('layout-sidebar');
      const existing = document.getElementById('site-side-nav');
      if (existing) existing.remove();
      return;
    }

    // create side-nav container if not exists
    let side = document.getElementById('site-side-nav');
    if (!side) {
      side = document.createElement('aside');
      side.id = 'site-side-nav';
      side.className = 'side-nav';
      // clone brand and nav-list
      const header = document.querySelector('.site-header .brand');
      if (header) side.appendChild(header.cloneNode(true));
      const nav = document.querySelector('.site-header .nav');
      if (nav) side.appendChild(nav.cloneNode(true));
      document.body.appendChild(side);
    }
    document.body.classList.add('layout-sidebar');
    // expose dashboard link if admin
    const adminLi = document.getElementById('nav-dashboard');
    if (adminLi) adminLi.style.display = '';
  };

  // run on load and resize (debounced)
  let resizeTimer = null;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(setupSideNav, 120);
  });
  // initial
  setupSideNav();

  // ===== Track button — Fetch API → api/search.php (or demo mode) =====
  const doTrack = () => {
    if (!validate()) return;
    if (demoMode?.checked) {
      showResult(false);
      resetTimeline();
      setBadge("Loading", "info");
      showLoading(true);
      setTimeout(renderDemo, 600);
      return;
    }
    trackViaApi();
  };

  trackBtn?.addEventListener("click", doTrack);

  trackForm?.addEventListener("submit", (e) => {
    e.preventDefault();
    doTrack();
  });

  newSearchBtn?.addEventListener("click", () => {
    showResult(false);
    showLoading(false);
    resetTimeline();
    clearErrors();
    if (trackingInput) trackingInput.value = "";
    trackingInput?.focus();
  });

  // ====================================================================
  //  FEEDBACK FORM VALIDATION (pages/feedback.html)
  // ====================================================================
  const feedbackForm = document.getElementById("feedbackForm");
  if (feedbackForm) {
  // If the page includes the success overlay element, move it to body so
  // it is not constrained by parent containers (prevents it from appearing
  // at the end of the page when shown).
  const existingSuccess = document.getElementById("feedbackSuccess");
  if (existingSuccess && existingSuccess.parentElement !== document.body) {
    document.body.appendChild(existingSuccess);
    // ensure it's hidden initially
    existingSuccess.hidden = true;
    existingSuccess.style.display = "none";
  }

  const feedbackForm = document.getElementById("feedbackForm");

  if (!feedbackForm) return;
    const fFirstName = document.getElementById("firstName");
    const fLastName = document.getElementById("lastName");
    const fEmail = document.getElementById("email");
    const fCarrier = document.getElementById("preferredCarrier");
    const fComments = document.getElementById("comments");
    const fCharCount = document.getElementById("charCount");
    const fSuccess = document.getElementById("feedbackSuccess");
    const fResetBtn = document.getElementById("resetBtn");

    const firstNameErr = document.getElementById("firstNameError");
    const lastNameErr = document.getElementById("lastNameError");
    const emailErr = document.getElementById("emailError");
    const ratingErr = document.getElementById("ratingError");
    const servicesErr = document.getElementById("servicesError");
    const carrierPErr = document.getElementById("carrierPrefError");

    const MAX_CHARS = 500;

    const setErr = (el, msg) => {
      if (!el) return;
      el.textContent = msg;
      el.closest(".form-group, .form-fieldset")?.classList.add("has-error");
    };
    const clrErr = (...els) => {
      els.forEach((e) => {
        if (!e) return;
        e.textContent = "";
        e.closest(".form-group, .form-fieldset")?.classList.remove("has-error");
      });
    };

    const isValidName = (v) => {
      const val = (v || "").trim();
      return val.length >= 2 && /^[A-Za-z - -\u06FF]+$/.test(val.replace(/\s+/g, ""));
    };

    const isValidEmail = (v) => {
      const val = (v || "").trim();
      if (!val) return false;
      const pattern = /^[^\s@]{2,}@[^^\s@]{2,}\.[^\s@]{2,}$/;
      return pattern.test(val);
    };

    // Live validation
    fFirstName?.addEventListener("blur", () => {
      if (fFirstName.value.trim() === "") {
        clrErr(firstNameErr);
      } else if (!isValidName(fFirstName.value)) {
        setErr(firstNameErr, "First name must be at least 2 letters, no numbers or symbols.");
      } else {
        clrErr(firstNameErr);
      }
    });

    fLastName?.addEventListener("blur", () => {
      if (fLastName.value.trim() === "") {
        clrErr(lastNameErr);
      } else if (!isValidName(fLastName.value)) {
        setErr(lastNameErr, "Last name must be at least 2 letters, no numbers or symbols.");
      } else {
        clrErr(lastNameErr);
      }
    });

    fEmail?.addEventListener("blur", () => {
      if (fEmail.value.trim() === "") {
        clrErr(emailErr);
      } else if (!isValidEmail(fEmail.value)) {
        setErr(emailErr, "Please enter a valid email address (e.g., name@example.com).");
      } else {
        clrErr(emailErr);
      }
    });

    fCarrier?.addEventListener("change", () => {
      if (fCarrier.value) clrErr(carrierPErr);
    });

    fComments?.addEventListener("input", () => {
      const len = fComments.value.length;
      if (fCharCount) fCharCount.textContent = len + " / " + MAX_CHARS + " characters";
      if (len > MAX_CHARS) {
        fComments.value = fComments.value.substring(0, MAX_CHARS);
        if (fCharCount) fCharCount.textContent = MAX_CHARS + " / " + MAX_CHARS + " characters";
      }
    });

    const validateFeedback = () => {
      clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
      let ok = true;

      if (!isValidName(fFirstName?.value)) {
        setErr(firstNameErr, "First name must be at least 2 letters, no numbers or symbols.");
        ok = false;
      }

      if (!isValidName(fLastName?.value)) {
        setErr(lastNameErr, "Last name must be at least 2 letters, no numbers or symbols.");
        ok = false;
      }

      if (!isValidEmail(fEmail?.value)) {
        setErr(emailErr, "Please enter a valid email address (e.g., name@example.com).");
        ok = false;
      }

      const ratingChecked = feedbackForm.querySelector('input[name="rating"]:checked');
      if (!ratingChecked) {
        setErr(ratingErr, "Please select a rating.");
        ok = false;
      }

      const servicesChecked = feedbackForm.querySelectorAll('input[name="services[]"]:checked');
      if (servicesChecked.length === 0) {
        setErr(servicesErr, "Please select at least one service.");
        ok = false;
      }

      if (!fCarrier?.value) {
        setErr(carrierPErr, "Please select your preferred carrier.");
        ok = false;
      }

      return ok;
    };

    const submitBtn = document.getElementById("submitBtn");

    const feedbackEmailNote = document.getElementById("feedbackEmailNote");

    const showFeedbackSuccess = (data = {}) => {
      if (!fSuccess) return;
      if (feedbackEmailNote) {
        if (data.emailSent) {
          feedbackEmailNote.textContent = "✓ Confirmation email sent.";
          feedbackEmailNote.style.color = "#2e7d32";
          feedbackEmailNote.hidden = false;
        } else if (data.emailError) {
          feedbackEmailNote.textContent = "⚠ " + data.emailError;
          feedbackEmailNote.style.color = "#b26a00";
          feedbackEmailNote.hidden = false;
        } else {
          feedbackEmailNote.textContent = "";
          feedbackEmailNote.hidden = true;
        }
      }
      if (fSuccess.parentElement !== document.body) {
        document.body.appendChild(fSuccess);
      }
      fSuccess.hidden = false;
      fSuccess.style.display = "flex";
      fSuccess.style.position = "fixed";
      fSuccess.style.inset = "0";
      fSuccess.style.zIndex = "10000";
      const modalBox = fSuccess.querySelector(".modal-box");
      if (modalBox) {
        modalBox.setAttribute("tabindex", "-1");
        modalBox.focus();
      }
      document.body.style.overflow = "hidden";
    };

    const handleSubmit = (e) => {
      if (e && e.cancelable) e.preventDefault();
      if (!validateFeedback()) {
        const firstErr = feedbackForm.querySelector(".error:not(:empty)");
        if (firstErr) firstErr.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }

      const fd = new FormData(feedbackForm);
      const first = (fFirstName?.value || "").trim();
      const last = (fLastName?.value || "").trim();
      fd.set("fullName", `${first} ${last}`.trim());

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting…";
      }

      fetch(apiUrl("api/feedback.php"), { method: "POST", body: fd })
        .then((r) => r.json())
        .then((data) => {
          if (!data.success) {
            alert(data.message || "Submission failed. Please try again.");
            return;
          }
          feedbackForm.reset();
          clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
          if (fCharCount) fCharCount.textContent = "0 / " + MAX_CHARS + " characters";
          showFeedbackSuccess(data);
        })
        .catch(() => alert("Network error. Please try again."))
        .finally(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Feedback";
          }
        });
    };

    submitBtn?.addEventListener("click", handleSubmit);
    submitBtn?.addEventListener("touchend", handleSubmit);

    fResetBtn?.addEventListener("click", () => {
      setTimeout(() => {
        clrErr(firstNameErr, lastNameErr, emailErr, ratingErr, servicesErr, carrierPErr);
        if (fCharCount) fCharCount.textContent = "0 / " + MAX_CHARS + " characters";
      }, 0);
    });

    const submitAnotherBtn = document.getElementById("submitAnotherBtn");
    submitAnotherBtn?.addEventListener("click", () => {
      if (feedbackEmailNote) {
        feedbackEmailNote.textContent = "";
        feedbackEmailNote.hidden = true;
      }
      if (fSuccess) {
        fSuccess.hidden = true;
        fSuccess.style.display = "none";
        fSuccess.style.position = "";
        fSuccess.style.inset = "";
        fSuccess.style.zIndex = "";
      }
      document.body.style.overflow = "";
      window.scrollTo({ top: 0, behavior: "smooth" });
      fFirstName?.focus();
    });
  }

  // Sign out via Fetch API (no full page reload to logout.php)
  document.querySelector(".btn-signout")?.addEventListener("click", (e) => {
    e.preventDefault();
    fetch(apiUrl("api/logout.php"), {
      method: "GET",
      credentials: "include",
      headers: { Accept: "application/json" },
    })
      .then((r) => r.json())
      .then((data) => {
        window.location.href = data.redirect || apiUrl("login.php");
      })
      .catch(() => {
        window.location.href = apiUrl("login.php");
      });
  });
})();