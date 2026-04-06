(() => {
  const THEME_KEY = 'billing_air_theme';
  const SIDEBAR_KEY = 'billing_air_sidebar';
  const INSTALL_DISMISSED_KEY = 'billing_air_install_prompt_dismissed_at';
  const INSTALL_INSTALLED_KEY = 'billing_air_install_installed';
  const ANNOUNCEMENT_DISMISS_PREFIX = 'billing_air_announcement_dismissed_';
  const body = document.body;
  const layout = document.getElementById('appLayout');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const themeToggle = document.getElementById('themeToggle');

  const ensureLoader = () => {
    let loader = document.getElementById('appLoader');
    if (loader) return loader;

    loader = document.createElement('div');
    loader.id = 'appLoader';
    loader.className = 'app-loader hide';
    loader.innerHTML = `
      <div class="spinner-border text-primary" role="status"></div>
      <div class="app-loader-message">Memuat halaman...</div>
      <div class="app-loader-subtitle">Tunggu sebentar ya</div>
    `;
    document.body.appendChild(loader);
    return loader;
  };

  const setLoaderMessage = (title = 'Memuat halaman...', subtitle = 'Tunggu sebentar ya') => {
    const loader = ensureLoader();
    const titleEl = loader.querySelector('.app-loader-message');
    const subtitleEl = loader.querySelector('.app-loader-subtitle');
    if (titleEl) titleEl.textContent = title;
    if (subtitleEl) subtitleEl.textContent = subtitle;
  };

  const showLoader = (title, subtitle) => {
    const loader = ensureLoader();
    setLoaderMessage(title, subtitle);
    loader.classList.remove('hide');
  };

  const hideLoader = () => {
    const loader = ensureLoader();
    loader.classList.add('hide');
  };

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

      const headers = Array.from(table.querySelectorAll('thead th')).map((th) => (th.textContent || '').trim());
      if (table.dataset.mobileStack !== 'off') {
        table.classList.add('table-card-mode');
      }

      Array.from(tbody.querySelectorAll('tr')).forEach((row) => {
        const cells = Array.from(row.querySelectorAll('td'));
        cells.forEach((cell, index) => {
          if (cell.hasAttribute('colspan')) return;
          if (!cell.dataset.label && headers[index]) {
            cell.dataset.label = headers[index];
          }
        });
      });

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
    const loader = ensureLoader();
    window.addEventListener('load', () => {
      hideLoader();
    });

    document.addEventListener('click', (event) => {
      const link = event.target.closest('a[href]');
      if (!link) return;
      if (link.target === '_blank' || link.hasAttribute('download')) return;
      const href = link.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

      try {
        const url = new URL(href, window.location.href);
        if (url.origin !== window.location.origin) return;
      } catch (error) {
        return;
      }

      const label = (link.textContent || '').trim();
      showLoader(label ? `Membuka ${label}...` : 'Membuka halaman...', 'Menyiapkan tampilan aplikasi');
    });

    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      const action = (form.getAttribute('action') || '').toLowerCase();
      const isLogin = action.includes('index.php') || form.querySelector('input[name="username"]') && form.querySelector('input[name="password"]');
      showLoader(
        isLogin ? 'Memproses login...' : 'Menyimpan data...',
        isLogin ? 'Sedang masuk ke aplikasi' : 'Perubahan sedang diproses'
      );
    });
  };

  const initInstallPrompt = () => {
    const backdrop = document.getElementById('installPromptBackdrop');
    const closeBtn = document.getElementById('installPromptClose');
    const laterBtn = document.getElementById('installPromptLaterBtn');
    const installBtn = document.getElementById('installPromptInstallBtn');
    const noteBox = document.getElementById('installPromptNote');
    const stepsBox = document.getElementById('installPromptSteps');
    const manualGuideButtons = document.querySelectorAll('[data-open-install-guide]');

    if (!backdrop || !installBtn || !laterBtn || !closeBtn) return;

    let deferredPrompt = null;

    const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isMobileLike = () => window.innerWidth <= 992 || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    const wasDismissedRecently = () => {
      const raw = Number(localStorage.getItem(INSTALL_DISMISSED_KEY) || 0);
      return raw > 0 && (Date.now() - raw) < (12 * 60 * 60 * 1000);
    };

    const showPrompt = (mode = 'manual', options = {}) => {
      const force = options.force === true;
      if (!force) {
        if (isStandalone() || localStorage.getItem(INSTALL_INSTALLED_KEY) === '1' || !isMobileLike()) return;
        if (wasDismissedRecently()) return;
      }

      installBtn.hidden = mode !== 'auto';
      noteBox.hidden = mode === 'auto';
      stepsBox.hidden = mode === 'auto';
      backdrop.hidden = false;
      document.body.style.overflow = 'hidden';
    };

    const hidePrompt = (remember = true) => {
      backdrop.hidden = true;
      document.body.style.overflow = '';
      if (remember) {
        localStorage.setItem(INSTALL_DISMISSED_KEY, String(Date.now()));
      }
    };

    closeBtn.addEventListener('click', () => hidePrompt(true));
    laterBtn.addEventListener('click', () => hidePrompt(true));
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop) {
        hidePrompt(true);
      }
    });

    installBtn.addEventListener('click', async () => {
      if (!deferredPrompt) {
        showPrompt('manual', { force: true });
        return;
      }

      deferredPrompt.prompt();
      try {
        await deferredPrompt.userChoice;
      } catch (error) {
        console.warn('Install prompt error:', error);
      }
      deferredPrompt = null;
      hidePrompt(true);
    });

    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredPrompt = event;
      setTimeout(() => showPrompt('auto'), 1400);
    });

    window.addEventListener('appinstalled', () => {
      localStorage.setItem(INSTALL_INSTALLED_KEY, '1');
      hidePrompt(false);
    });

    manualGuideButtons.forEach((button) => {
      button.addEventListener('click', () => {
        showPrompt('manual', { force: true });
      });
    });

    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch((error) => {
          console.warn('SW register failed:', error);
        });
      });
    }

    setTimeout(() => showPrompt('manual'), 2400);
  };

  const initAnnouncementPopup = () => {
    const backdrop = document.getElementById('announcementPopupBackdrop');
    const closeBtn = document.getElementById('announcementPopupClose');
    const okBtn = document.getElementById('announcementPopupOk');
    const card = backdrop?.querySelector('[data-announcement-id]');
    if (!backdrop || !closeBtn || !okBtn || !card) return;

    const announcementId = card.dataset.announcementId || '';
    const dismissKey = ANNOUNCEMENT_DISMISS_PREFIX + announcementId;
    if (announcementId && localStorage.getItem(dismissKey) === '1') {
      return;
    }

    const hide = () => {
      backdrop.hidden = true;
      document.body.style.overflow = '';
      if (announcementId) {
        localStorage.setItem(dismissKey, '1');
      }
    };

    setTimeout(() => {
      backdrop.hidden = false;
      document.body.style.overflow = 'hidden';
    }, 900);

    closeBtn.addEventListener('click', hide);
    okBtn.addEventListener('click', hide);
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop) hide();
    });
  };

  initTheme();
  initSidebarMode();
  initDataTables();
  initLoader();
  initInstallPrompt();
  initAnnouncementPopup();
})();
