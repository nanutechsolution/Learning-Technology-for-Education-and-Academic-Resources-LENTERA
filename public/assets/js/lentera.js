(function () {
    'use strict';

    var body = document.body;

    // ---------- Sidebar (layar kecil) ----------
    var toggleButton = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');

    function closeSidebar() {
        body.classList.remove('sidebar-open');
    }

    if (toggleButton) {
        toggleButton.addEventListener('click', function () {
            body.classList.toggle('sidebar-open');
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    // ---------- Konfirmasi form: <form data-confirm="Pesan konfirmasi"> ----------
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form && form.getAttribute ? form.getAttribute('data-confirm') : null;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // ---------- Tampilkan/sembunyikan password: <button data-toggle-password="#id"> ----------
    var toggles = document.querySelectorAll('[data-toggle-password]');

    Array.prototype.forEach.call(toggles, function (button) {
        button.addEventListener('click', function () {
            var input = document.querySelector(button.getAttribute('data-toggle-password'));

            if (!input) {
                return;
            }

            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';

            var icon = button.querySelector('i');

            if (icon) {
                icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            }

            button.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    });
})();