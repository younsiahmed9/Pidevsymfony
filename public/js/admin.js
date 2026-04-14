document.addEventListener('DOMContentLoaded', function () {
    const hash = window.location.hash;
    if (hash) {
        const targetPane = document.querySelector(hash + '.tab-pane');
        const allPanes = document.querySelectorAll('.admin-content .tab-pane');
        if (targetPane && allPanes.length > 0) {
            allPanes.forEach(function (pane) {
                pane.classList.remove('show', 'active');
            });
            targetPane.classList.add('show', 'active');
        }
    }

    const searchInput = document.querySelector('.search');

    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    const tableWrappers = document.querySelectorAll('.table-responsive');

    tableWrappers.forEach(function (wrapper, index) {
        const table = wrapper.querySelector('table');
        if (!table || table.classList.contains('users-table')) {
            return;
        }

        const tbody = table.querySelector('tbody');
        const headers = Array.from(table.querySelectorAll('thead th'));
        if (!tbody || headers.length === 0) {
            return;
        }

        const dataRows = Array.from(tbody.querySelectorAll('tr')).filter(function (row) {
            const firstCell = row.querySelector('td');
            if (!firstCell) {
                return false;
            }

            const colspan = Number(firstCell.getAttribute('colspan') || '1');
            return colspan <= 1 || row.querySelectorAll('td').length > 1;
        });

        if (dataRows.length === 0) {
            return;
        }

        const existingToolbar = wrapper.previousElementSibling;
        if (existingToolbar && existingToolbar.classList.contains('bo-table-tools')) {
            return;
        }

        const toolbar = document.createElement('div');
        toolbar.className = 'bo-table-tools d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3';
        toolbar.innerHTML = '' +
            '<div class="d-flex flex-wrap gap-2 align-items-center">' +
                '<input type="search" class="form-control form-control-sm bo-table-search" placeholder="Recherche dynamique..." style="min-width: 220px;">' +
                '<select class="form-select form-select-sm bo-table-sort-col" style="min-width: 170px;"></select>' +
                '<select class="form-select form-select-sm bo-table-sort-dir" style="width: 120px;">' +
                    '<option value="asc">Tri ASC</option>' +
                    '<option value="desc">Tri DESC</option>' +
                '</select>' +
            '</div>' +
            '<div class="d-flex gap-2">' +
                '<span class="badge text-bg-primary bo-kpi-total"></span>' +
                '<span class="badge text-bg-info bo-kpi-visible"></span>' +
            '</div>';

        const searchField = toolbar.querySelector('.bo-table-search');
        const sortColumn = toolbar.querySelector('.bo-table-sort-col');
        const sortDirection = toolbar.querySelector('.bo-table-sort-dir');
        const kpiTotal = toolbar.querySelector('.bo-kpi-total');
        const kpiVisible = toolbar.querySelector('.bo-kpi-visible');

        const noSortOption = document.createElement('option');
        noSortOption.value = '-1';
        noSortOption.textContent = 'Tri: colonne';
        sortColumn.appendChild(noSortOption);

        headers.forEach(function (th, colIndex) {
            const option = document.createElement('option');
            option.value = String(colIndex);
            option.textContent = (th.textContent || ('Colonne ' + (colIndex + 1))).trim();
            sortColumn.appendChild(option);
        });

        function normalize(value) {
            return String(value || '').toLowerCase().trim();
        }

        function parseSortValue(text) {
            const normalized = (text || '').replace(/\s+/g, ' ').trim();
            const numericCandidate = normalized.replace(/[^0-9,.-]/g, '').replace(',', '.');
            const asNumber = Number(numericCandidate);
            if (!Number.isNaN(asNumber) && numericCandidate !== '' && /\d/.test(numericCandidate)) {
                return asNumber;
            }

            const asDate = Date.parse(normalized);
            if (!Number.isNaN(asDate)) {
                return asDate;
            }

            return normalize(normalized);
        }

        function getCellText(row, colIndex) {
            const cell = row.children[colIndex];
            return cell ? (cell.textContent || '') : '';
        }

        function applyTableTools() {
            const query = normalize(searchField.value);
            const colIndex = Number(sortColumn.value);
            const dir = sortDirection.value === 'desc' ? -1 : 1;

            let visibleRows = dataRows.filter(function (row) {
                return query === '' || normalize(row.textContent).includes(query);
            });

            if (colIndex >= 0) {
                visibleRows = visibleRows.slice().sort(function (a, b) {
                    const left = parseSortValue(getCellText(a, colIndex));
                    const right = parseSortValue(getCellText(b, colIndex));

                    if (left < right) return -1 * dir;
                    if (left > right) return 1 * dir;
                    return 0;
                });
            }

            dataRows.forEach(function (row) {
                row.style.display = 'none';
            });

            visibleRows.forEach(function (row) {
                row.style.display = '';
                tbody.appendChild(row);
            });

            kpiTotal.textContent = 'Total: ' + dataRows.length;
            kpiVisible.textContent = 'Affiches: ' + visibleRows.length;
        }

        searchField.addEventListener('input', applyTableTools);
        sortColumn.addEventListener('change', applyTableTools);
        sortDirection.addEventListener('change', applyTableTools);

        wrapper.parentNode.insertBefore(toolbar, wrapper);
        applyTableTools();
    });
});
