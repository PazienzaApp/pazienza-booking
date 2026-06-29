(function () {
    var data  = window.pazienzaResourcesData || {};
    var nonce = data.nonce || '';

    function toggle(action, id, value, labelEl) {
        labelEl.querySelector('input').disabled = true;
        fetch(window.ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: action, id: id, value: value ? '1' : '0', _ajax_nonce: nonce}),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) {
                alert(res.data || (data.labelError || 'Errore API'));
                labelEl.querySelector('input').checked = !value;
            }
            labelEl.querySelector('span').textContent = labelEl.querySelector('input').checked
                ? (data.labelYes || 'Sì')
                : (data.labelNo  || 'No');
        })
        .finally(function () {
            labelEl.querySelector('input').disabled = false;
        });
    }

    document.querySelectorAll('.pbf-toggle-resource').forEach(function (el) {
        el.addEventListener('change', function () {
            toggle('pazienza_booking_toggle_resource', el.dataset.id, el.checked, el.closest('label'));
        });
    });

    document.querySelectorAll('.pbf-toggle-product').forEach(function (el) {
        el.addEventListener('change', function () {
            toggle('pazienza_booking_toggle_product', el.dataset.id, el.checked, el.closest('label'));
        });
    });
})();
