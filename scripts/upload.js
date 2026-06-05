/*
Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
ID: 2206712, 2207221, 2205679
Section: CPCS403
Date: 31-05-2026
File: scripts/upload.js
Purpose: Upload page script — client validation, drag-and-drop, and AJAX file upload
*/

(() => {
  "use strict";

  // ── Elements ──
  const uploadForm    = document.getElementById("uploadForm");
  if (!uploadForm) return; // only run on upload page

  const dropZone      = document.getElementById("dropZone");
  const fileInput     = document.getElementById("documentFile");
  const fileChosen    = document.getElementById("fileChosen");
  const uploadBtn     = document.getElementById("uploadBtn");
  const uploadSuccess = document.getElementById("uploadSuccess");
  const successMsg    = document.getElementById("successMsg");
  const successMeta   = document.getElementById("successMeta");
  const uploadAnotherBtn = document.getElementById("uploadAnotherBtn");
  const recentInfo    = document.getElementById("recentInfo");

  // Error elements
  const trackingError = document.getElementById("trackingError");
  const carrierError  = document.getElementById("carrierError");
  const docTypeError  = document.getElementById("docTypeError");
  const fileError     = document.getElementById("fileError");
  const emailError    = document.getElementById("emailError");

  // Form fields
  const trackingInput = document.getElementById("trackingNumber");
  const carrierSelect = document.getElementById("carrier");
  const docTypeSelect = document.getElementById("docType");
  const emailInput    = document.getElementById("uploaderEmail");

  const MAX_SIZE = 2 * 1024 * 1024; // 2 MB
  const ALLOWED_TYPES = ["image/jpeg", "image/png", "application/pdf"];

  // Tracks how many docs uploaded this session (shown in sidebar)
  let sessionCount = 0;

  // ── Helpers ──
  const setErr = (el, msg) => { if (el) el.textContent = msg; };
  const clrErr = (...els) => els.forEach(e => { if (e) e.textContent = ""; });

  const formatBytes = (bytes) => {
    if (bytes < 1024) return bytes + " B";
    return (bytes / 1024).toFixed(1) + " KB";
  };

  // ── Client-side validation ──
  const validate = () => {
    clrErr(trackingError, carrierError, docTypeError, fileError, emailError);
    let ok = true;

    // Tracking number: 5–50 alphanumeric + hyphens
    const tn = (trackingInput?.value || "").trim();
    if (tn === "") {
      setErr(trackingError, "Tracking number is required.");
      ok = false;
    } else if (!/^[A-Za-z0-9\-]{5,50}$/.test(tn)) {
      setErr(trackingError, "Enter a valid tracking number (5–50 letters, numbers, or hyphens).");
      ok = false;
    }

    // Carrier
    if (!carrierSelect?.value) {
      setErr(carrierError, "Please select a carrier.");
      ok = false;
    }

    // Document type
    if (!docTypeSelect?.value) {
      setErr(docTypeError, "Please select a document type.");
      ok = false;
    }

    // Email (confirmation)
    const email = (emailInput?.value || "").trim();
    if (email === "") {
      setErr(emailError, "Email is required to send your upload confirmation.");
      ok = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
      setErr(emailError, "Please enter a valid email address.");
      ok = false;
    }

    // File
    const file = fileInput?.files?.[0];
    if (!file) {
      setErr(fileError, "Please select a file to upload.");
      ok = false;
    } else {
      if (file.size > MAX_SIZE) {
        setErr(fileError, "File exceeds the 2 MB limit. Please choose a smaller file.");
        ok = false;
      } else if (!ALLOWED_TYPES.includes(file.type)) {
        setErr(fileError, "Only JPG, PNG, and PDF files are allowed.");
        ok = false;
      }
    }

    return ok;
  };

  // ── Update file label when user picks a file ──
  const updateFileLabel = (file) => {
    if (!file || !fileChosen) return;
    fileChosen.textContent = `Selected: ${file.name} (${formatBytes(file.size)})`;
    fileChosen.hidden = false;
    clrErr(fileError);
  };

  fileInput?.addEventListener("change", () => {
    updateFileLabel(fileInput.files?.[0]);
  });

  // ── Drag-and-drop on the drop zone ──
  if (dropZone) {
    ["dragenter", "dragover"].forEach(evt => {
      dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.add("drag-over");
      });
    });

    ["dragleave", "drop"].forEach(evt => {
      dropZone.addEventListener(evt, () => {
        dropZone.classList.remove("drag-over");
      });
    });

    dropZone.addEventListener("drop", (e) => {
      e.preventDefault();
      const files = e.dataTransfer?.files;
      if (files?.length) {
        // Assign to the file input so FormData picks it up
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        fileInput.files = dt.files;
        updateFileLabel(files[0]);
      }
    });

    // Allow clicking anywhere in the zone to open file picker
    dropZone.addEventListener("click", (e) => {
      if (e.target !== fileInput) fileInput.click();
    });

    // Keyboard accessibility (Enter / Space)
    dropZone.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        fileInput.click();
      }
    });
  }

  // ── Form submit — AJAX (Fetch API, no page reload) ──
  uploadForm.addEventListener("submit", (e) => {
    e.preventDefault();

    if (!validate()) {
      // Scroll to first visible error
      const firstErr = uploadForm.querySelector(".error:not(:empty)");
      firstErr?.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }

    // Disable button while uploading
    if (uploadBtn) {
      uploadBtn.disabled = true;
      uploadBtn.textContent = "Uploading…";
    }

    const formData = new FormData(uploadForm);

    fetch(uploadForm.action, {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showSuccess(data);
        } else if (data.errors) {
          // Show server-side field errors
          setErr(trackingError, data.errors.tracking_number || "");
          setErr(carrierError,  data.errors.carrier         || "");
          setErr(docTypeError,  data.errors.doc_type        || "");
          setErr(fileError,     data.errors.document        || "");
          setErr(emailError,    data.errors.uploader_email  || "");
        } else {
          setErr(fileError, data.message || "Upload failed. Please try again.");
        }
      })
      .catch(() => {
        setErr(fileError, "Network error. Please check your connection and try again.");
      })
      .finally(() => {
        if (uploadBtn) {
          uploadBtn.disabled = false;
          uploadBtn.textContent = "Upload & Send Confirmation";
        }
      });
  });

  // ── Show success state ──
  const showSuccess = (data) => {
    // Hide the form
    uploadForm.hidden = true;

    // Populate success card
    if (successMsg) {
      successMsg.textContent =
        `"${data.original_name}" has been uploaded and saved to your shipment record.`;
    }

    // Build meta info — include email status if relevant
    let metaHtml =
      `<p><strong>File:</strong> ${data.original_name}</p>` +
      `<p><strong>Size:</strong> ${data.file_size_kb} KB</p>`;

    if (data.emailSent) {
      metaHtml += `<p style="color:#2e7d32;">&#10003; Confirmation email sent to your inbox.</p>`;
    } else if (data.emailError) {
      metaHtml += `<p style="color:#b26a00;">&#9888; ${data.emailError}</p>`;
    } else {
      metaHtml += `<p style="color:#b26a00;">&#9888; Document saved, but confirmation email could not be sent.</p>`;
    }

    if (successMeta) {
      successMeta.innerHTML = metaHtml;
    }

    if (uploadSuccess) uploadSuccess.hidden = false;

    // Update sidebar session count
    sessionCount++;
    if (recentInfo) {
      recentInfo.textContent =
        `${sessionCount} document${sessionCount > 1 ? "s" : ""} uploaded this session.`;
    }

    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  // ── "Upload Another" resets the form back to its empty state ──
  uploadAnotherBtn?.addEventListener("click", () => {
    uploadForm.reset();
    clrErr(trackingError, carrierError, docTypeError, fileError, emailError);
    if (fileChosen) fileChosen.hidden = true;

    uploadForm.hidden = false;
    if (uploadSuccess) uploadSuccess.hidden = true;

    window.scrollTo({ top: 0, behavior: "smooth" });
    trackingInput?.focus();
  });

})();
