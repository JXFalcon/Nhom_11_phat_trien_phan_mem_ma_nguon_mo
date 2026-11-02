/* ============================================================
   Shopee – payment_qr.js
   Modal thanh toán QR chuyển khoản VietQR.
   Mỗi lần mở/tạo mới đều gọi API để sinh memo random:
   QR khác nhau nhưng vẫn là tài khoản ngân hàng của cửa hàng.
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF;
  let currentModal = null;

  function copyText(text) {
    const value = String(text == null ? "" : text);
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(value).then(
        () => OF.ui.toast("success", "Đã sao chép: " + value),
        () => fallbackCopy(value)
      );
      return;
    }
    fallbackCopy(value);
  }

  function fallbackCopy(value) {
    const ta = document.createElement("textarea");
    ta.value = value;
    ta.setAttribute("readonly", "");
    ta.style.position = "fixed";
    ta.style.left = "-9999px";
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand("copy");
      OF.ui.toast("success", "Đã sao chép: " + value);
    } catch (e) {
      OF.ui.toast("error", "Không sao chép được, vui lòng chép tay.");
    }
    ta.remove();
  }

  function row(label, value, copyable) {
    return '<div class="qr-row"><span class="qr-label">' + OF.escapeHtml(label) + "</span>" +
      '<strong class="qr-value">' + OF.escapeHtml(value || "—") + "</strong>" +
      (copyable
        ? '<button type="button" class="btn btn-sm" data-copy="' + OF.escapeHtml(value) + '">Sao chép</button>'
        : "") +
      "</div>";
  }

  function buildBody(qr, isCustomer) {
    const bank = qr.bank || {};
    return '<div class="qr-pay">' +
        '<div class="qr-pay-top">' +
          '<div class="qr-pay-image">' +
            '<img id="qrImage" src="' + OF.escapeHtml(qr.image_url) + '" alt="Mã QR chuyển khoản" />' +
            '<a class="qr-open-image" href="' + OF.escapeHtml(qr.image_url) + '" target="_blank" rel="noopener">Mở ảnh QR</a>' +
          "</div>" +
          '<div class="qr-pay-info">' +
            '<div class="qr-amount">' + OF.fmt.money(qr.amount) + "</div>" +
            row("Ngân hàng", bank.name) +
            row("Số tài khoản", bank.account_number, true) +
            row("Chủ tài khoản", bank.account_holder) +
            '<div class="qr-memo">' +
              '<span class="qr-label">Nội dung chuyển khoản (bắt buộc giữ nguyên)</span>' +
              '<div class="qr-memo-value">' +
                '<strong id="qrMemo">' + OF.escapeHtml(qr.memo) + "</strong>" +
                '<button type="button" class="btn btn-sm btn-primary" data-copy="' + OF.escapeHtml(qr.memo) + '">Sao chép</button>' +
              "</div>" +
            "</div>" +
            '<div class="qr-countdown" id="qrCountdown"></div>' +
          "</div>" +
        "</div>" +
        '<div class="alert-banner alert-info qr-note">' + OF.icon("info") +
          "<div><strong>Quét QR bằng app ngân hàng để chuyển khoản thật</strong>" +
          "<p>Số tiền và nội dung chuyển khoản đã được điền sẵn. Vui lòng giữ nguyên nội dung <strong>" +
          OF.escapeHtml(qr.memo) + "</strong> để cửa hàng đối chiếu đơn " + OF.escapeHtml(qr.order_code) +
          ". " + (isCustomer
            ? "Sau khi chuyển khoản xong, bấm <strong>Tôi đã chuyển khoản</strong> để hệ thống ghi nhận ngay."
            : "Sau khi khách chuyển khoản, bấm <strong>Thành công</strong> trong trang Thanh toán để ghi nhận.") +
          "</p></div>" +
        "</div>" +
      "</div>";
  }

  function showResult(qr, d) {
    const late = d.order_status === "da_huy";
    const orderStatus = d.order_status || qr.order_status || "cho_xac_nhan";
    const title = late ? "Đã nhận thanh toán – đơn đã hủy" : "Chuyển khoản thành công";
    const body =
      '<div class="qr-result ' + (late ? "is-warning" : "is-success") + '">' +
        '<div class="qr-result-icon">' + OF.icon(late ? "alert" : "check") + "</div>" +
        "<h3>" + title + "</h3>" +
        "<p>" + (late
          ? "Hệ thống đã nhận được thanh toán cho đơn <strong>" + OF.escapeHtml(qr.order_code) +
            "</strong>, nhưng đơn đã hết hạn hoặc đã hủy. Bộ phận CSKH sẽ xử lý hoàn tiền."
          : "Hệ thống đã ghi nhận thanh toán cho đơn <strong>" + OF.escapeHtml(qr.order_code) +
            "</strong>. " + (orderStatus === "da_xac_nhan"
              ? "Đơn đã được cửa hàng xác nhận."
              : "Trạng thái đơn đã được cập nhật.")) + "</p>" +
        '<div class="qr-result-amount">' + OF.fmt.money(qr.amount) + "</div>" +
        '<div class="detail-grid qr-result-grid">' +
          '<div class="detail-item"><label>Mã giao dịch</label><span>' +
            OF.escapeHtml(qr.payment_code || ("#" + qr.payment_id)) + "</span></div>" +
          '<div class="detail-item"><label>Trạng thái đơn</label><span>' +
            OF.badgeHTML(OF.ORDER_STATUS, orderStatus) + "</span></div>" +
        "</div>" +
      "</div>";
    OF.ui.openModal({
      title: late ? "Kết quả thanh toán" : "Thanh toán thành công",
      size: "sm",
      body,
      footer: '<button class="btn" data-close>Đóng</button>' +
        '<a class="btn btn-primary" href="orders.php">Xem đơn hàng</a>',
    });
  }

  function startCountdown(el, expiresAt) {
    if (!el) return null;
    const end = new Date(String(expiresAt || "").replace(" ", "T")).getTime();
    if (!end || isNaN(end)) {
      el.textContent = "";
      return null;
    }
    const tick = () => {
      if (!el.isConnected) return;
      const diff = Math.floor((end - Date.now()) / 1000);
      if (diff <= 0) {
        el.textContent = "Mã QR đã hết hạn – bấm “Tạo mã QR mới” nếu đơn còn hiệu lực.";
        el.classList.add("is-expired");
        return;
      }
      const m = Math.floor(diff / 60);
      const s = diff % 60;
      el.textContent = "Mã QR còn hiệu lực " + m + " phút " + String(s).padStart(2, "0") + " giây";
      el.classList.remove("is-expired");
    };
    tick();
    return setInterval(tick, 1000);
  }

  function show(qr, opts) {
    opts = opts || {};
    if (currentModal) {
      const old = currentModal;
      currentModal = null;
      try { old.close(); } catch (e) { /* ignore */ }
    }

    let tickTimer = null;
    let pollTimer = null;
    let polling = false;
    let confirming = false;
    const isCustomer = ((OF.session.getUser() || {}).role || "") === "customer";
    const confirmBtn = isCustomer
      ? '<button class="btn btn-success" id="qrConfirm">' + OF.icon("check", "icon-sm") + " Tôi đã chuyển khoản</button>"
      : "";

    currentModal = OF.ui.openModal({
      title: "Thanh toán QR – đơn " + qr.order_code,
      size: "lg",
      body: buildBody(qr, isCustomer),
      footer:
        '<button class="btn" data-close>Đóng</button>' +
        confirmBtn +
        '<button class="btn btn-primary" id="qrRefresh">' + OF.icon("refresh", "icon-sm") + " Tạo mã QR mới</button>",
      onOpen(api) {
        const img = api.el.querySelector("#qrImage");
        if (img) {
          img.addEventListener("error", () => {
            img.style.display = "none";
            OF.ui.toast("error", "Không tải được ảnh QR. Kiểm tra kết nối mạng hoặc mở ảnh QR trực tiếp.");
          });
        }
        api.el.querySelectorAll("[data-copy]").forEach((btn) =>
          btn.addEventListener("click", () => copyText(btn.getAttribute("data-copy"))));
        tickTimer = startCountdown(api.el.querySelector("#qrCountdown"), qr.expires_at);

        api.el.querySelector("#qrRefresh").addEventListener("click", async () => {
          const btn = api.el.querySelector("#qrRefresh");
          btn.disabled = true;
          const res = await OF.api.post("payments/qr_create.php", { payment_id: qr.payment_id });
          btn.disabled = false;
          if (!res.success) {
            OF.ui.toast("error", res.message || "Không tạo được mã QR mới.");
            return;
          }
          OF.ui.toast("success", "Đã tạo mã QR mới. Nội dung chuyển khoản đã thay đổi.");
          show(res.data, opts);
        });

        const confirmEl = api.el.querySelector("#qrConfirm");
        if (confirmEl) {
          confirmEl.addEventListener("click", async () => {
            if (confirming) return;
            confirming = true;
            const originalHTML = confirmEl.innerHTML;
            confirmEl.disabled = true;
            confirmEl.innerHTML = "Đang xác nhận…";
            try {
              const res = await OF.api.post("payments/customer_confirm.php", {
                qr_id: qr.qr_id,
                payment_id: qr.payment_id,
              });
              if (!res.success) {
                OF.ui.toast("error", res.message || "Không thể xác nhận thanh toán.");
                return;
              }
              if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
              }
              api.close();
              showResult(qr, res.data || {});
              if (typeof opts.onPaid === "function") opts.onPaid(res.data || {});
            } finally {
              confirming = false;
              if (confirmEl.isConnected) {
                confirmEl.disabled = false;
                confirmEl.innerHTML = originalHTML;
              }
            }
          });
        }

        pollTimer = setInterval(async () => {
          if (polling || !api.el.isConnected) return;
          polling = true;
          try {
            const res = await OF.api.get("payments/qr_status.php", "id=" + qr.qr_id);
            if (!res.success) return;
            const d = res.data;
            if (d.status === "paid") {
              if (pollTimer) clearInterval(pollTimer);
              pollTimer = null;
              api.close();
              showResult(qr, d);
              if (typeof opts.onPaid === "function") opts.onPaid(d);
            } else if (d.status === "expired") {
              const el = api.el.querySelector("#qrCountdown");
              if (el) {
                el.textContent = "Mã QR đã hết hạn – bấm “Tạo mã QR mới” nếu đơn còn hiệu lực.";
                el.classList.add("is-expired");
              }
            } else if (d.status === "replaced") {
              const el = api.el.querySelector("#qrCountdown");
              if (el) el.textContent = "Mã QR này đã được thay thế bởi mã mới hơn.";
            }
          } finally {
            polling = false;
          }
        }, 5000);
      },
      onClose() {
        if (tickTimer) clearInterval(tickTimer);
        if (pollTimer) clearInterval(pollTimer);
        tickTimer = null;
        pollTimer = null;
        currentModal = null;
      },
    });
    return currentModal;
  }

  async function open(opts) {
    opts = opts || {};
    const body = {};
    if (opts.paymentId) body.payment_id = Number(opts.paymentId);
    if (opts.orderId) body.order_id = Number(opts.orderId);
    if (!body.payment_id && !body.order_id) {
      OF.ui.toast("error", "Thiếu thông tin giao dịch để tạo QR.");
      return null;
    }
    const res = await OF.api.post("payments/qr_create.php", body);
    if (!res.success) {
      OF.ui.toast("error", res.message || "Không tạo được mã QR thanh toán.");
      return null;
    }
    return show(res.data, opts);
  }

  OF.paymentQR = { open: open };
})();
