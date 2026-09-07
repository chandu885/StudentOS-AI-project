<?php
// frontend/components/tables.php

function renderTableHeader($columns = []) {
    ?>
    <thead>
        <tr>
            <?php foreach ($columns as $col): ?>
                <th><?php echo htmlspecialchars($col); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <?php
}

function renderTableControls($searchPlaceholder = 'Search records...', $filterOptions = []) {
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <div style="position: relative; width: 260px;">
            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
            <input type="text" class="form-control" style="padding-left: 34px; height: 38px; border-radius: var(--radius-md);" placeholder="<?php echo htmlspecialchars($searchPlaceholder); ?>" onkeyup="filterTableRows(this)">
        </div>
        <?php if (!empty($filterOptions)): ?>
            <div style="display: flex; gap: 8px;">
                <select class="form-control" style="width: auto; height: 38px;">
                    <option value="">All Categories</option>
                    <?php foreach ($filterOptions as $val => $name): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    <script>
    function filterTableRows(input) {
        const term = input.value.toLowerCase();
        const table = input.closest('.card-body, .dashboard-content').querySelector('table tbody');
        if (!table) return;
        const rows = table.getElementsByTagName('tr');
        for (let row of rows) {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        }
    }
    </script>
    <?php
}
