/* Iyapayao Booster — Frontend JavaScript */

(function () {
    'use strict';

    // ----------------------------------------------------------------
    // Sidebar toggle (mobile)
    // ----------------------------------------------------------------
    const hamburger = document.getElementById('hamburgerBtn');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar && sidebar.classList.add('open');
        overlay && overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar && sidebar.classList.remove('open');
        overlay && overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    hamburger && hamburger.addEventListener('click', openSidebar);
    overlay   && overlay.addEventListener('click', closeSidebar);

    // ----------------------------------------------------------------
    // New Order — dynamic service loader
    // ----------------------------------------------------------------
    const categorySelect  = document.getElementById('category_select');
    const serviceSelect   = document.getElementById('service_select');
    const serviceInfo     = document.getElementById('service_info');
    const quantityInput   = document.getElementById('quantity');
    const dripfeedSection = document.getElementById('dripfeed_section');

    // Services data injected by PHP as window.servicesData
    function populateServices(category) {
        if (!serviceSelect) return;
        serviceSelect.innerHTML = '<option value="">— Select Service —</option>';
        if (!category || !window.servicesData) return;

        const filtered = window.servicesData.filter(s => s.category === category);
        filtered.forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            serviceSelect.appendChild(opt);
        });
    }

    function showServiceInfo(serviceId) {
        if (!serviceInfo || !window.servicesData) return;
        const s = window.servicesData.find(sv => String(sv.id) === String(serviceId));
        if (!s) {
            serviceInfo.classList.remove('visible');
            return;
        }
        // Populate detail fields
        const fill = function (id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        fill('info_min',   s.min);
        fill('info_max',   s.max);
        fill('info_rate',  '$' + parseFloat(s.price).toFixed(5) + ' / 1000');
        fill('info_desc',  s.description || '—');

        // Update quantity constraints
        if (quantityInput) {
            quantityInput.min = s.min;
            quantityInput.max = s.max;
            quantityInput.placeholder = 'Min: ' + s.min + ' / Max: ' + s.max;
        }

        // Show/hide drip feed
        if (dripfeedSection) {
            dripfeedSection.style.display = s.dripfeed ? 'block' : 'none';
        }

        serviceInfo.classList.add('visible');
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            populateServices(this.value);
            serviceInfo && serviceInfo.classList.remove('visible');
        });
    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', function () {
            showServiceInfo(this.value);
        });
    }

    // ----------------------------------------------------------------
    // Auto-refresh order status (every 60 s on orders page)
    // ----------------------------------------------------------------
    if (document.querySelector('[data-page="orders"]')) {
        setTimeout(function () {
            window.location.reload();
        }, 60000);
    }

    // ----------------------------------------------------------------
    // Modal helpers
    // ----------------------------------------------------------------
    function openModal(id) {
        const m = document.getElementById(id);
        m && m.classList.add('open');
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        m && m.classList.remove('open');
    }

    // Attach open triggers
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.dataset.modalOpen);
            // Pre-fill data- attributes into modal fields
            Object.keys(btn.dataset).forEach(function (key) {
                if (key === 'modalOpen') return;
                const target = document.getElementById('modal_' + key);
                if (target) target.value = btn.dataset[key];
            });
        });
    });

    // Attach close triggers
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(btn.dataset.modalClose);
        });
    });

    // Close modal on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) {
                backdrop.classList.remove('open');
            }
        });
    });

    // ----------------------------------------------------------------
    // Confirm-delete protection
    // ----------------------------------------------------------------
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ----------------------------------------------------------------
    // Flash message auto-dismiss (after 5 s)
    // ----------------------------------------------------------------
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity    = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 5000);
    });

})();
