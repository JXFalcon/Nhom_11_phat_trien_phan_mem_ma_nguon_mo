/* ============================================================
   Shopee – api.js
   Client gọi backend PHP (API JSON + Bearer token).
   - Cùng origin khi chạy bằng Apache/php -S.
   - Tự trỏ về http://127.0.0.1:8090 khi chạy Live Server (cổng khác).
   ============================================================ */
(function () {
  "use strict";
  const OF = window.OF = window.OF || {};
  const TOKEN_KEY = "orderflow.token";
  const USER_KEY = "orderflow.user";

  function getToken() { return localStorage.getItem(TOKEN_KEY) || ""; }
  function setToken(t) { t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY); }
  function getUser() {
    try { return JSON.parse(localStorage.getItem(USER_KEY) || "null"); }
    catch (e) { return null; }
  }
  function setUser(u) { u ? localStorage.setItem(USER_KEY, JSON.stringify(u)) : localStorage.removeItem(USER_KEY); }
  function clearSession() { setToken(""); setUser(""); }

  /** URL gốc của backend API */
  function apiBase() {
    if (location.protocol === "file:") return "http://127.0.0.1:8090";
    const port = location.port;
    // Live Server mặc định cổng 5500 → gọi backend cổng 8090
    if (port === "5500") return "http://127.0.0.1:8090";
    return ""; // cùng origin (Apache :80 hoặc php -S :8090)
  }

  async function request(method, path, body) {
    const headers = { "Content-Type": "application/json" };
    const t = getToken();
    if (t) headers["Authorization"] = "Bearer " + t;
    let res;
    try {
      res = await fetch(apiBase() + "/api/" + path, {
        method,
        headers,
        body: body !== undefined && body !== null ? JSON.stringify(body) : undefined,
        credentials: "include",
      });
    } catch (e) {
      return {
        success: false,
        message: "Không kết nối được máy chủ backend. Hãy chạy PHP server (XAMPP hoặc php -S 127.0.0.1:8090).",
      };
    }
    let data = null;
    try { data = await res.json(); }
    catch (e) { data = { success: false, message: "Phản hồi máy chủ không hợp lệ (HTTP " + res.status + ")." }; }

    // Hết phiên / chưa đăng nhập → về trang login (có chống vòng lặp)
    if (res.status === 401 && path.indexOf("auth/") !== 0) {
      clearSession();
      if (location.pathname.indexOf("login") === -1) {
        const BOUNCE_KEY = "orderflow.authBounce";
        const last = Number(sessionStorage.getItem(BOUNCE_KEY) || 0);
        if (Date.now() - last > 5000) {
          sessionStorage.setItem("orderflow.authInfo", data && data.message ? data.message : "Phiên đăng nhập đã hết hoặc chưa đăng nhập. Vui lòng đăng nhập lại.");
          sessionStorage.setItem(BOUNCE_KEY, String(Date.now()));
          location.replace("login.html");
        }
        // Nếu mới bounce liên tục (< 5s) thì dừng, không redirect thêm
        // để tránh vòng lặp vô hạn giữa trang hiện tại và trang đăng nhập.
      }
    }
    return data;
  }

  const api = {
    base: apiBase,
    get: (path, query) => request("GET", path + (query ? "?" + query : "")),
    post: (path, body) => request("POST", path, body || {}),
    patch: (path, body) => request("PATCH", path, body || {}),
    put: (path, body) => request("PUT", path, body || {}),
    del: (path) => request("DELETE", path),
    request,
  };

  OF.session = { getToken, setToken, getUser, setUser, clear: clearSession };
  OF.api = api;
})();
