// ── Accordion ──────────────────────────
function csToggle(btn) {
    var item = btn.closest('.cs-acc-item');
    var body = item.querySelector('.cs-acc-body');
    var arrow = item.querySelector('.cs-acc-arrow');

    var isOpen = body.classList.contains('open');

    // Cerrar todos
    document.querySelectorAll('.cs-acc-body').forEach(function(b) {
        b.classList.remove('open');
    });
    document.querySelectorAll('.cs-acc-arrow').forEach(function(a) {
        a.style.transform = '';
    });

    // Abrir el clickeado si estaba cerrado
    if (!isOpen) {
        body.classList.add('open');
        arrow.style.transform = 'rotate(180deg)';
    }
}

// ── Marcar lección como completada ──────
function massCompletarLeccion(btn) {
    var leccionId = btn.getAttribute('data-leccion');

    btn.disabled    = true;
    btn.textContent = 'Guardando...';

    fetch(MASS.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mass_completar_leccion'
            + '&leccion_id=' + leccionId
            + '&nonce='      + MASS.nonce
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            btn.outerHTML = '<span class="ls-completada-badge">Lección completada</span>';
            if (res.data.certificado) {
                alert(' ¡Felicitaciones! Completaste el curso.');
            }
        } else {
            btn.disabled    = false;
            btn.textContent = 'Marcar como completada ';
            alert(res.data.mensaje || 'Error al guardar.');
        }
    })
    .catch(function() {
        btn.disabled    = false;
        btn.textContent = 'Marcar como completada ';
        alert('Error de conexión.');
    });
}