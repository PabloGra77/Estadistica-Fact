/* ============================================================
   Tablero PPL — app.js
   ============================================================ */

(function () {
    'use strict';

    // ── Sidebar mobile toggle ────────────────────────────────
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 768 &&
                sidebar.classList.contains('show') &&
                !sidebar.contains(e.target) &&
                e.target !== toggleBtn) {
                sidebar.classList.remove('show');
            }
        });
    }

    // ── Mark active nav link ─────────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('#sidebar .nav-link').forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && href !== '/' && currentPath.startsWith(href)) {
            link.classList.add('active');
        } else if (href === '/' && currentPath === '/') {
            link.classList.add('active');
        }
    });

    // ── Auto-dismiss alerts after 5 s ───────────────────────
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // ── Confirm destructive forms ────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm || '¿Está seguro?')) {
                e.preventDefault();
            }
        });
    });

    // ── Password visibility toggle ───────────────────────────
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.dataset.togglePassword;
            const input = document.getElementById(targetId);
            if (!input) return;
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
            }
        });
    });

    // ── File input label update ──────────────────────────────
    document.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            const label = document.querySelector('label[for="' + input.id + '"]');
            if (label && input.files.length > 0) {
                label.title = input.files[0].name;
            }
        });
    });
})();
