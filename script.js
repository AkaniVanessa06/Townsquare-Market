// script.js - TownSquare Market Client-Side Functionality

document.addEventListener('DOMContentLoaded', function () {

    // -------------------------------------------------------
    // Auto-dismiss alerts after 5 seconds
    // -------------------------------------------------------
    const alerts = document.querySelectorAll('.alert.auto-dismiss');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.6s ease';
            alert.style.opacity   = '0';
            setTimeout(function () { alert.remove(); }, 600);
        }, 5000);
    });

    // -------------------------------------------------------
    // Confirm before destructive actions
    // -------------------------------------------------------
    document.querySelectorAll('.delete-confirm').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // -------------------------------------------------------
    // Quantity input guard – cap at max stock
    // -------------------------------------------------------
    document.querySelectorAll('.quantity-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var max = parseInt(this.getAttribute('max'), 10);
            var val = parseInt(this.value, 10);
            if (val > max) {
                this.value = max;
                showToast('Only ' + max + ' item(s) available in stock.');
            }
            if (val < 1) {
                this.value = 1;
            }
        });
    });

    // -------------------------------------------------------
    // Client-side password match validation for register form
    // -------------------------------------------------------
    var registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            var pwd     = document.getElementById('password');
            var confirm = document.getElementById('confirm-password');
            var err     = document.getElementById('password-error');

            if (pwd && confirm && pwd.value !== confirm.value) {
                e.preventDefault();
                if (err) {
                    err.textContent = 'Passwords do not match.';
                    err.style.display = 'block';
                }
                pwd.focus();
            }
        });
    }

    // -------------------------------------------------------
    // Back to top button
    // -------------------------------------------------------
    var backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        window.addEventListener('scroll', function () {
            backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
        });
        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // -------------------------------------------------------
    // Simple toast notification helper
    // -------------------------------------------------------
    function showToast(message) {
        var toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = [
            'position:fixed', 'bottom:80px', 'left:50%',
            'transform:translateX(-50%)',
            'background:#2d3748', 'color:#fff',
            'padding:10px 20px', 'border-radius:8px',
            'font-size:14px', 'z-index:9999',
            'box-shadow:0 4px 12px rgba(0,0,0,0.2)',
            'transition:opacity 0.4s ease'
        ].join(';');
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            setTimeout(function () { toast.remove(); }, 400);
        }, 3000);
    }

});