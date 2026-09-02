<?php
require_once __DIR__ . '/../config.php';
ensureLayoutSchema();

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$db = getDb();
$csrfToken = generateCsrfToken();
$layouts = getAllLayouts();
$activeLayout = getActiveLayout();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitzplan-Editor – Oratorienchor Kreuzlingen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f5f3ef; color: #1a1a2e; }
        header { background: #2c3e50; color: #fff; padding: 16px 0; margin-bottom: 24px; }
        header .container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        header h1 { font-size: 1.25rem; font-weight: 600; }
        header a { color: #c8a96e; text-decoration: none; font-size: 0.9rem; }
        header a:hover { text-decoration: underline; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        .editor-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .editor-layout { grid-template-columns: 1fr; }
        }

        .sidebar {
            background: #fff;
            border: 1px solid #e0dcd4;
            border-radius: 8px;
            padding: 16px;
            position: sticky;
            top: 20px;
        }
        .sidebar h3 {
            font-size: 0.9rem;
            margin-bottom: 12px;
            color: #2c3e50;
        }
        .sidebar .form-group {
            margin-bottom: 12px;
        }
        .sidebar .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .sidebar .form-group input,
        .sidebar .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e0dcd4;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
        }

        .tool-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            margin-bottom: 12px;
        }
        .tool-btn {
            padding: 8px 6px;
            border: 2px solid #e0dcd4;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
            transition: all 0.15s;
            font-family: inherit;
        }
        .tool-btn:hover { border-color: #c8a96e; background: #fdf8ee; }
        .tool-btn.active { border-color: #c8a96e; background: #fdf3dc; font-weight: 700; }
        .tool-btn.seat-tool { border-color: #b8d4ed; }
        .tool-btn.seat-tool.active { background: #e8f0f8; border-color: #6aabe0; }
        .tool-btn.seat1-tool { border-color: #dba8a3; }
        .tool-btn.seat1-tool.active { background: #f8e8e6; border-color: #c87870; }
        .tool-btn.stage-tool { border-color: #999; }
        .tool-btn.stage-tool.active { background: #f0f0f0; border-color: #666; }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: #c8a96e;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            text-align: center;
        }
        .btn:hover { background: #b8974d; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-outline {
            background: transparent;
            border: 1px solid #e0dcd4;
            color: #555;
        }
        .btn-outline:hover { background: #f5f3ef; border-color: #c8a96e; color: #c8a96e; }

        .btn-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .grid-canvas {
            background: #fff;
            border: 1px solid #e0dcd4;
            border-radius: 8px;
            padding: 20px;
            overflow-x: auto;
        }

        .editor-grid {
            display: inline-grid;
            gap: 1px;
            background: #eee;
            border: 1px solid #ddd;
        }

        .editor-cell {
            width: 30px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.55rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.1s;
            position: relative;
            user-select: none;
            border: 1px solid transparent;
        }
        .editor-cell:hover { z-index: 2; transform: scale(1.2); }
        .editor-cell.type-empty { background: #fafafa; border-color: #eee; }
        .editor-cell.type-seat { background: #b8d4ed; color: #2a5a8a; border-color: #8bb8d9; }
        .editor-cell.type-seat.cat-1 { background: #ecc8c5; color: #8b3a3a; border-color: #dba8a3; }
        .editor-cell.type-stage { background: #d5d5d5; color: #555; border-color: #bbb; }
        .editor-cell.type-aisle { background: #f0ede8; color: #999; border-color: #d8d4cc; }
        .editor-cell.type-tech { background: #e8e0d0; color: #7a6a50; border-color: #c8b898; }
        .editor-cell.type-rownum { background: transparent; color: #888; border: none; font-size: 0.6rem; }
        .editor-cell.type-margin { background: transparent; border: none; }
        .editor-cell.type-spacer { background: transparent; border: none; }
        .editor-cell.selected { outline: 2px solid #2ecc71; outline-offset: -1px; }
        .editor-cell.is-bodan { outline: 2px solid #999; outline-offset: -3px; }

        .editor-cell .cell-label {
            position: absolute;
            bottom: calc(100% + 1px);
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: #fff;
            font-size: 0.6rem;
            padding: 2px 5px;
            border-radius: 3px;
            white-space: nowrap;
            pointer-events: none;
            display: none;
            z-index: 10;
        }
        .editor-cell:hover .cell-label { display: block; }

        .label-overlay {
            position: absolute;
            background: #faf8f5;
            border: 1px solid #9e9682;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: #7a7262;
            pointer-events: none;
            z-index: 5;
        }

        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: #fff;
            border: 1px solid #e0dcd4;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 0.82rem;
            color: #666;
        }
        .status-bar strong { color: #2c3e50; }

        .layout-list {
            list-style: none;
            margin-bottom: 12px;
        }
        .layout-list li {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 0.82rem;
            cursor: pointer;
            transition: background 0.15s;
        }
        .layout-list li:hover { background: #f5f3ef; }
        .layout-list li.active { background: #fdf3dc; font-weight: 600; }
        .layout-list li .badge-active {
            display: inline-block;
            padding: 1px 6px;
            background: #2ecc71;
            color: #fff;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .modal-content h3 { font-size: 1rem; margin-bottom: 16px; }

        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 0.9rem; display: none; }
        .message.show { display: block; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        textarea.json-export {
            width: 100%;
            min-height: 200px;
            font-family: monospace;
            font-size: 0.75rem;
            border: 1px solid #e0dcd4;
            border-radius: 6px;
            padding: 10px;
            resize: vertical;
        }
    </style>
</head>
<body>

<header>
    <div class="container">
        <h1>Sitzplan-Editor</h1>
        <div>
            <a href="index.php">&#8592; Zurück zum Admin</a>
        </div>
    </div>
</header>

<div class="container">
    <div class="message" id="status-msg"></div>

    <div class="status-bar">
        <span id="status-text">Lade...</span>
        <span id="seat-count"></span>
    </div>

    <div class="editor-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3>Layouts</h3>
            <ul class="layout-list" id="layout-list"></ul>
            <div class="btn-group" style="margin-bottom: 16px;">
                <button class="btn btn-sm" onclick="newLayout()">+ Neues Layout</button>
            </div>

            <h3>Eigenschaften</h3>
            <div class="form-group">
                <label for="layout-name">Name</label>
                <input type="text" id="layout-name" placeholder="z.B. Konzertsaal">
            </div>
            <div class="form-group">
                <label for="layout-cols">Spalten</label>
                <input type="number" id="layout-cols" min="1" max="100" value="35">
            </div>
            <div class="form-group">
                <label for="layout-rows">Zeilen</label>
                <input type="number" id="layout-rows" min="1" max="100" value="30">
            </div>

            <h3 style="margin-top: 16px;">Werkzeug</h3>
            <div class="tool-grid">
                <button class="tool-btn type-empty-tool active" data-type="empty" onclick="setTool('empty', this)">Leer</button>
                <button class="tool-btn seat1-tool" data-type="seat-1" onclick="setTool('seat-1', this)">Sitz Kat.1</button>
                <button class="tool-btn seat-tool" data-type="seat-2" onclick="setTool('seat-2', this)">Sitz Kat.2</button>
                <button class="tool-btn seat-tool" data-type="seat-3" onclick="setTool('seat-3', this)">Sitz Kat.3</button>
                <button class="tool-btn stage-tool" data-type="stage" onclick="setTool('stage', this)">Bühne</button>
                <button class="tool-btn" data-type="aisle" onclick="setTool('aisle', this)">Gang</button>
                <button class="tool-btn" data-type="tech" onclick="setTool('tech', this)">Technik</button>
                <button class="tool-btn" data-type="row-num" onclick="setTool('row-num', this)">Zeilennr.</button>
                <button class="tool-btn" data-type="spacer" onclick="setTool('spacer', this)">Abstand</button>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="tool-bodan"> Bodan-Markierung
                </label>
            </div>

            <h3 style="margin-top: 16px;">Aktionen</h3>
            <div class="btn-group" style="margin-bottom: 8px;">
                <button class="btn btn-sm" onclick="saveLayout()">Speichern</button>
                <button class="btn btn-sm btn-outline" onclick="activateLayout()">Aktivieren</button>
            </div>
            <div class="btn-group" style="margin-bottom: 8px;">
                <button class="btn btn-sm btn-outline" onclick="showExport()">Export JSON</button>
                <button class="btn btn-sm btn-outline" onclick="showImport()">Import JSON</button>
            </div>
            <div class="btn-group">
                <button class="btn btn-sm btn-danger" onclick="deleteLayout()">Löschen</button>
            </div>
        </div>

        <!-- Main grid canvas -->
        <div class="grid-canvas" id="grid-canvas"></div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal-overlay" id="export-modal">
    <div class="modal-content">
        <h3>Layout exportieren</h3>
        <textarea class="json-export" id="export-json" readonly></textarea>
        <div class="btn-group" style="margin-top: 12px;">
            <button class="btn btn-sm" onclick="copyExport()">Kopieren</button>
            <button class="btn btn-sm btn-outline" onclick="downloadExport()">Herunterladen</button>
            <button class="btn btn-sm btn-outline" onclick="closeModals()">Schliessen</button>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal-overlay" id="import-modal">
    <div class="modal-content">
        <h3>Layout importieren</h3>
        <p style="font-size:0.82rem;color:#666;margin-bottom:12px;">JSON-Datei oder Text einfügen:</p>
        <textarea class="json-export" id="import-json" placeholder='{"name":"...","cols":35,"rows":30,...}'></textarea>
        <div class="btn-group" style="margin-top: 12px;">
            <button class="btn btn-sm" onclick="doImport()">Importieren</button>
            <button class="btn btn-sm btn-outline" onclick="closeModals()">Schliessen</button>
        </div>
    </div>
</div>

<!-- Label Modal -->
<div class="modal-overlay" id="label-modal">
    <div class="modal-content">
        <h3>Label bearbeiten</h3>
        <div class="form-group">
            <label for="label-text">Text</label>
            <input type="text" id="label-text" placeholder="z.B. Orgel">
        </div>
        <div class="form-group">
            <label for="label-type">Positionstyp</label>
            <select id="label-type">
                <option value="chor">CHOR (volle Breite oben)</option>
                <option value="empore">EMPORE (Abschnittstitel)</option>
                <option value="center-label">Mittiges Label (Spaltenbereich)</option>
                <option value="custom">Frei positioniert</option>
            </select>
        </div>
        <div class="btn-group" style="margin-top: 12px;">
            <button class="btn btn-sm" onclick="saveLabel()">Speichern</button>
            <button class="btn btn-sm btn-outline" onclick="closeModals()">Abbrechen</button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';

let currentLayout = null;
let currentTool = 'empty';
let gridCells = {};
let isDrawing = false;
let cellWidth = 30;
let cellHeight = 24;

function showMessage(text, type) {
    const el = document.getElementById('status-msg');
    el.textContent = text;
    el.className = 'message ' + type + ' show';
    setTimeout(() => el.classList.remove('show'), 3000);
}

async function loadLayoutList() {
    try {
        const res = await fetch('layout.php?list=1');
        const data = await res.json();
        const list = document.getElementById('layout-list');
        list.innerHTML = '';
        (data.layouts || []).forEach(l => {
            const li = document.createElement('li');
            li.className = l.is_active == 1 ? 'active' : '';
            li.innerHTML = '<span>' + escHtml(l.name) + '</span>';
            if (l.is_active == 1) li.innerHTML += ' <span class="badge-active">aktiv</span>';
            li.onclick = () => loadLayout(l.id);
            list.appendChild(li);
        });
    } catch (e) {
        console.error(e);
    }
}

async function loadLayout(id) {
    try {
        const res = await fetch('layout.php?id=' + id);
        if (!res.ok) throw new Error('Not found');
        currentLayout = await res.json();
        applyLayoutToUI();
        renderEditorGrid();
    } catch (e) {
        showMessage('Fehler beim Laden des Layouts.', 'error');
    }
}

function applyLayoutToUI() {
    document.getElementById('layout-name').value = currentLayout.name || '';
    document.getElementById('layout-cols').value = currentLayout.cols || 35;
    document.getElementById('layout-rows').value = currentLayout.rows || 30;
    gridCells = currentLayout.cells || {};
    updateStatus();
}

function getGridConfig() {
    const cols = parseInt(document.getElementById('layout-cols').value) || 35;
    const config = { columns: [] };
    for (let i = 1; i <= cols; i++) {
        const existing = currentLayout?.grid_config?.columns?.find(c => c.idx === i);
        config.columns.push({ idx: i, type: existing?.type || 'left' });
    }
    return config;
}

function renderEditorGrid() {
    const canvas = document.getElementById('grid-canvas');
    canvas.innerHTML = '';
    const cols = parseInt(document.getElementById('layout-cols').value) || 35;
    const rows = parseInt(document.getElementById('layout-rows').value) || 30;

    const grid = document.createElement('div');
    grid.className = 'editor-grid';
    grid.style.gridTemplateColumns = `repeat(${cols}, ${cellWidth}px)`;

    for (let r = 1; r <= rows; r++) {
        for (let c = 1; c <= cols; c++) {
            const cell = document.createElement('div');
            const key = r + ':' + c;
            const data = gridCells[key] || { type: 'empty' };

            let cls = 'editor-cell type-' + data.type;
            if (data.type === 'seat' && data.category === 1) cls += ' cat-1';
            if (data.bodan) cls += ' is-bodan';

            cell.className = cls;
            cell.dataset.row = r;
            cell.dataset.col = c;
            cell.dataset.key = key;

            if (data.type === 'seat') {
                cell.textContent = data.seat_number || '';
            } else if (data.type === 'row-num') {
                cell.textContent = r;
            }

            cell.addEventListener('mousedown', (e) => {
                if (e.button !== 0) return;
                isDrawing = true;
                paintCell(cell, key, e.shiftKey);
            });
            cell.addEventListener('mouseenter', (e) => {
                if (isDrawing) paintCell(cell, key, e.shiftKey);
            });
            cell.addEventListener('dblclick', () => editCellLabel(key));

            grid.appendChild(cell);
        }
    }

    canvas.appendChild(grid);
    document.addEventListener('mouseup', () => { isDrawing = false; });
}

function paintCell(cellEl, key, isMulti) {
    const toolType = currentTool;
    const isBodan = document.getElementById('tool-bodan').checked;

    let data;
    switch (toolType) {
        case 'empty':
            data = { type: 'empty' };
            break;
        case 'seat-1':
            data = { type: 'seat', category: 1 };
            break;
        case 'seat-2':
            data = { type: 'seat', category: 2 };
            break;
        case 'seat-3':
            data = { type: 'seat', category: 3 };
            break;
        case 'stage':
            data = { type: 'stage' };
            break;
        case 'aisle':
            data = { type: 'aisle' };
            break;
        case 'tech':
            data = { type: 'tech' };
            break;
        case 'row-num':
            data = { type: 'row-num' };
            break;
        case 'spacer':
            data = { type: 'spacer' };
            break;
        default:
            data = { type: 'empty' };
    }

    if (isBodan && data.type === 'seat') {
        data.bodan = true;
    }

    gridCells[key] = data;

    const r = parseInt(cellEl.dataset.row);
    const c = parseInt(cellEl.dataset.col);

    let cls = 'editor-cell type-' + data.type;
    if (data.type === 'seat' && data.category === 1) cls += ' cat-1';
    if (data.bodan) cls += ' is-bodan';
    cellEl.className = cls;

    if (data.type === 'seat') {
        cellEl.textContent = '';
    } else if (data.type === 'row-num') {
        cellEl.textContent = r;
    } else {
        cellEl.textContent = '';
    }

    updateStatus();
}

function setTool(tool, btn) {
    currentTool = tool;
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function updateStatus() {
    const seatCount = Object.values(gridCells).filter(c => c.type === 'seat').length;
    const totalCount = Object.keys(gridCells).length;
    document.getElementById('seat-count').textContent = seatCount + ' Sitzplätze / ' + totalCount + ' Zellen';
    document.getElementById('status-text').textContent = currentLayout
        ? 'Layout: ' + currentLayout.name + (currentLayout.is_active ? ' (aktiv)' : '')
        : 'Neues Layout';
}

function newLayout() {
    currentLayout = null;
    gridCells = {};
    document.getElementById('layout-name').value = '';
    document.getElementById('layout-cols').value = 35;
    document.getElementById('layout-rows').value = 30;
    renderEditorGrid();
    updateStatus();
    loadLayoutList();
}

async function saveLayout() {
    const name = document.getElementById('layout-name').value.trim();
    if (!name) {
        showMessage('Bitte einen Namen eingeben.', 'error');
        return;
    }

    const cols = parseInt(document.getElementById('layout-cols').value) || 35;
    const rows = parseInt(document.getElementById('layout-rows').value) || 30;

    const body = {
        action: 'save',
        csrf_token: CSRF_TOKEN,
        name: name,
        cols: cols,
        rows: rows,
        grid_config: getGridConfig(),
        cells: gridCells,
        labels: currentLayout?.labels || [],
        set_active: !currentLayout || !currentLayout.is_active,
    };

    if (currentLayout?.id) {
        body.id = currentLayout.id;
    }

    try {
        const res = await fetch('layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (data.error) {
            showMessage(data.error, 'error');
            return;
        }
        currentLayout = { ...body, id: data.id, is_active: body.set_active };
        showMessage('Layout gespeichert.', 'success');
        loadLayoutList();
    } catch (e) {
        showMessage('Fehler beim Speichern.', 'error');
    }
}

async function activateLayout() {
    if (!currentLayout?.id) {
        showMessage('Zuerst speichern.', 'error');
        return;
    }
    try {
        const res = await fetch('layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'activate', csrf_token: CSRF_TOKEN, id: currentLayout.id }),
        });
        const data = await res.json();
        if (data.error) { showMessage(data.error, 'error'); return; }
        currentLayout.is_active = 1;
        showMessage('Layout aktiviert.', 'success');
        loadLayoutList();
    } catch (e) {
        showMessage('Fehler.', 'error');
    }
}

async function deleteLayout() {
    if (!currentLayout?.id) return;
    if (!confirm('Layout "' + currentLayout.name + '" wirklich löschen?')) return;
    try {
        const res = await fetch('layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', csrf_token: CSRF_TOKEN, id: currentLayout.id }),
        });
        const data = await res.json();
        if (data.error) { showMessage(data.error, 'error'); return; }
        newLayout();
        showMessage('Layout gelöscht.', 'success');
    } catch (e) {
        showMessage('Fehler.', 'error');
    }
}

function showExport() {
    if (!currentLayout) { showMessage('Kein Layout zum Exportieren.', 'error'); return; }
    const exportData = {
        name: currentLayout.name,
        cols: parseInt(document.getElementById('layout-cols').value) || 35,
        rows: parseInt(document.getElementById('layout-rows').value) || 30,
        grid_config: getGridConfig(),
        cells: gridCells,
        labels: currentLayout.labels || [],
    };
    document.getElementById('export-json').value = JSON.stringify(exportData, null, 2);
    document.getElementById('export-modal').classList.add('show');
}

function copyExport() {
    const textarea = document.getElementById('export-json');
    textarea.select();
    document.execCommand('copy');
    showMessage('In die Zwischenablage kopiert.', 'success');
}

function downloadExport() {
    const data = document.getElementById('export-json').value;
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (currentLayout?.name || 'layout') + '.json';
    a.click();
    URL.revokeObjectURL(url);
}

function showImport() {
    document.getElementById('import-json').value = '';
    document.getElementById('import-modal').classList.add('show');
}

function doImport() {
    try {
        const data = JSON.parse(document.getElementById('import-json').value);
        if (!data.name || !data.grid_config || !data.cells) {
            showMessage('Ungültiges JSON: name, grid_config, cells erforderlich.', 'error');
            return;
        }
        currentLayout = data;
        applyLayoutToUI();
        renderEditorGrid();
        closeModals();
        showMessage('Layout importiert. Bitte speichern.', 'success');
    } catch (e) {
        showMessage('JSON-Parse-Fehler: ' + e.message, 'error');
    }
}

function editCellLabel(key) {
    const data = gridCells[key];
    if (!data || data.type !== 'seat') return;
    const num = prompt('Sitznummer für ' + key + ':', data.seat_number || '');
    if (num !== null && num !== '') {
        data.seat_number = parseInt(num) || 0;
        renderEditorGrid();
    }
}

function closeModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    loadLayoutList();
    <?php if ($activeLayout): ?>
    loadLayout(<?= (int)$activeLayout['id'] ?>);
    <?php else: ?>
    renderEditorGrid();
    updateStatus();
    <?php endif; ?>
});

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', (e) => {
        if (e.target === m) closeModals();
    });
});
</script>
</body>
</html>
