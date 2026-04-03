(() => {
  const THEME_KEY = 'billing_air_theme';
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

  sidebarToggle?.addEventListener('click', () => {
    layout?.classList.toggle('sidebar-open');
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
      const pageSize = Number(table.dataset.pageSize || 10);

      const tools = document.createElement('div');
      tools.className = 'js-table-tools';
      tools.innerHTML = `
        <input type="search" class="form-control form-control-sm js-table-search" placeholder="Cari data...">
        <div class="js-table-pager">
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

  initTheme();
  initDataTables();
})();
