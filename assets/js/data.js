/* ============================================================
   OrderFlow – data.js (giai đoạn kết nối backend)
   Giữ lại: hàm định dạng, nhãn/badge trạng thái, helper.
   Dữ liệu nghiệp vụ: lấy từ API MySQL (xem assets/js/api.js).
   ============================================================ */
window.OF = (function () {
  "use strict";

  /* ---------------- Định dạng ---------------- */
  const fmt = {
    money(n) {
      const v = Math.round(Number(n) || 0);
      return v.toLocaleString("vi-VN") + " ₫";
    },
    moneyShort(n) {
      const v = Number(n) || 0;
      if (v >= 1e9) return (v / 1e9).toFixed(1).replace(".0", "") + " tỷ";
      if (v >= 1e6) return (v / 1e6).toFixed(1).replace(".0", "") + " tr";
      if (v >= 1e3) return Math.round(v / 1e3) + "k";
      return String(v);
    },
    date(iso) {
      if (!iso) return "—";
      const d = new Date(String(iso).replace(" ", "T"));
      if (isNaN(d)) return "—";
      const p = (x) => String(x).padStart(2, "0");
      return p(d.getDate()) + "/" + p(d.getMonth() + 1) + "/" + d.getFullYear();
    },
    dateTime(iso) {
      if (!iso) return "—";
      const d = new Date(String(iso).replace(" ", "T"));
      if (isNaN(d)) return "—";
      const p = (x) => String(x).padStart(2, "0");
      return p(d.getDate()) + "/" + p(d.getMonth() + 1) + "/" + d.getFullYear() +
        " " + p(d.getHours()) + ":" + p(d.getMinutes());
    },
    dateInput(iso) {
      if (!iso) return "";
      const d = new Date(String(iso).replace(" ", "T"));
      const p = (x) => String(x).padStart(2, "0");
      return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate());
    },
    dayKey(iso) {
      const d = new Date(String(iso).replace(" ", "T"));
      const p = (x) => String(x).padStart(2, "0");
      return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate());
    },
    dayLabel(iso) {
      const d = new Date(String(iso).replace(" ", "T"));
      return String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0");
    },
  };

  /* ---------------- Nhãn & màu trạng thái ---------------- */
  const ORDER_STATUS = {
    cho_xac_nhan: { label: "Chờ xác nhận", badge: "badge-orange" },
    da_xac_nhan: { label: "Đã xác nhận", badge: "badge-indigo" },
    dang_giao: { label: "Đang giao", badge: "badge-blue" },
    da_giao: { label: "Đã giao", badge: "badge-green" },
    da_huy: { label: "Đã hủy", badge: "badge-red" },
  };

  const RETURN_STATUS = {
    cho_xu_ly: { label: "Chờ xử lý", badge: "badge-orange" },
    da_chap_nhan: { label: "Đã chấp nhận", badge: "badge-indigo" },
    tu_choi: { label: "Từ chối", badge: "badge-red" },
    da_hoan_tat: { label: "Đã hoàn tất", badge: "badge-green" },
  };

  const SHIPMENT_STATUS = {
    cho_lay_hang: { label: "Chờ lấy hàng", badge: "badge-gray" },
    dang_van_chuyen: { label: "Đang vận chuyển", badge: "badge-blue" },
    da_giao: { label: "Đã giao", badge: "badge-green" },
    that_bai: { label: "Giao thất bại", badge: "badge-red" },
  };

  const PAYMENT_STATUS = {
    thanh_cong: { label: "Thành công", badge: "badge-green" },
    cho_xu_ly: { label: "Chờ xử lý", badge: "badge-orange" },
    that_bai: { label: "Thất bại", badge: "badge-red" },
  };

  const PAYMENT_METHODS = {
    cod: "COD (thanh toán khi nhận)",
    bank: "Chuyển khoản ngân hàng",
    momo: "Ví MoMo",
  };

  const CARRIERS = ["Giao Hàng Nhanh (GHN)", "J&T Express", "Viettel Post", "Ninja Van"];

  /* ---------------- Helpers dùng chung ---------------- */
  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  function statusInfo(map, status) {
    return map[status] || { label: status, badge: "badge-gray" };
  }

  function badgeHTML(map, status) {
    const info = statusInfo(map, status);
    return '<span class="badge ' + info.badge + '">' + info.label + "</span>";
  }

  function uid(prefix) {
    return prefix + "-" + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
  }

  return {
    fmt,
    badgeHTML,
    escapeHtml,
    statusInfo,
    uid,
    ORDER_STATUS,
    RETURN_STATUS,
    SHIPMENT_STATUS,
    PAYMENT_STATUS,
    PAYMENT_METHODS,
    CARRIERS,
    DEMO_ACCOUNTS: [
      { email: "admin@orderflow.vn", password: "123456", label: "Quản trị viên" },
      { email: "nhanvien@orderflow.vn", password: "123456", label: "Nhân viên xử lý đơn" },
      { email: "khachhang@orderflow.vn", password: "123456", label: "Khách hàng" },
    ],
  };
})();
