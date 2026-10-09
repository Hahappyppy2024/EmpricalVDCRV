/* P16 — small UX enhancements, no frameworks. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.flash').forEach(function (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity 400ms ease';
            flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 500);
        }, 5000);
    });
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type=submit]').forEach(function (btn) {
                btn.disabled = true;
                btn.dataset.origText = btn.textContent;
                btn.textContent = 'Working...';
            });
        });
    });
});