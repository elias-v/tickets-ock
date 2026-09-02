// Shared seat grid constants and rendering logic
// Used by both the frontend (app.js) and admin panel

const gridCols = [
    { idx: 1, type: 'margin' }, { idx: 2, type: 'margin' },
    { idx: 3, type: 'margin' }, { idx: 4, type: 'margin' },
    { idx: 5, type: 'margin' }, { idx: 6, type: 'margin' },
    { idx: 7, type: 'spacer' },
    { idx: 8, type: 'left' }, { idx: 9, type: 'left' },
    { idx: 10, type: 'left' }, { idx: 11, type: 'left' },
    { idx: 12, type: 'left' }, { idx: 13, type: 'left' },
    { idx: 14, type: 'left' }, { idx: 15, type: 'left' },
    { idx: 16, type: 'left' }, { idx: 17, type: 'left' },
    { idx: 18, type: 'row-num' },
    { idx: 19, type: 'right' }, { idx: 20, type: 'right' },
    { idx: 21, type: 'right' }, { idx: 22, type: 'right' },
    { idx: 23, type: 'right' }, { idx: 24, type: 'right' },
    { idx: 25, type: 'right' }, { idx: 26, type: 'right' },
    { idx: 27, type: 'right' },     { idx: 28, type: 'right' },
    { idx: 29, type: 'right' },
    { idx: 30, type: 'spacer' },
    { idx: 31, type: 'margin' }, { idx: 32, type: 'margin' },
    { idx: 33, type: 'margin' }, { idx: 34, type: 'margin' },
    { idx: 35, type: 'margin' },
];

/**
 * Renders the complete seat grid (CHOR + header + rows 2-22 + Empore).
 * If a layout object is provided, uses dynamic rendering; otherwise falls back to hardcoded grid.
 * @param {HTMLElement} container
 * @param {Array} seats
 * @param {Function} createCell - callback(seat) returning a cell element
 * @param {Object|null} layout - optional layout JSON from getActiveLayout()
 */
function renderGrid(container, seats, createCell, layout) {
    if (layout && layout.grid_config && layout.grid_config.columns) {
        renderLayoutGrid(container, seats, createCell, layout);
        return;
    }
    renderLegacyGrid(container, seats, createCell);
}

/**
 * Dynamic rendering from layout JSON.
 */
function renderLayoutGrid(container, seats, createCell, layout) {
    container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'seat-grid';

    const cols = layout.grid_config.columns;
    const totalCols = cols.length;

    const rows = {};
    seats.forEach(s => {
        if (!rows[s.row]) rows[s.row] = [];
        rows[s.row].push(s);
    });

    const rowRange = layout.grid_config.rowRange || [2, 22];
    const emporeRows = layout.grid_config.emporeRows || [];
    const emporeConfig = layout.grid_config.emporeConfig || {};
    const labels = layout.labels || [];

    const colWidths = cols.map(c => {
        if (c.type === 'spacer') return '5px';
        if (c.type === 'row-num') return '20px';
        if (c.type === 'margin') return '34px';
        return '34px';
    });

    const layoutLabels = labels.filter(l => l.position === 'chor');
    if (layoutLabels.length > 0 || true) {
        const chor = document.createElement('div');
        chor.className = 'grid-row chor-strip';
        const cl = document.createElement('div');
        cl.className = 'chor-label';
        cl.textContent = 'CHOR';
        chor.appendChild(cl);
        grid.appendChild(chor);
    }

    const hdr = document.createElement('div');
    hdr.className = 'grid-row row-header';
    const leftCols = cols.filter(c => c.type === 'left');
    const rightCols = cols.filter(c => c.type === 'right');
    if (leftCols.length > 0) {
        const ls = document.createElement('div');
        ls.className = 'col left-label';
        ls.style.gridColumn = leftCols[0].idx + ' / ' + (leftCols[leftCols.length - 1].idx + 1);
        ls.textContent = '\u2039 links';
        hdr.appendChild(ls);
    }
    if (rightCols.length > 0) {
        const rs = document.createElement('div');
        rs.className = 'col right-label';
        rs.style.gridColumn = rightCols[0].idx + ' / ' + (rightCols[rightCols.length - 1].idx + 1);
        rs.textContent = 'rechts \u203A';
        hdr.appendChild(rs);
    }
    grid.appendChild(hdr);

    const rowNums = Object.keys(rows).map(Number).sort((a, b) => a - b);

    rowNums.forEach(rn => {
        if (rn < rowRange[0] || rn > rowRange[1]) return;
        grid.appendChild(buildDynamicRow(rn, rows[rn] || [], cols, createCell));
    });

    if (emporeRows.length > 0) {
        const emporeHeader = document.createElement('div');
        emporeHeader.className = 'grid-row empore-header';
        const el = document.createElement('div');
        el.className = 'section-label empore';
        el.textContent = 'EMPORE';
        emporeHeader.appendChild(el);
        grid.appendChild(emporeHeader);

        const gap = document.createElement('div');
        gap.className = 'grid-row';
        for (let c = 1; c <= totalCols; c++) {
            const d = document.createElement('div');
            d.className = 'seat-cell empty';
            gap.appendChild(d);
        }
        grid.appendChild(gap);

        const emporeContainer = document.createElement('div');
        emporeContainer.style.cssText = 'position:relative;display:flex;flex-direction:column;gap:1px;';

        emporeRows.forEach(rn => {
            const ec = emporeConfig[rn] || {};
            const rowSeats = rows[rn] || [];
            const seatMap = {};
            rowSeats.forEach(s => { seatMap[s.col] = s; });

            const row = document.createElement('div');
            row.className = 'grid-row';

            for (let col = 1; col <= totalCols; col++) {
                const cd = cols.find(c => c.idx === col) || { type: 'empty' };

                if (cd.type === 'spacer' || cd.type === 'row-num' || cd.type === 'margin') {
                    const d = document.createElement('div');
                    d.className = 'seat-cell empty';
                    row.appendChild(d);
                    continue;
                }

                const centerCols = ec.centerCols || (ec.center ? Array.from({length: 5}, (_, i) => 16 + i) : []);
                if (centerCols.includes(col) && ec.center) {
                    if (col === centerCols[0]) {
                        const label = document.createElement('div');
                        label.className = 'section-label center-label';
                        label.style.gridColumn = centerCols[0] + ' / ' + (centerCols[centerCols.length - 1] + 1);
                        label.textContent = ec.center;
                        row.appendChild(label);
                    }
                    continue;
                }

                if (seatMap[col]) {
                    row.appendChild(createCell(seatMap[col]));
                } else {
                    const d = document.createElement('div');
                    d.className = 'seat-cell empty';
                    row.appendChild(d);
                }
            }
            emporeContainer.appendChild(row);
        });

        const emporeOverlay = labels.find(l => l.position === 'empore-overlay');
        if (emporeOverlay) {
            const overlay = document.createElement('div');
            overlay.className = 'empore-combined-label';
            overlay.textContent = emporeOverlay.text;
            emporeContainer.appendChild(overlay);
        }

        grid.appendChild(emporeContainer);

        const aufgangLabel = labels.find(l => l.position === 'empore-aufgang');
        if (aufgangLabel) {
            const aufgang = document.createElement('div');
            aufgang.className = 'grid-row';
            for (let col = 1; col <= totalCols; col++) {
                const cd = cols.find(c => c.idx === col) || { type: 'empty' };
                if (cd.type === 'spacer' || cd.type === 'row-num' || cd.type === 'margin') {
                    const d = document.createElement('div');
                    d.className = 'seat-cell empty';
                    aufgang.appendChild(d);
                    continue;
                }
                if (aufgangLabel.colStart && col === aufgangLabel.colStart) {
                    const label = document.createElement('div');
                    label.className = 'section-label center-label';
                    label.style.gridColumn = aufgangLabel.colStart + ' / ' + (aufgangLabel.colEnd || aufgangLabel.colStart + 4);
                    label.textContent = aufgangLabel.text;
                    aufgang.appendChild(label);
                    continue;
                }
                if (aufgangLabel.colStart && col > aufgangLabel.colStart && col < (aufgangLabel.colEnd || aufgangLabel.colStart + 4)) {
                    continue;
                }
                const d = document.createElement('div');
                d.className = 'seat-cell empty';
                aufgang.appendChild(d);
            }
            grid.appendChild(aufgang);
        }
    }

    container.appendChild(grid);
}

function buildDynamicRow(rn, rowSeats, cols, createCell) {
    const seatMap = {};
    rowSeats.forEach(s => { seatMap[s.col] = s; });
    const row = document.createElement('div');
    row.className = 'grid-row';

    cols.forEach(cd => {
        if (cd.type === 'spacer' || cd.type === 'margin') {
            const el = document.createElement('div');
            el.className = 'seat-cell empty';
            row.appendChild(el);
            return;
        }

        if (cd.type === 'row-num') {
            const el = document.createElement('div');
            el.className = 'row-number';
            el.textContent = rn;
            row.appendChild(el);
            return;
        }

        if (seatMap[cd.idx]) {
            row.appendChild(createCell(seatMap[cd.idx]));
        } else {
            const el = document.createElement('div');
            el.className = 'seat-cell empty';
            row.appendChild(el);
        }
    });
    return row;
}

/**
 * Legacy hardcoded grid rendering (fallback when no layout is available).
 */
function renderLegacyGrid(container, seats, createCell) {
    container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'seat-grid';

    const rows = {};
    seats.forEach(s => {
        if (!rows[s.row]) rows[s.row] = [];
        rows[s.row].push(s);
    });
    const rowNums = Object.keys(rows).map(Number).sort((a, b) => a - b);

    const chor = document.createElement('div');
    chor.className = 'grid-row chor-strip';
    const cl = document.createElement('div');
    cl.className = 'chor-label';
    cl.textContent = 'CHOR';
    chor.appendChild(cl);
    grid.appendChild(chor);

    const hdr = document.createElement('div');
    hdr.className = 'grid-row row-header';
    const ls = document.createElement('div');
    ls.className = 'col left-label';
    ls.style.gridColumn = '8 / 18';
    ls.textContent = '\u2039 links';
    const rs = document.createElement('div');
    rs.className = 'col right-label';
    rs.style.gridColumn = '19 / 29';
    rs.textContent = 'rechts \u203A';
    hdr.appendChild(ls);
    hdr.appendChild(rs);
    grid.appendChild(hdr);

    rowNums.forEach(rn => {
        if (rn < 2 || rn > 22) return;
        grid.appendChild(buildLegacyRow(rn, rows[rn] || [], createCell));
    });

    addLegacyEmporeSection(grid, rows, createCell);

    container.appendChild(grid);
}

function buildLegacyRow(rn, rowSeats, createCell) {
    const seatMap = {};
    rowSeats.forEach(s => { seatMap[s.col] = s; });
    const row = document.createElement('div');
    row.className = 'grid-row';

    for (let col = 1; col <= 35; col++) {
        const cd = gridCols[col - 1];

        if (cd.type === 'spacer') {
            const el = document.createElement('div');
            el.className = 'seat-cell empty';
            row.appendChild(el);
            continue;
        }

        if (cd.type === 'row-num') {
            const el = document.createElement('div');
            el.className = 'row-number';
            el.textContent = rn;
            row.appendChild(el);
            continue;
        }

        if (seatMap[col]) {
            row.appendChild(createCell(seatMap[col]));
        } else {
            const el = document.createElement('div');
            el.className = 'seat-cell empty';
            row.appendChild(el);
        }
    }
    return row;
}

function addLegacyEmporeSection(grid, rows, createCell) {
    const eh = document.createElement('div');
    eh.className = 'grid-row empore-header';
    const el = document.createElement('div');
    el.className = 'section-label empore';
    el.textContent = 'EMPORE';
    eh.appendChild(el);
    grid.appendChild(eh);

    const gap = document.createElement('div');
    gap.className = 'grid-row';
    for (let c = 1; c <= 35; c++) {
        const d = document.createElement('div');
        d.className = 'seat-cell empty';
        gap.appendChild(d);
    }
    grid.appendChild(gap);

    const empContainer = document.createElement('div');
    empContainer.style.cssText = 'position:relative;display:flex;flex-direction:column;gap:1px;';

    empContainer.appendChild(buildLegacyEmporeRow(rows[23] || [], 'spieltisch', '', createCell));
    empContainer.appendChild(buildLegacyEmporeRow(rows[24] || [], '', '', createCell));
    empContainer.appendChild(buildLegacyEmporeRow(rows[25] || [], 'orgel', '', createCell));
    empContainer.appendChild(buildLegacyEmporeRow(rows[26] || [], 'turm', '', createCell));

    const combinedLabel = document.createElement('div');
    combinedLabel.className = 'empore-combined-label';
    combinedLabel.innerHTML = 'Orgel';
    empContainer.appendChild(combinedLabel);

    grid.appendChild(empContainer);

    const aufgang = document.createElement('div');
    aufgang.className = 'grid-row';
    for (let col = 1; col <= 35; col++) {
        const cd = gridCols[col - 1];
        if (cd.type === 'spacer' || cd.type === 'row-num') {
            const d = document.createElement('div');
            d.className = 'seat-cell empty';
            aufgang.appendChild(d);
            continue;
        }
        if (col >= 26 && col <= 29) {
            if (col === 26) {
                const label = document.createElement('div');
                label.className = 'section-label center-label';
                label.style.gridColumn = '26 / 30';
                label.textContent = 'Aufgang';
                aufgang.appendChild(label);
            }
            continue;
        }
        const d = document.createElement('div');
        d.className = 'seat-cell empty';
        aufgang.appendChild(d);
    }
    grid.appendChild(aufgang);
}

function buildLegacyEmporeRow(seats, centerType, centerLabel, createCell) {
    const seatMap = {};
    seats.forEach(s => { seatMap[s.col] = s; });
    const row = document.createElement('div');
    row.className = 'grid-row';

    for (let col = 1; col <= 35; col++) {
        const cd = gridCols[col - 1];

        if (cd.type === 'spacer' || cd.type === 'row-num') {
            const d = document.createElement('div');
            d.className = 'seat-cell empty';
            row.appendChild(d);
            continue;
        }

        if (col >= 16 && col <= 20 && centerType !== '') {
            if (col === 16) {
                const label = document.createElement('div');
                label.className = 'section-label center-label';
                label.style.gridColumn = '16 / 21';
                label.textContent = centerLabel;
                row.appendChild(label);
            }
            continue;
        }

        if (seatMap[col]) {
            row.appendChild(createCell(seatMap[col]));
        } else {
            const d = document.createElement('div');
            d.className = 'seat-cell empty';
            row.appendChild(d);
        }
    }
    return row;
}
