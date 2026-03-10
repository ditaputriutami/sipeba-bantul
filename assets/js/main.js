// ============================================================
// SIPEBA Bantul - Main JavaScript
// ============================================================

document.addEventListener("DOMContentLoaded", function () {
  // ---- Sidebar elements ----
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebarOverlay");
  const toggleBtn = document.getElementById("mainSidebarToggle");
  const sidebarClose = document.getElementById("sidebarToggleBtn");

  // Debug: Log to ensure elements are found
  console.log("Sidebar elements:", {
    sidebar: !!sidebar,
    overlay: !!overlay,
    toggleBtn: !!toggleBtn,
    sidebarClose: !!sidebarClose,
  });

  // ---- Sidebar collapse/expand (desktop & tablet) ----
  function toggleSidebarCollapse(e) {
    // Prevent default anchor/button behavior that might cause scroll
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }

    const isLargeScreen = window.innerWidth >= 1200;
    console.log("Toggle clicked, isLargeScreen:", isLargeScreen);

    if (isLargeScreen) {
      // Save current scroll position before toggle
      const currentScrollTop = sidebar?.scrollTop || 0;
      console.log("Saving scroll position:", currentScrollTop);

      // Desktop: toggle collapsed state
      sidebar?.classList.toggle("collapsed");

      // Restore scroll position immediately and after transition
      if (sidebar) {
        sidebar.scrollTop = currentScrollTop;
      }
      
      // Also restore after animation completes (300ms transition)
      requestAnimationFrame(() => {
        if (sidebar) {
          sidebar.scrollTop = currentScrollTop;
        }
      });
      
      setTimeout(() => {
        if (sidebar) {
          sidebar.scrollTop = currentScrollTop;
          console.log("Restored scroll position:", currentScrollTop);
        }
      }, 350);

      // Save state to localStorage
      const isCollapsed = sidebar?.classList.contains("collapsed");
      localStorage.setItem("sidebarCollapsed", isCollapsed ? "true" : "false");
      console.log("Sidebar collapsed:", isCollapsed);
    } else {
      // Mobile: toggle open/close
      if (sidebar?.classList.contains("open")) {
        closeSidebar();
      } else {
        openSidebar();
      }
    }
  }

  // ---- Restore sidebar state on page load ----
  function restoreSidebarState() {
    const isLargeScreen = window.innerWidth >= 1200;
    const savedState = localStorage.getItem("sidebarCollapsed");

    if (isLargeScreen && savedState === "true") {
      sidebar?.classList.add("collapsed");
      console.log("Restored collapsed state");
    }
  }

  // ---- Mobile sidebar functions ----
  function openSidebar() {
    sidebar?.classList.add("open");
    overlay?.classList.add("active");
    console.log("Sidebar opened");
  }
  function closeSidebar() {
    sidebar?.classList.remove("open");
    overlay?.classList.remove("active");
    console.log("Sidebar closed");
  }

  // ---- Event listeners ----
  if (toggleBtn) {
    toggleBtn.addEventListener("click", function(e) {
      toggleSidebarCollapse(e);
    });
    console.log("Main toggle button listener attached");
  } else {
    console.warn("mainSidebarToggle button not found!");
  }

  if (sidebarClose) {
    sidebarClose.addEventListener("click", function(e) {
      toggleSidebarCollapse(e);
    });
    console.log("Sidebar close button listener attached");
  }

  if (overlay) {
    overlay.addEventListener("click", closeSidebar);
  }

  // ---- Restore state on load ----
  restoreSidebarState();

  // ---- Handle window resize ----
  window.addEventListener("resize", function () {
    const isLargeScreen = window.innerWidth >= 1200;

    if (isLargeScreen) {
      // Desktop mode: close mobile sidebar and restore collapsed state
      closeSidebar();
      restoreSidebarState();
    } else {
      // Mobile mode: remove collapsed class
      sidebar?.classList.remove("collapsed");
    }
  });

  // ---- Auto-dismiss alerts ----
  document.querySelectorAll(".alert.auto-dismiss").forEach(function (el) {
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 4000);
  });

  // ---- Confirm delete ----
  document.querySelectorAll("[data-confirm]").forEach(function (el) {
    el.addEventListener("click", function (e) {
      const msg = this.dataset.confirm || "Apakah Anda yakin?";
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // ---- Numeric input formatting ----
  document.querySelectorAll("input[data-rupiah]").forEach(function (input) {
    input.addEventListener("input", function () {
      let val = this.value.replace(/\D/g, "");
      this.value = val;
    });
  });

  // ---- Tooltip init ----
  document
    .querySelectorAll('[data-bs-toggle="tooltip"]')
    .forEach(function (el) {
      new bootstrap.Tooltip(el);
    });

  // ---- Table search (live filter) ----
  const searchInputs = document.querySelectorAll("[data-table-search]");
  searchInputs.forEach(function (input) {
    const tableId = input.dataset.tableSearch;
    const table = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener("keyup", function () {
      const val = this.value.toLowerCase();
      table.querySelectorAll("tbody tr").forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(val) ? "" : "none";
      });
    });
  });
});
