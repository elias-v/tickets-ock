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
 * @param {HTMLElement} container - the element to render into
 * @param {Array} seats - seat objects from get-seats.php
 * @param {Function} createCell - callback(seat) returning a cell element
 */
function renderGrid(container, seats, createCell) {
    container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'seat-grid';

    // Group seats by row
    const rows = {};
    seats.forEach(s => {
        if (!rows[s.row]) rows[s.row] = [];
        rows[s.row].push(s);
    });
    const rowNums = Object.keys(rows).map(Number).sort((a, b) => a - b);

    // CHOR strip
    const chor = document.createElement('div');
    chor.className = 'grid-row chor-strip';
    const cl = document.createElement('div');
    cl.className = 'chor-label';
    cl.textContent = 'CHOR';
    chor.appendChild(cl);
    grid.appendChild(chor);

    // Header row
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

    // Rows 2-22
    rowNums.forEach(rn => {
        if (rn < 2 || rn > 22) return;
        grid.appendChild(buildRow(rn, rows[rn] || [], createCell));
    });

    // Empore section
    addEmporeSection(grid, rows, createCell);

    container.appendChild(grid);
}

/**
 * Builds a single standard grid row.
 */
function buildRow(rn, rowSeats, createCell) {
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

/**
 * Builds a row for the Empore section (rows 23-26).
 * Handles center labels (Spieltisch, Orgel, Turm) and Aufgang.
 */
function addEmporeSection(grid, rows, createCell) {
    // Empore header
    const eh = document.createElement('div');
    eh.className = 'grid-row empore-header';
    const el = document.createElement('div');
    el.className = 'section-label empore';
    el.textContent = 'EMPORE';
    eh.appendChild(el);
    grid.appendChild(eh);

    // Gap row
    const gap = document.createElement('div');
    gap.className = 'grid-row';
    for (let c = 1; c <= 35; c++) {
        const d = document.createElement('div');
        d.className = 'seat-cell empty';
        gap.appendChild(d);
    }
    grid.appendChild(gap);

    // Wrap rows 23-26 in a relative container for the combined label
    const container = document.createElement('div');
    container.style.cssText = 'position:relative;display:flex;flex-direction:column;gap:1px;';

    // Row 23 – 471-477 left (no individual center label)
    const r23 = rows[23] || [];
    container.appendChild(buildEmporeRow(r23, 'spieltisch', '', createCell));

    // Row 24 – 481-487 left + 461-464 right
    const r24 = rows[24] || [];
    container.appendChild(buildEmporeRow(r24, '', '', createCell));

    // Row 25 – 491-497 left + 465-468 right (no individual center label)
    const r25 = rows[25] || [];
    container.appendChild(buildEmporeRow(r25, 'orgel', '', createCell));

    // Row 26 – 501-507 left (no individual center label)
    const r26 = rows[26] || [];
    container.appendChild(buildEmporeRow(r26, 'turm', '', createCell));

    // Combined label overlay for Spieltisch / Orgel / Turm
    const combinedLabel = document.createElement('div');
    combinedLabel.className = 'empore-combined-label';
    combinedLabel.innerHTML = 'Orgel';
    container.appendChild(combinedLabel);

    grid.appendChild(container);

    // Aufgang row
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

/**
 * Builds a single Empore row with optional center label.
 */
function buildEmporeRow(seats, centerType, centerLabel, createCell) {
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
