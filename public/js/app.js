/* ==========================================================================
   App JS scaffold — dropdowns, modals, view switches (no dependencies)
   ========================================================================== */


(function () {
  "use strict";

  // Utilities
  const qs = (sel, ctx = document) => ctx.querySelector(sel);
  const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const on = (el, evt, fn, opts) => el && el.addEventListener(evt, fn, opts);
  const closest = (el, sel) => el && (el.closest ? el.closest(sel) : null);

  // State
  let openDropdown = null;
  let openModal = null;

  // ---------- DROPDOWNS ----------
  function closeAnyDropdown() {
    if (openDropdown) {
      openDropdown.classList.remove("open");
      const btn = openDropdown._trigger;
      if (btn) {
        btn.setAttribute("aria-expanded", "false");
      }
      openDropdown = null;
    }
  }

  function toggleDropdown(dropdown, trigger) {
    if (!dropdown) return;
    if (openDropdown && openDropdown !== dropdown) {
      closeAnyDropdown();
    }
    const willOpen = !dropdown.classList.contains("open");
    dropdown.classList.toggle("open", willOpen);
    if (trigger) {
      trigger.setAttribute("aria-expanded", String(willOpen));
      dropdown._trigger = trigger;
    }
    openDropdown = willOpen ? dropdown : null;
  }

  // Delegate: any element with [data-dropdown] toggles the nearest .dropdown
  document.addEventListener("click", (e) => {
    const dropdownTrigger = closest(e.target, "[data-dropdown]");
    if (dropdownTrigger) {
      const wrapper = closest(dropdownTrigger, ".dropdown") || dropdownTrigger.parentElement;
      toggleDropdown(wrapper, dropdownTrigger);
      return;
    }

    // Avatar → menu via aria-controls
    const ctrl = closest(e.target, "[aria-controls]");
    if (ctrl) {
      const menuId = ctrl.getAttribute("aria-controls");
      const menu = document.getElementById(menuId) || qs(`#${CSS.escape(menuId)}`);
      const dd = closest(menu, ".dropdown");
      if (dd) toggleDropdown(dd, ctrl);
      else if (menu) {
        // allow non-.dropdown targets to toggle visibility
        menu.classList.toggle("open");
      }
      return;
    }

    // Click outside closes dropdowns
    if (openDropdown && !openDropdown.contains(e.target)) {
      closeAnyDropdown();
    }
  });

  // ---------- MODALS ----------
  function findModalById(modalId) {
    // Our Blade modal sets aria-labelledby to the same id we pass in.
    // So we open any .modal that has aria-labelledby equal to modalId.
    return qs(`.modal[aria-labelledby="${CSS.escape(modalId)}"]`);
  }

  function openModalById(modalId) {
    const modal = findModalById(modalId);
    if (!modal) return;

    // Close others
    if (openModal && openModal !== modal) closeModal(openModal);

    modal.classList.add("is-open");
    openModal = modal;

    // Focus first focusable
    const focusables = qsa(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
      modal
    ).filter(el => !el.hasAttribute("disabled") && !el.getAttribute("aria-hidden"));
    if (focusables.length) focusables[0].focus();
  }

  function closeModal(modal) {
    if (!modal) modal = openModal;
    if (!modal) return;
    modal.classList.remove("is-open");
    openModal = null;
  }

  // Open modal (any element with data-open="modalId")
  document.addEventListener("click", (e) => {
    const opener = closest(e.target, "[data-open]");
    if (opener) {
      e.preventDefault();
      const id = opener.getAttribute("data-open");
      openModalById(id);
      return;
    }

    // Dismiss modal
    const dismiss = closest(e.target, '[data-dismiss="modal"]');
    if (dismiss) {
      e.preventDefault();
      closeModal();
      return;
    }

    // Click on backdrop closes
    if (openModal && e.target.classList.contains("modal-backdrop")) {
      closeModal();
      return;
    }
  });

  // Esc closes dropdowns/modals
  on(document, "keydown", (e) => {
    if (e.key === "Escape") {
      if (openModal) { closeModal(); return; }
      if (openDropdown) { closeAnyDropdown(); return; }
    }
  });

  // ---------- FILE VIEW SWITCH (list/grid) ----------
  function setViewPressedState(group, view) {
    qsa("button", group).forEach(btn => {
      const isActive = btn.getAttribute("data-view") === view;
      btn.setAttribute("aria-pressed", String(isActive));
    });
  }

  function applyFilesView(view) {
    const list = qs(".file-list");
    const grid = qs(".file-grid");
    if (!list && !grid) return;

    if (grid && list) {
      if (view === "grid") { if (grid) grid.style.display = ""; if (list) list.style.display = "none"; }
      else { if (list) list.style.display = ""; if (grid) grid.style.display = "none"; }
    }
    // Persist preference
    try { localStorage.setItem("files.view", view); } catch {}
  }

  function initViewToggles() {
    const group = qs(".view-toggles");
    if (!group) return;

    // initial
    const saved = (localStorage.getItem("files.view") || "list");
    setViewPressedState(group, saved);
    applyFilesView(saved);

    on(group, "click", (e) => {
      const btn = closest(e.target, "button[data-view]");
      if (!btn) return;
      const view = btn.getAttribute("data-view");
      setViewPressedState(group, view);
      applyFilesView(view);
    });
  }

  // ---------- Simple auto-close for menus on window blur ----------
  on(window, "blur", () => { closeAnyDropdown(); });

  // ---------- Init ----------
  function init() {
    initViewToggles();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else { init(); }
    // ---------- THEME TOGGLE ----------
  (function themeToggleInit() {
    const storageKey = "theme"; // 'light' | 'dark'
    const root = document.documentElement;
    const btn = document.querySelector("[data-theme-toggle]");
    if (!btn) return;

    const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");

    function apply(theme, { save = true } = {}) {
      // Set attribute so our CSS overrides take effect
      root.setAttribute("data-theme", theme);
      // Update aria state
      btn.setAttribute("aria-pressed", theme === "light" ? "true" : "false");
      // Persist
      if (save) {
        try { localStorage.setItem(storageKey, theme); } catch {}
      }
    }

    // On load: pick stored theme, else system preference
    let theme = null;
    try { theme = localStorage.getItem(storageKey); } catch {}
    if (!theme) {
      theme = prefersDark && prefersDark.matches ? "dark" : "light";
    }
    apply(theme, { save: false });

    // Toggle on click
    btn.addEventListener("click", (e) => {
      const current = root.getAttribute("data-theme") || theme;
      const next = current === "dark" ? "light" : "dark";
      apply(next);
    });

    // Optional: if you want to react to system changes ONLY when user hasn't chosen
    // a custom theme, uncomment below:
    /*
    if (prefersDark) {
      prefersDark.addEventListener("change", (ev) => {
        let stored = null;
        try { stored = localStorage.getItem(storageKey); } catch {}
        if (!stored) {
          apply(ev.matches ? "dark" : "light", { save: false });
        }
      });
    }
    */
  })();

})();

