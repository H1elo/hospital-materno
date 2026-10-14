/**
 * Hospital Materno Rosa - interacciones del cliente.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initFlash();
        initSidebar();
        initPasswordToggles();
        initConfirm();
        initModals();
        initPrint();
        initAutoSubmit();
    });

    /* ------------------------------ Mensajes flash ----------------------------- */
    function initFlash() {
        document.querySelectorAll('[data-flash]').forEach(function (element) {
            element.querySelector('[data-flash-close]')?.addEventListener('click', function () {
                element.remove();
            });

            setTimeout(function () {
                element.style.transition = 'opacity .4s ease, transform .4s ease';
                element.style.opacity = '0';
                element.style.transform = 'translateX(1rem)';
                setTimeout(function () { element.remove(); }, 400);
            }, 6000);
        });
    }

    /* --------------------------------- Sidebar -------------------------------- */
    function initSidebar() {
        var sidebar = document.querySelector('[data-sidebar]');
        if (!sidebar) return;

        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                sidebar.classList.remove('hidden');
            });
        });

        sidebar.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                sidebar.classList.add('hidden');
            });
        });
    }

    /* --------------------------- Mostrar/ocultar clave ------------------------ */
    function initPasswordToggles() {
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.getAttribute('data-toggle-password'));
                if (!target) return;

                var isPassword = target.getAttribute('type') === 'password';
                target.setAttribute('type', isPassword ? 'text' : 'password');

                button.querySelector('[data-eye]')?.classList.toggle('hidden', !isPassword);
                button.querySelector('[data-eye-off]')?.classList.toggle('hidden', isPassword);
            });
        });
    }

    /* --------------------------- Confirmar acciones --------------------------- */
    function initConfirm() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });
    }

    /* --------------------------------- Modales -------------------------------- */
    function initModals() {
        document.querySelectorAll('[data-modal-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById(button.getAttribute('data-modal-open'))?.classList.remove('hidden');
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('[data-modal]')?.classList.add('hidden');
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-modal]').forEach(function (modal) {
                    modal.classList.add('hidden');
                });
            }
        });
    }

    /* -------------------------------- Impresion ------------------------------- */
    function initPrint() {
        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.print();
            });
        });
    }

    /* ------------------------- Envio automatico de filtros -------------------- */
    function initAutoSubmit() {
        document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
            field.addEventListener('change', function () {
                field.closest('form')?.submit();
            });
        });
    }
})();
