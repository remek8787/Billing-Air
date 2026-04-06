(() => {
  const THEME_KEY = 'billing_air_theme';
  const SIDEBAR_KEY = 'billing_air_sidebar';
  const body = document.body;
  const layout = document.getElementById('appLayout');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const themeToggle = document.getElementById('themeToggle');

  const setTheme = (theme) => {
    body.setAttribute('data-theme', theme);
    localStorage.setItem(THEME_KEY, theme);
    if (themeToggle) {
      themeToggle.textContent = theme === 'dark' ? '☀️ Light' : '🌙 Dark';
    }
  };

  const initTheme = () => {
    const saved = localStorage.getItem(THEME_KEY);
    setTheme(saved === 'dark' ? 'dark' : 'light');
  };

  const setSidebarMode = (mode) => {
    if (!layout) return;
    layout.classList.remove('sidebar-collapsed');
    if (mode === 'collapsed') {
      layout.classList.add('sidebar-collapsed');
    }
    localStorage.setItem(SIDEBAR_KEY, mode);
  };

  const initSidebarMode = () => {
    const saved = localStorage.getItem(SIDEBAR_KEY);
    if (window.innerWidth > 992 && saved === 'collapsed') {
      setSidebarMode('collapsed');
    }
  };

  sidebarToggle?.addEventListener('click', () => {
    if (!layout) return;

    if (window.innerWidth <= 992) {
      layout.classList.toggle('sidebar-open');
      return;
    }

    const collapsed = layout.classList.contains('sidebar-collapsed');
    setSidebarMode(collapsed ? 'expanded' : 'collapsed');
  });

  document.addEventListener('click', (event) => {
    if (!layout || window.innerWidth > 992) return;
    const sidebar = document.getElementById('appSidebar');
    if (!layout.classList.contains('sidebar-open')) return;
    if (sidebar?.contains(event.target) || sidebarToggle?.contains(event.target)) return;
    layout.classList.remove('sidebar-open');
  });

  themeToggle?.addEventListener('click', () => {
    const current = body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    setTheme(current === 'dark' ? 'light' : 'dark');
  });

  const initDataTables = () => {
    const tables = document.querySelectorAll('table.js-data-table');

    tables.forEach((table) => {
      const tbody = table.querySelector('tbody');
      if (!tbody) return;

      const rows = Array.from(tbody.querySelectorAll('tr')).filter((tr) => !tr.querySelector('td[colspan]'));
      if (rows.length === 0) return;

      let filteredRows = [...rows];
      let page = 1;
      let pageSize = Number(table.dataset.pageSize || 10);

      const tools = document.createElement('div');
      tools.className = 'js-table-tools';
      tools.innerHTML = `
        <div class="js-table-search-wrap">
          <input type="search" class="form-control form-control-sm js-table-search" placeholder="Cari data...">
        </div>
        <div class="js-table-pager">
          <label class="js-page-size-wrap">
            <span>Show</span>
            <select class="form-select form-select-sm js-page-size">
              <option value="5">5</option>
              <option value="10" selected>10</option>
              <option value="15">15</option>
              <option value="25">25</option>
            </select>
          </label>
          <button type="button" class="btn btn-sm btn-outline-secondary js-prev">Prev</button>
          <span class="js-table-info"></span>
          <button type="button" class="btn btn-sm btn-outline-secondary js-next">Next</button>
        </div>
      `;

      table.parentElement?.insertBefore(tools, table);

      const searchInput = tools.querySelector('.js-table-search');
      const info = tools.querySelector('.js-table-info');
      const prevBtn = tools.querySelector('.js-prev');
      const nextBtn = tools.querySelector('.js-next');
      const pageSizeInput = tools.querySelector('.js-page-size');

      if (pageSizeInput) {
        pageSizeInput.value = String(pageSize);
      }

      const draw = () => {
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (page > totalPages) page = totalPages;

        const start = (page - 1) * pageSize;
        const end = start + pageSize;

        rows.forEach((row) => {
          row.style.display = 'none';
        });

        filteredRows.slice(start, end).forEach((row) => {
          row.style.display = '';
        });

        if (info) {
          info.textContent = `${total === 0 ? 0 : start + 1}-${Math.min(end, total)} / ${total}`;
        }

        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= totalPages;
      };

      searchInput?.addEventListener('input', () => {
        const q = (searchInput.value || '').toLowerCase().trim();
        filteredRows = rows.filter((row) => row.innerText.toLowerCase().includes(q));
        page = 1;
        draw();
      });

      pageSizeInput?.addEventListener('change', () => {
        pageSize = Math.max(1, Number(pageSizeInput.value || 10));
        page = 1;
        draw();
      });

      prevBtn?.addEventListener('click', () => {
        page = Math.max(1, page - 1);
        draw();
      });

      nextBtn?.addEventListener('click', () => {
        page += 1;
        draw();
      });

      draw();
    });
  };

  const initLoader = () => {
    const loader = document.getElementById('appLoader');
    if (!loader) return;
    window.addEventListener('load', () => {
      loader.classList.add('hide');
      setTimeout(() => loader.remove(), 300);
    });
  };

  initTheme();
  initSidebarMode();
  initDataTables();
  initLoader();
})();
