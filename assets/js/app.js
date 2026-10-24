(function () {
    if (!document.querySelector('meta[name="viewport"]')) {
        var meta = document.createElement("meta");
        meta.name = "viewport";
        meta.content = "width=device-width, initial-scale=1.0";
        document.head.appendChild(meta);
    }

    var menu = document.querySelector(".menu-lateral");
    if (!menu) {
        return;
    }

    var bouton = document.createElement("button");
    bouton.type = "button";
    bouton.className = "bouton-menu";
    bouton.setAttribute("aria-label", "Ouvrir le menu");
    bouton.setAttribute("aria-expanded", "false");
    bouton.innerHTML =
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
        '<line x1="4" y1="6" x2="20" y2="6"></line>' +
        '<line x1="4" y1="12" x2="20" y2="12"></line>' +
        '<line x1="4" y1="18" x2="20" y2="18"></line>' +
        "</svg>";

    var voile = document.createElement("div");
    voile.className = "menu-voile";

    document.body.appendChild(bouton);
    document.body.appendChild(voile);

    function fermerMenu() {
        document.body.classList.remove("menu-ouvert");
        bouton.setAttribute("aria-expanded", "false");
        bouton.setAttribute("aria-label", "Ouvrir le menu");
    }

    function basculerMenu() {
        var ouvert = document.body.classList.toggle("menu-ouvert");
        bouton.setAttribute("aria-expanded", ouvert ? "true" : "false");
        bouton.setAttribute("aria-label", ouvert ? "Fermer le menu" : "Ouvrir le menu");
    }

    bouton.addEventListener("click", basculerMenu);
    voile.addEventListener("click", fermerMenu);

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            fermerMenu();
        }
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth > 768) {
            fermerMenu();
        }
    });

    document.querySelectorAll(".tableau-donnees, .tableau-equipe").forEach(function (table) {
        if (table.parentElement && table.parentElement.classList.contains("tableau-scroll")) {
            return;
        }
        var wrap = document.createElement("div");
        wrap.className = "tableau-scroll";
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });
})();
