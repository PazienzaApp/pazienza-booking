(function () {
    var data = window.pazienzaMyAccountData || {};

    document.querySelectorAll('.pazienza-appt-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm(data.confirmMsg || 'Confermi la cancellazione di questa prenotazione?')) return;
            btn.disabled    = true;
            btn.textContent = data.cancellingMsg || 'Annullamento…';

            fetch(data.cancelUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce},
                body: JSON.stringify({appointment_id: btn.dataset.id}),
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.cancelled) {
                    btn.closest('tr').style.opacity = '.4';
                    btn.textContent = data.cancelledMsg || 'Annullata';
                } else {
                    alert(res.message || (data.errorMsg || 'Errore nella cancellazione.'));
                    btn.disabled    = false;
                    btn.textContent = data.cancelLabel || 'Annulla';
                }
            })
            .catch(function () {
                btn.disabled    = false;
                btn.textContent = data.cancelLabel || 'Annulla';
            });
        });
    });
})();
