/**
 * Admin alerts: dismiss is client-only — log_id list in localStorage (`dismissed_alerts`), no server round-trip.
 */
(function () {
  "use strict";

  var STORAGE_KEY = "dismissed_alerts";

  function getDismissedIds() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      if (raw == null || raw === "") {
        return [];
      }
      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        return [];
      }
      var out = [];
      for (var i = 0; i < parsed.length; i += 1) {
        var n = parseInt(String(parsed[i]), 10);
        if (n > 0 && out.indexOf(n) === -1) {
          out.push(n);
        }
      }
      return out;
    } catch (e) {
      return [];
    }
  }

  function saveDismissedIds(ids) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch (e) {
      /* quota / private mode: still hide the row in-session */
    }
  }

  function addDismissedId(id) {
    var n = parseInt(String(id), 10);
    if (n <= 0) {
      return;
    }
    var list = getDismissedIds();
    if (list.indexOf(n) === -1) {
      list.push(n);
    }
    saveDismissedIds(list);
  }

  var root = document.getElementById("alert-feed");
  if (!root) {
    return;
  }

  var list = document.getElementById("alert-feed-list");
  if (!list) {
    return;
  }

  var zero = document.getElementById("alert-feed-zero");

  function isHiddenItem(el) {
    return el && el.classList && el.classList.contains("alert-feed__item--dismissed");
  }

  function countVisible() {
    var items = list.querySelectorAll(".alert-feed__item");
    var n = 0;
    for (var j = 0; j < items.length; j += 1) {
      if (!isHiddenItem(items[j])) {
        n += 1;
      }
    }
    return n;
  }

  function updateEmptyState() {
    if (!zero) {
      return;
    }
    if (countVisible() === 0) {
      zero.removeAttribute("hidden");
    } else {
      zero.setAttribute("hidden", "hidden");
    }
  }

  function applyHiddenFromStorage() {
    var dismissed = getDismissedIds();
    if (dismissed.length === 0) {
      updateEmptyState();
      return;
    }
    var set = {};
    for (var i = 0; i < dismissed.length; i += 1) {
      set[dismissed[i]] = true;
    }
    var items = list.querySelectorAll(".alert-feed__item[data-log-id]");
    for (var k = 0; k < items.length; k += 1) {
      var li = items[k];
      var id = parseInt(li.getAttribute("data-log-id") || "0", 10);
      if (id > 0 && set[id]) {
        li.classList.add("alert-feed__item--dismissed");
      }
    }
    updateEmptyState();
  }

  applyHiddenFromStorage();

  root.addEventListener("click", function (e) {
    var btn = e.target && e.target.closest ? e.target.closest(".alert-feed__dismiss") : null;
    if (!btn || !root.contains(btn)) {
      return;
    }
    var item = btn.closest(".alert-feed__item");
    if (!item) {
      return;
    }
    var logId = btn.getAttribute("data-log-id");
    if (logId == null || logId === "") {
      return;
    }
    e.preventDefault();
    addDismissedId(logId);
    item.classList.add("alert-feed__item--dismissed");
    updateEmptyState();
  });
})();
