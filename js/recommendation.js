/* Halaman form: ringkasan live, budget spesifik, kirim ke sistem pakar. */
(function () {
  var $ = function (s) { return document.querySelector(s); };
  var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };
  var checked = function (n) { return $('input[name="' + n + '"]:checked'); };
  var exact = $("#budgetExact");

  function exactValue() {
    var v = exact ? parseInt(exact.value, 10) : 0;
    return v > 0 ? v : 0;
  }

  function summary() {
    var parts = $$('input[name="part"]:checked');
    var w = checked("work"), b = checked("budget"), p = checked("prio"), br = checked("brand");
    var sw = $$('input[name="sw"]:checked').map(function (x) { return x.dataset.s; });

    $("#sPart").textContent = parts.length === 8 ? "Rakit Full (8 Part)" : parts.map(function (x) { return x.dataset.s; }).join(", ") || "-";
    $("#sCount").textContent = parts.length + " dari 8 komponen";
    $("#sWork").textContent = w ? w.dataset.t.replace(/&amp;/g, "&") : "-";
    $("#sSw").textContent = sw.length ? sw.length + " software dipilih" : "Belum ada software";

    var ex = exactValue();
    var label = ex ? WS.rp(ex) : (b ? b.dataset.t.replace(/&lt;/g, "<").replace(/&gt;/g, ">") : "-");
    $("#sBud").textContent = label;
    $("#sBudS").textContent = ex ? "Budget spesifik" : "Rentang budget";
    $("#cur").textContent = label;
    $("#rangeMid").textContent = ex ? "Budget spesifik: " + WS.rp(ex) : "Rp " + label.replace(/^[<>] /, "") + " (sekitar Rp " + (b ? b.dataset.mid : "-") + " Juta)";
    $("#fill").style.width = (ex ? Math.min(95, Math.max(5, ex / 40000000 * 100)) : (b ? b.dataset.pct : 0)) + "%";

    $("#sPrio").textContent = p ? p.dataset.t.replace(/&amp;/g, "&") : "-";
    $("#sBrand").textContent = br ? br.dataset.t : "-";
  }

  document.addEventListener("change", function (e) {
    if (e.target.name === "budget" && exact) { exact.value = ""; }
    summary();
  });
  if (exact) { exact.addEventListener("input", function () {
    if (exactValue()) { $$('input[name="budget"]').forEach(function (r) { r.checked = false; }); }
    else if (!checked("budget")) { var d = $('input[name="budget"][data-v="10-18"]'); if (d) { d.checked = true; } }
    summary();
  }); }

  var all = $("#all");
  if (all) { all.addEventListener("click", function () {
    $$('input[name="part"]').forEach(function (c) { c.checked = true; });
    summary();
  }); }

  var go = $("#go");
  go.addEventListener("click", function (e) {
    e.preventDefault();
    var parts = $$('input[name="part"]:checked').map(function (x) { return x.dataset.s; });
    if (!parts.length) { WS.toast("Pilih minimal satu komponen terlebih dahulu.", true); return; }
    var b = checked("budget");
    var payload = {
      parts: parts,
      work: checked("work").dataset.v,
      budget: b ? b.dataset.v : "10-18",
      budget_exact: exactValue(),
      prio: checked("prio").dataset.v,
      brand: checked("brand").dataset.v,
      sw: $$('input[name="sw"]:checked').map(function (x) { return x.dataset.s; })
    };
    var old = go.innerHTML;
    go.style.pointerEvents = "none";
    go.textContent = "Sistem pakar sedang menganalisis…";
    WS.api("api/recommend.php", payload).then(function (d) {
      location.href = "result.html?k=" + encodeURIComponent(d.token);
    }).catch(function (err) {
      WS.toast(err.message, true);
      go.innerHTML = old; go.style.pointerEvents = "";
    });
  });

  summary();
})();
