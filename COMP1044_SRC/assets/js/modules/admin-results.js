/**
 * Admin results: toggle .filter-panel with class .active (native JS only).
 */
(function () {
    "use strict";

    var panelId = "filter-panel";
    var btnId = "filter-panel-toggle";

    function init() {
        var panel = document.getElementById(panelId);
        var btn = document.getElementById(btnId);
        if (!panel || !btn) {
            return;
        }

        function setOpen(open) {
            panel.classList.toggle("active", open);
            btn.setAttribute("aria-expanded", open ? "true" : "false");
            panel.setAttribute("aria-hidden", open ? "false" : "true");
            if (open) {
                panel.removeAttribute("inert");
            } else {
                panel.setAttribute("inert", "");
            }
        }

        setOpen(false);

        btn.addEventListener("click", function () {
            setOpen(!panel.classList.contains("active"));
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
