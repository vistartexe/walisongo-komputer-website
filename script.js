/* Fungsi bersama: tautan WhatsApp dan peta toko.
   GANTI nomor dan alamat di bawah ini dengan data toko yang sebenarnya. */

var WA_NUMBER = "6281290242008";
var MAP_QUERY = "Jl. Prof. Dr. Hamka, Tambakaji, Ngaliyan, Kota Semarang";

function waLink(text) {
  return "https://wa.me/" + WA_NUMBER + "?text=" + encodeURIComponent(text);
}

document.addEventListener("click", function (e) {
  var wa = e.target.closest("[data-wa]");
  if (wa) {
    e.preventDefault();
    window.open(waLink(wa.getAttribute("data-wa")), "_blank", "noopener");
    return;
  }
  var map = e.target.closest("[data-map]");
  if (map) {
    e.preventDefault();
    window.open("https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(MAP_QUERY), "_blank", "noopener");
  }
});
