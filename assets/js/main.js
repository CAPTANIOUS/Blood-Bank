/*
 * assets/js/main.js
 * Our own small JavaScript. It does three jobs:
 *   1. Forms with class "needs-validation" show red/green hints before sending.
 *   2. The "confirm password" box must match the "password" box.
 *   3. Message boxes (alerts) marked data-autohide disappear after 6 seconds.
 *   4. Forms with data-confirm="question" ask "OK / Cancel" before they are sent.
 *   5. Request form: the date/time boxes are shown only for a Normal request.
 *   6. File boxes with data-max-mb / data-max-files check the files before upload.
 *   7. Buttons with data-confirm-click="question" ask "OK / Cancel" when clicked.
 *   8. Registration forms show a password strength bar while the person types.
 */
document.addEventListener('DOMContentLoaded', function () {

    // ---- 1. Check the form in the browser before it is sent ----
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();      // stop sending
                event.stopPropagation();
            }
            form.classList.add('was-validated');   // Bootstrap now shows the hints
        });
    });

    // ---- 2. Password and confirm password must be the same ----
    document.querySelectorAll('form').forEach(function (form) {
        var pass    = form.querySelector('input[name="password"]');
        var confirm = form.querySelector('input[name="confirm_password"]');
        if (!pass || !confirm) { return; }

        function check() {
            if (confirm.value === pass.value) {
                confirm.setCustomValidity('');                       // OK
            } else {
                confirm.setCustomValidity('Passwords do not match'); // not OK
            }
        }
        pass.addEventListener('input', check);
        confirm.addEventListener('input', check);
    });

    // ---- 4. Ask before a risky action (for example "Deactivate") ----
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // ---- 4b. Same, for ONE button inside a form that has several buttons ----
    document.querySelectorAll('button[data-confirm-click]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            if (!window.confirm(btn.getAttribute('data-confirm-click'))) {
                event.preventDefault();
            }
        });
    });

    // ---- 5. Request form: date and time only for a Normal request ----
    var whenBox = document.getElementById('normalWhen');
    if (whenBox) {
        var syncWhen = function () {
            var normal = document.querySelector('input[name="urgency"]:checked').value === 'Normal';
            whenBox.hidden = !normal;
            whenBox.querySelectorAll('input').forEach(function (box) {
                box.disabled = !normal;        // a disabled box is not checked and not sent
                box.required = normal;
            });
        };
        document.querySelectorAll('input[name="urgency"]').forEach(function (radio) {
            radio.addEventListener('change', syncWhen);
        });
    }

    // ---- 6. Check the chosen files before they are uploaded ----
    document.querySelectorAll('input[type="file"][data-max-mb]').forEach(function (box) {
        var feedback = box.parentElement.querySelector('.invalid-feedback');
        var normalText = feedback ? feedback.getAttribute('data-default') : '';
        var okExt = ['pdf', 'jpg', 'jpeg', 'png'];

        box.addEventListener('change', function () {
            var message = '';
            var maxBytes = parseInt(box.getAttribute('data-max-mb'), 10) * 1024 * 1024;
            var maxFiles = parseInt(box.getAttribute('data-max-files'), 10) || 3;

            if (box.files.length > maxFiles) {
                message = 'Choose at most ' + maxFiles + ' file' + (maxFiles === 1 ? '' : 's') + '.';
            }
            Array.prototype.forEach.call(box.files, function (file) {
                var ext = file.name.split('.').pop().toLowerCase();
                if (!message && okExt.indexOf(ext) === -1) {
                    message = '"' + file.name + '" is not allowed. Use PDF, JPG, JPEG or PNG.';
                } else if (!message && file.size > maxBytes) {
                    message = '"' + file.name + '" is too large. The limit is ' + box.getAttribute('data-max-mb') + ' MB.';
                }
            });
            box.setCustomValidity(message);
            if (feedback) { feedback.textContent = message || normalText; }
        });
    });

    // ---- 8. Password strength bar (only on forms that ask to confirm the password) ----
    document.querySelectorAll('form').forEach(function (form) {
        var pass = form.querySelector('input[name="password"]');
        if (!pass || !form.querySelector('input[name="confirm_password"]')) { return; }

        var wrap = document.createElement('div');
        wrap.className = 'mt-2';
        wrap.innerHTML = '<div class="progress" style="height:6px"><div class="progress-bar" role="progressbar" style="width:0%"></div></div>' +
                         '<div class="form-text" aria-live="polite"></div>';
        pass.insertAdjacentElement('afterend', wrap);
        var bar  = wrap.querySelector('.progress-bar');
        var text = wrap.querySelector('.form-text');

        pass.addEventListener('input', function () {
            var v = pass.value, score = 0;
            if (v.length >= 8)               { score++; }
            if (v.length >= 12)              { score++; }
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) { score++; }
            if (/\d/.test(v))                { score++; }
            if (/[^A-Za-z0-9]/.test(v))      { score++; }
            if (v.length === 0)              { score = -1; }

            var levels = [['Too weak', 'bg-danger', 20], ['Weak', 'bg-danger', 40], ['Fair', 'bg-warning', 60],
                          ['Good', 'bg-info', 80], ['Strong', 'bg-success', 100]];
            if (score < 0) { bar.style.width = '0%'; text.textContent = ''; return; }
            var lv = levels[Math.min(score, 4)];
            bar.className = 'progress-bar ' + lv[1];
            bar.style.width = lv[2] + '%';
            text.textContent = 'Password strength: ' + lv[0] + (score < 3 ? ' - try adding capital letters, numbers and symbols.' : '.');
        });
    });

    // ---- 3. Hide alert boxes by themselves ----
    document.querySelectorAll('.alert[data-autohide]').forEach(function (box) {
        setTimeout(function () { box.remove(); }, 6000);
    });
});
