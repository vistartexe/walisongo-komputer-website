/* Helper bersama: format rupiah, escape HTML, panggilan API, toast. */
window.WS = (function () {
  function rp(n) { return "Rp " + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // fetch JSON; melempar Error dengan pesan dari server bila gagal
  function api(url, body) {
    var opt = body === undefined ? {} : {
      method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body)
    };
    return fetch(url, opt).then(function (r) {
      return r.text().then(function (txt) {
        var d;
        try { d = JSON.parse(txt); } catch (e) {
          throw new Error("Server tidak mengembalikan JSON. Pastikan halaman dibuka lewat http://localhost (PHP aktif), bukan file://");
        }
        if (!r.ok) { throw new Error(d.error || "Permintaan gagal (" + r.status + ")"); }
        return d;
      });
    });
  }

  var timer;
  function toast(msg, isErr) {
    var t = document.getElementById("ws-toast");
    if (!t) { t = document.createElement("div"); t.id = "ws-toast"; t.setAttribute("role", "status"); document.body.appendChild(t); }
    t.textContent = msg;
    t.className = "ws-toast show" + (isErr ? " err" : "");
    clearTimeout(timer);
    timer = setTimeout(function () { t.className = "ws-toast"; }, 4200);
  }

  return { rp: rp, esc: esc, api: api, toast: toast };
})();
