document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('peModal').addEventListener('click', function(e) {
        if (e.target === this) peModalClose();
    });
});

function peF(estado, btn) {
    document.querySelectorAll('#peTabla tbody tr').forEach(function(f) {
        f.style.display = (estado === 'todos' || f.dataset.estado === estado) ? '' : 'none';
    });
    document.querySelectorAll('.pe-filtro-btn').forEach(function(b) { b.classList.remove('activo'); });
    btn.classList.add('activo');
}

function peAZ(btn) {
    var tbody = document.querySelector('#peTabla tbody');
    Array.from(tbody.rows)
        .sort(function(a, b) {
            return (a.dataset.nombre || '').localeCompare(b.dataset.nombre || '');
        })
        .forEach(function(r) { tbody.appendChild(r); });
    document.querySelectorAll('.pe-filtro-btn').forEach(function(b) { b.classList.remove('activo'); });
    btn.classList.add('activo');
}

var peUsuarioActual = null;

function peModalOpen() {
    var modal = document.getElementById('peModal');
    modal.style.display = 'flex';
    document.getElementById('pe_msg').style.display = 'none';
    document.getElementById('pe_msg').className = 'pe-msg';
}

function peModalClose() {
    document.getElementById('peModal').style.display = 'none';
    peUsuarioActual = null;
}

function peAgregar() {
    peUsuarioActual = null;
    document.getElementById('peModalTitulo').textContent = 'Registro de colaborador';
    document.getElementById('pe_pass_hint').style.display = 'none';
    document.getElementById('pe_nombre').value   = '';
    document.getElementById('pe_rut').value      = '';
    document.getElementById('pe_email').value    = '';
    document.getElementById('pe_login').value    = '';
    document.getElementById('pe_password').value = '';
    document.getElementById('pe_cargo').value    = '';
    document.getElementById('pe_estado').value   = 'activo';
    document.getElementById('pe_login').removeAttribute('readonly');
    peModalOpen();
}

function peMod(id) {
    var fila = document.querySelector('#peTabla tbody tr[data-id="' + id + '"]');
    if (!fila) return;

    peUsuarioActual = id;
    document.getElementById('peModalTitulo').textContent = 'Modificar trabajador';
    document.getElementById('pe_pass_hint').style.display = 'inline';
    document.getElementById('pe_nombre').value   = fila.dataset.nombre    || '';
    document.getElementById('pe_rut').value      = fila.dataset.rut       || '';
    document.getElementById('pe_email').value    = fila.dataset.email     || '';
    document.getElementById('pe_login').value    = fila.dataset.login     || '';
    document.getElementById('pe_password').value = '';
    document.getElementById('pe_cargo').value    = fila.dataset.cargo     || '';
    document.getElementById('pe_estado').value   = fila.dataset.estadoVal || 'activo';
    document.getElementById('pe_login').setAttribute('readonly', 'readonly');
    peModalOpen();
}

function peGuardar() {
    var nombre   = document.getElementById('pe_nombre').value.trim();
    var rut      = document.getElementById('pe_rut').value.trim();
    var email    = document.getElementById('pe_email').value.trim();
    var login    = document.getElementById('pe_login').value.trim();
    var password = document.getElementById('pe_password').value;
    var cargo    = document.getElementById('pe_cargo').value;
    var estado   = document.getElementById('pe_estado').value;

    if (!nombre || !email || !login) {
        peMsgShow('Por favor completa nombre, correo y nombre de usuario.', 'error');
        return;
    }

    if (!peUsuarioActual && !password) {
        peMsgShow('La contraseña es obligatoria para nuevos usuarios.', 'error');
        return;
    }

    var btn = document.getElementById('peBtnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    var data = new FormData();
    data.append('action',   peUsuarioActual ? 'pe_modificar_usuario' : 'pe_agregar_usuario');
    data.append('nonce',    pe_ajax.nonce);
    data.append('user_id',  peUsuarioActual || '');
    data.append('nombre',   nombre);
    data.append('rut',      rut);
    data.append('email',    email);
    data.append('login',    login);
    data.append('password', password);
    data.append('cargo',    cargo);
    data.append('estado',   estado);

    fetch(pe_ajax.url, { method: 'POST', body: data })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.textContent = 'Guardar';
            if (res.success) {
                peMsgShow(res.data.mensaje, 'ok');
                setTimeout(function() {
                    document.getElementById('peModal').style.display = 'none';
                    location.reload();
                }, 1200);
            } else {
                peMsgShow(res.data.mensaje || 'Error al guardar.', 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Guardar';
            peMsgShow('Error de conexión. Intenta de nuevo.', 'error');
        });
}

function peMsgShow(msg, tipo) {
    var el = document.getElementById('pe_msg');
    el.textContent = msg;
    el.className = 'pe-msg ' + tipo;
    el.style.display = 'block';
}

function peDel(id, nombre) {
    if (!confirm('¿Estás seguro de eliminar a ' + nombre + '?')) return;

    var data = new FormData();
    data.append('action',  'pe_eliminar_usuario');
    data.append('nonce',   pe_ajax.nonce);
    data.append('user_id', id);

    fetch(pe_ajax.url, { method: 'POST', body: data })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                var fila = document.querySelector('#peTabla tbody tr[data-id="' + id + '"]');
                if (fila) fila.remove();
            } else {
                alert(res.data.mensaje || 'Error al eliminar.');
            }
        });
}

function peTogglePass() {
    var input = document.getElementById('pe_password');
    var icon  = document.getElementById('pe-eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    }
}