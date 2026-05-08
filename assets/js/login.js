function lmLogin() {
    var usuario = document.getElementById('lm_usuario').value.trim();
    var pass    = document.getElementById('lm_pass').value;
    var errBox  = document.getElementById('lm-error');
    var btn     = document.getElementById('lm-btn');
    var btnTxt  = document.getElementById('lm-btn-text');
    var loader  = document.getElementById('lm-btn-loader');

    errBox.style.display = 'none';
    errBox.textContent   = '';

    if (!usuario) { lmError('Por favor ingresa tu nombre de usuario.'); return; }
    if (!pass)    { lmError('Por favor ingresa tu contraseña.');         return; }

    btn.disabled         = true;
    btnTxt.style.display = 'none';
    loader.style.display = 'inline';

    var data = 'action=lm_do_login'
             + '&log='   + encodeURIComponent(usuario)
             + '&pwd='   + encodeURIComponent(pass)
             + '&nonce=' + encodeURIComponent(LM.nonce);

    fetch(LM.ajaxUrl, {
        method  : 'POST',
        headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
        body    : data
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            window.location.href = res.data.redirect;
        } else {
            lmError(res.data.message || 'Usuario o contraseña incorrectos.');
            lmReset();
        }
    })
    .catch(function() {
        lmError('Error de conexión. Intenta nuevamente.');
        lmReset();
    });
}

function lmError(msg) {
    var errBox = document.getElementById('lm-error');
    errBox.textContent   = msg;
    errBox.style.display = 'block';
}

function lmReset() {
    var btn    = document.getElementById('lm-btn');
    var btnTxt = document.getElementById('lm-btn-text');
    var loader = document.getElementById('lm-btn-loader');
    btn.disabled         = false;
    btnTxt.style.display = 'inline';
    loader.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    ['lm_usuario', 'lm_pass'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') lmLogin();
        });
    });
});

// ── Toggle contraseña ──────────────────────────
function lmTogglePass() {
    const input = document.getElementById('lm_pass');
    const icon  = document.getElementById('lm-eye-icon');

    if (input.type === 'password') {
        input.type = 'text';
        // Mostrar contraseña → ojo abierto
        icon.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
    } else {
        input.type = 'password';
        // Ocultar contraseña → ojo tachado
        icon.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
        `;
    }
}