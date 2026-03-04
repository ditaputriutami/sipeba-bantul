// ============================================================
// SIPEBA Bantul - Main JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

  // ---- Sidebar toggle (mobile) ----
  const sidebar       = document.getElementById('sidebar');
  const overlay       = document.getElementById('sidebarOverlay');
  const toggleBtn     = document.getElementById('mainSidebarToggle');
  const sidebarClose  = document.getElementById('sidebarToggleBtn');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('active');
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('active');
  }

  toggleBtn?.addEventListener('click', openSidebar);
  sidebarClose?.addEventListener('click', closeSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // ---- Auto-dismiss alerts ----
  document.querySelectorAll('.alert.auto-dismiss').forEach(function (el) {
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 4000);
  });

  // ---- Confirm delete ----
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      const msg = this.dataset.confirm || 'Apakah Anda yakin?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // ---- Numeric input formatting ----
  document.querySelectorAll('input[data-rupiah]').forEach(function (input) {
    input.addEventListener('input', function () {
      let val = this.value.replace(/\D/g, '');
      this.value = val;
    });
  });

  // ---- Tooltip init ----
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    new bootstrap.Tooltip(el);
  });

  // ---- Table search (live filter) ----
  const searchInputs = document.querySelectorAll('[data-table-search]');
  searchInputs.forEach(function (input) {
    const tableId = input.dataset.tableSearch;
    const table = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener('keyup', function () {
      const val = this.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(val) ? '' : 'none';
      });
    });
  });

});
