    </main>

    <footer class="app-footer">
      Copyright DENTA TIRTA • Powered By
      <a href="https://remek8787.github.io/portofolio/" target="_blank" rel="noopener">Ananta Satriya</a>
    </footer>
  </div>
</div>

<?php if (($currentPage ?? '') === 'dashboard.php'): ?>
  <div class="install-popup-backdrop" id="installPromptBackdrop" hidden>
    <div class="install-popup-card" role="dialog" aria-modal="true" aria-labelledby="installPromptTitle">
      <div class="install-popup-head">
        <div class="install-popup-brand">
          <div class="install-popup-icon-wrap">
            <img src="assets/app-icon-192.png" alt="DENTA TIRTA" class="install-popup-icon">
          </div>
          <div>
            <div class="install-popup-title" id="installPromptTitle">Install Aplikasi</div>
            <div class="install-popup-subtitle">DENTA TIRTA</div>
          </div>
        </div>
        <button type="button" class="install-popup-close" id="installPromptClose" aria-label="Tutup popup install">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="install-popup-body">
        <p class="install-popup-text">
          Install aplikasi ini di layar utama HP Anda untuk akses lebih cepat,
          tampilan lebih full screen, dan terasa seperti aplikasi beneran.
        </p>

        <div class="install-popup-note" id="installPromptNote">
          <b>Note:</b> kalau tombol install otomatis tidak muncul, tenang — Anda masih bisa install manual dari menu browser.
        </div>

        <div class="install-steps" id="installPromptSteps">
          <div class="install-step">
            <div class="install-step-icon"><i class="bi bi-three-dots-vertical"></i></div>
            <div>
              <div class="install-step-title">1. Buka menu browser</div>
              <div class="install-step-text">Tekan <b>Menu / Titik Tiga</b> di pojok kanan atas browser Anda.</div>
            </div>
          </div>
          <div class="install-step">
            <div class="install-step-icon"><i class="bi bi-phone"></i></div>
            <div>
              <div class="install-step-title">2. Pilih Install App</div>
              <div class="install-step-text">Lalu pilih <b>Install App</b>, <b>Tambahkan ke Layar Utama</b>, atau <b>Add to Home Screen</b>.</div>
            </div>
          </div>
        </div>

        <div class="install-popup-actions">
          <button type="button" class="btn btn-primary install-main-btn" id="installPromptInstallBtn">Install Sekarang</button>
          <button type="button" class="btn btn-outline-secondary" id="installPromptLaterBtn">Nanti Saja</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
