/* Halaman hasil: render rekomendasi sistem pakar, tukar part, simpan, pesan, umpan balik. */
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var token = new URLSearchParams(location.search).get("k");
  var data = null;      // seluruh respons
  var builds = [];      // [utama, alt1, alt2] (urutan dapat bertukar saat "Jadikan Utama")

  if (!token) { fail("Kode rekomendasi tidak ditemukan. Silakan isi formulir konsultasi terlebih dahulu."); return; }

  function fail(msg) {
    document.querySelector("main").innerHTML =
      '<div class="pd-panel" style="text-align:center"><h2>Rekomendasi tidak dapat ditampilkan</h2><p>' + WS.esc(msg) +
      '</p><p style="margin-top:18px"><a class="btn dark" href="recommendation.html">Isi Formulir Konsultasi</a></p></div>';
  }

  WS.api("api/result.php?k=" + encodeURIComponent(token)).then(function (d) {
    data = d; builds = [d.utama].concat(d.alternatif); render();
  }).catch(function (e) { fail(e.message); });

  function icon(n) { return '<svg class="i"><use href="#' + n + '"></use></svg>'; }

  function render() {
    var b = builds[0], inp = data.input, pk = data.paket_pakar;
    var errs = b.checks.filter(function (c) { return c.t === "err"; }).length;
    var warns = b.checks.filter(function (c) { return c.t === "warn"; }).length;

    $("hPill").innerHTML = icon("ok") + (errs ? " Ada " + errs + " masalah kompatibilitas, konsultasikan dengan admin"
      : warns ? " Kompatibel, dengan " + warns + " catatan" : " Analisis Kompatibilitas Soket &amp; Daya: 100% Cocok (Bebas Bottleneck)");
    $("hWork").textContent = inp.work_label;
    $("hBud").textContent = WS.rp(b.budget) + (inp.budget_label ? " (" + inp.budget_label + ")" : "");
    $("sCount").textContent = b.items.length + " Komponen Modular Terpilih";
    $("sTotal").textContent = WS.rp(b.total);
    $("sWatt").textContent = "~" + b.watt + " Watt (Rekomendasi PSU " + b.psu_min + "W)";
    $("hKonf").textContent = "Konfigurasi Spesifikasi Komponen Pilihan (" + (pk ? pk.nama : b.label) + ")";
    $("hTitle").textContent = b.title;
    $("hPrice").textContent = WS.rp(b.total);
    $("hOld").style.display = "none";
    $("hSave").textContent = b.over_budget
      ? "Melebihi budget " + WS.rp(b.budget) + " (budget minimal konfigurasi ini)"
      : "Total estimasi " + b.items.length + " part • sisa budget " + WS.rp(b.budget - b.total);

    // alasan: paket pakar toko (bila ada) + alasan sistem + hasil pengecekan
    var why = [];
    if (pk) { why.push("<b>Paket pakar toko: " + WS.esc(pk.nama) + "</b> (" + WS.esc(pk.judul) + ")"); pk.alasan.forEach(function (a) { why.push(WS.esc(a)); }); }
    b.why.forEach(function (w) { why.push(WS.esc(w)); });
    b.checks.forEach(function (c) { why.push((c.t === "ok" ? "✔ " : c.t === "warn" ? "⚠ " : "✖ ") + WS.esc(c.m)); });
    $("hWhy").innerHTML = why.map(function (w) { return "<li>" + icon("ok") + "<span>" + w + "</span></li>"; }).join("");
    $("hTrace").innerHTML = b.trace.map(function (t) { return "<li>" + WS.esc(t) + "</li>"; }).join("");

    $("hParts").innerHTML = b.items.map(function (it, i) {
      var opts = it.alt.map(function (a) {
        var d = a.harga - it.harga;
        return '<option value="' + WS.esc(a.kode) + '">' + WS.esc(a.nama) + " (" + WS.rp(a.harga) + (d ? ", " + (d > 0 ? "+" : "-") + WS.rp(Math.abs(d)) : "") + ")</option>";
      }).join("");
      return '<div class="hs-part">' + icon(it.ico) + "<div><small>" + WS.esc(it.label || it.cat) + " <em>" + WS.esc(it.brand) + "</em></small>" +
        "<b>" + WS.esc(it.nama) + "</b><span>" + WS.esc(it.spek) + "</span>" +
        '<span class="hs-why-part">' + WS.esc(it.alasan) + "</span>" +
        (it.stok !== "Stok Tersedia" ? '<span class="hs-stok">' + WS.esc(it.stok) + "</span>" : "") +
        (opts ? '<select class="hs-swap" data-i="' + i + '" aria-label="Tukar ' + WS.esc(it.cat) + '"><option value="">Tukar part…</option>' + opts + "</select>" : "") +
        '</div><div class="hs-pr"><b>' + WS.rp(it.harga) + "</b></div></div>";
    }).join("");

    renderAlt();
    var lines = b.items.map(function (x) { return "- " + x.cat + ": " + x.nama + " (" + WS.rp(x.harga) + ")"; });
    $("bOrder").setAttribute("data-wa", "Halo admin Walisongo Komputer, saya ingin memesan rakitan hasil rekomendasi sistem pakar (kode " + token + "):\n" +
      lines.join("\n") + "\nTotal estimasi: " + WS.rp(b.total) + "\nMohon dicek ketersediaan stok dan jadwal perakitannya.");
  }

  function renderAlt() {
    $("hAlt").innerHTML = builds.slice(1).map(function (a, i) {
      var top = a.items.slice(0, 3).map(function (x) {
        return "<li>" + icon("ok") + "<span>" + WS.esc(x.cat) + ": " + WS.esc(x.nama) + "</span></li>";
      }).join("");
      var diff = a.total - builds[0].total;
      return '<article class="hs-card"><span class="hs-up">' + WS.esc(a.label) + "</span><small>" + WS.esc(a.prio_label) + "</small><h3>" +
        WS.esc(a.title) + "</h3><p>" + (diff === 0 ? "Total sama dengan rekomendasi utama" : (diff > 0 ? "+" : "-") + WS.rp(Math.abs(diff)) + " dibanding rekomendasi utama") +
        "</p><ul>" + top + '</ul><div class="hs-foot"><div><small>Total estimasi</small><b>' + WS.rp(a.total) +
        '</b></div><a href="#" class="btn dark" data-promote="' + (i + 1) + '">Jadikan Utama</a></div></article>';
    }).join("");
  }

  // tukar part -> validasi ke server (kompatibilitas, watt, total)
  document.addEventListener("change", function (e) {
    var sel = e.target.closest(".hs-swap");
    if (!sel || !sel.value) { return; }
    var b = builds[0], idx = +sel.dataset.i;
    var codes = b.items.map(function (x, i) { return i === idx ? sel.value : x.kode; });
    WS.api("api/validate.php", { kodes: codes, work: data.input.work }).then(function (r) {
      var alt = b.items[idx].alt.filter(function (a) { return a.kode === sel.value; })[0];
      var old = b.items[idx];
      b.items[idx] = Object.assign({}, old, { kode: alt.kode, nama: alt.nama, harga: alt.harga, spek: "Dipilih manual (menggantikan " + old.nama + ")",
        alasan: "Ditukar manual oleh Anda; kompatibilitas diperiksa ulang oleh sistem.", alt: [{ kode: old.kode, nama: old.nama, harga: old.harga }].concat(old.alt.filter(function (a) { return a.kode !== alt.kode; })) });
      b.total = r.total; b.watt = r.watt; b.psu_min = r.psu_min; b.checks = r.checks;
      b.title = b.title + " (dimodifikasi)";
      render();
      WS.toast(r.checks.some(function (c) { return c.t === "err"; }) ? "Perhatian: ada masalah kompatibilitas pada susunan ini." : "Part berhasil ditukar, kompatibilitas aman.",
        r.checks.some(function (c) { return c.t === "err"; }));
    }).catch(function (err) { WS.toast(err.message, true); sel.value = ""; });
  });

  document.addEventListener("click", function (e) {
    var pro = e.target.closest("[data-promote]");
    if (pro) { e.preventDefault(); var i = +pro.dataset.promote; var t = builds[0]; builds[0] = builds[i]; builds[i] = t; render(); window.scrollTo({ top: 0, behavior: "smooth" }); return; }
    if (e.target.closest("#bPdf, #bPrint")) { e.preventDefault(); window.print(); return; }
    if (e.target.closest("#bSave")) {
      e.preventDefault();
      var url = location.origin + location.pathname + "?k=" + token;
      (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () {
        WS.toast("Tautan rekomendasi disalin. Kode Anda: " + token);
      }).catch(function () { WS.toast("Simpan kode rekomendasi Anda: " + token); });
      return;
    }
    if (e.target.closest("#bYes")) {
      e.preventDefault();
      WS.api("api/feedback.php", { k: token, pas: 1, rating: 5 }).then(function () {
        WS.toast("Terima kasih! Umpan balik Anda tersimpan. Kode rekomendasi: " + token);
      }).catch(function (err) { WS.toast(err.message, true); });
      return;
    }
    if (e.target.closest(".hs-fb .pd-back") && navigator.sendBeacon) {
      navigator.sendBeacon("api/feedback.php", new Blob([JSON.stringify({ k: token, pas: 0 })], { type: "application/json" }));
    }
  });
})();
