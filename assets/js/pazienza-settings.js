(function () {
    var data  = window.pazienzaSettingsData || {};
    var table = document.getElementById('pazienza-custom-fields-table');
    if (!table) return;

    var tbody = table.querySelector('tbody');
    var idx   = data.fieldCount || 0;
    var types = ['text', 'textarea', 'select', 'radio', 'checkbox'];

    document.getElementById('pbf-add-field').addEventListener('click', function (e) {
        e.preventDefault();
        var opts = types.map(function (t) { return '<option value="' + t + '">' + t + '</option>'; }).join('');
        var row  = document.createElement('tr');
        row.innerHTML =
            '<td><input type="text" name="pbf_fields[' + idx + '][id]" style="width:100%" placeholder="es. fonte"></td>' +
            '<td><input type="text" name="pbf_fields[' + idx + '][label]" style="width:100%"></td>' +
            '<td><select name="pbf_fields[' + idx + '][type]" style="width:100%">' + opts + '</select></td>' +
            '<td><textarea name="pbf_fields[' + idx + '][options]" rows="3" style="width:100%"></textarea></td>' +
            '<td style="text-align:center"><input type="checkbox" name="pbf_fields[' + idx + '][required]" value="1"></td>' +
            '<td><a href="#" class="pbf-remove-row button button-small">' + (data.labelRemove || 'Rimuovi') + '</a></td>';
        tbody.appendChild(row);
        idx++;
        bindRemove(row.querySelector('.pbf-remove-row'));
    });

    document.querySelectorAll('.pbf-remove-row').forEach(bindRemove);

    function bindRemove(el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            el.closest('tr').remove();
        });
    }
})();
