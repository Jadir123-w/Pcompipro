/**
 * ============================================================================
 * ARCHIVO: script.js (VERSIÓN DEFINITIVA - DATA LABELS EXTERNOS EN GRAF 3 Y 4)
 * 1. Gráfico Estado: Barras gruesas y Tooltips de lista.
 * 2. Gráfico Avance: Timer Global + Toggle.
 * 3. Gráficos 3 y 4: ETIQUETAS EXTERNAS (Cantidad + %) + Tooltip de Lista.
 * ============================================================================
 */

// --- VARIABLES GLOBALES ---
let globalData = null;
let charts = {};
let alertInterval = null;
let isReprocesoActive = false;

// ESTADO DE REGLAS
let appliedSettings = null;
let showHidden = false;

// INTERVALOS
let countdownInterval = null;
let chartUpdateInterval = null;

// --- COLORES ---
const statusColorsMap = {
    'En curso': '#fde047', 'Entregado': '#60a5fa',
    'Cerrado': '#22c55e', 'Reproceso': '#ef4444'
};
const closingColorsMap = {
    'EXCELENTE': '#3b82f6', 'BUENO': '#22c55e',
    'REGULAR': '#a855f7', 'PENDIENTE': '#f97316', 'REPROCESO': '#ff0000ff'
};

// ============================================================================
// 1. INICIALIZACIÓN
// ============================================================================
function initDashboard() {
    console.log('🚀 Iniciando Dashboard Logística...');
    try {
        const dateEl = document.getElementById('currentDate');
        if (dateEl) dateEl.textContent = new Date().toLocaleDateString();

        // Registrar plugin de manera segura
        registerCustomPlugins();

        loadSettingsFromStorage();
        initCharts();
        setupEventListeners();

        console.log('📡 Solicitando datos...');
        fetchData();

        startDataSyncLoop();
        startVisualUpdateLoop();
    } catch (error) {
        console.error('❌ Error fatal al inicializar dashboard:', error);
        hidePreloader();
    }
}

function registerCustomPlugins() {
    try {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js no está definido al registrar plugins.');
            return;
        }
        // Evitar registrar doble
        if (Chart.pluginService) {
            Chart.pluginService.register(countdownPlugin);
        } else if (Chart.register) {
            Chart.register(countdownPlugin);
        }
    } catch (e) {
        console.warn('Error registrando plugin:', e);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboard);
} else {
    // Si el script se carga dinámicamente y el DOM ya está listo (y Chart cargado)
    // Damos un pequeño respiro para asegurar que el stack actual termine
    setTimeout(initDashboard, 50);
}

// ============================================================================
// 2. LOGICA DE ALMACENAMIENTO
// ============================================================================

function loadSettingsFromStorage() {
    const savedActive = localStorage.getItem('dashboard_ruleActive');

    if (savedActive === 'true') {
        const savedVal = localStorage.getItem('dashboard_cleanVal');
        const savedUnit = localStorage.getItem('dashboard_cleanUnit');
        const savedWeek = localStorage.getItem('dashboard_week');
        const savedTime = localStorage.getItem('dashboard_startTime');

        if (savedVal && savedUnit && savedTime) {
            appliedSettings = {
                val: parseFloat(savedVal),
                unit: savedUnit,
                week: savedWeek || 'week1',
                startTime: parseInt(savedTime)
            };

            document.getElementById('cleanVal').value = savedVal;
            document.getElementById('cleanUnit').value = savedUnit;
            if (savedWeek) document.getElementById('weekFilter').value = savedWeek;

            updateButtonsState(true);
        }
    } else {
        appliedSettings = null;
        updateButtonsState(false);
    }
    updateStatusText();
}

function updateButtonsState(isActive) {
    const applyBtn = document.getElementById('applyBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const toggleBtn = document.getElementById('toggleBtn');

    if (isActive) {
        if (applyBtn) applyBtn.style.display = 'none';
        if (cancelBtn) cancelBtn.style.display = 'flex';
        if (toggleBtn) toggleBtn.style.display = 'flex';
    } else {
        if (applyBtn) {
            applyBtn.style.display = 'flex';
            // Start disabled until user triggers enableApplyButton() via input change
            applyBtn.classList.add('disabled');
            applyBtn.disabled = true;
        }
        if (cancelBtn) cancelBtn.style.display = 'none';
        if (toggleBtn) {
            toggleBtn.style.display = 'none';
            showHidden = false;
            updateToggleVisuals();
        }
    }
}

// ============================================================================
// 3. ACCIONES DE USUARIO
// ============================================================================

function applyCleanupSettings() {
    const valInput = document.getElementById('cleanVal').value;
    const unitInput = document.getElementById('cleanUnit').value;
    const weekInput = document.getElementById('weekFilter').value;
    const now = Date.now();

    appliedSettings = {
        val: parseFloat(valInput),
        unit: unitInput,
        week: weekInput,
        startTime: now
    };

    localStorage.setItem('dashboard_ruleActive', 'true');
    localStorage.setItem('dashboard_cleanVal', appliedSettings.val);
    localStorage.setItem('dashboard_cleanUnit', appliedSettings.unit);
    localStorage.setItem('dashboard_week', appliedSettings.week);
    localStorage.setItem('dashboard_startTime', now);

    showHidden = false;
    updateToggleVisuals();

    updateWeekChart();
    updateButtonsState(true);
    updateStatusText();
    updateGlobalBlueClock();
}

function cancelCleanupSettings() {
    appliedSettings = null;
    localStorage.setItem('dashboard_ruleActive', 'false');
    localStorage.removeItem('dashboard_startTime');

    updateWeekChart();
    updateButtonsState(false);
    updateStatusText();
    updateGlobalBlueClock();
}

function toggleHiddenProjects() {
    showHidden = !showHidden;
    updateToggleVisuals();
    updateWeekChart();
    updateStatusText();
}

function updateToggleVisuals() {
    const btn = document.getElementById('toggleBtn');
    if (!btn) return;
    if (showHidden) {
        btn.innerHTML = '<i class="fa-regular fa-eye-slash"></i> Ocultar Eliminados';
        btn.classList.add('active');
    } else {
        btn.innerHTML = '<i class="fa-regular fa-eye"></i> Ver Eliminados';
        btn.classList.remove('active');
    }
}

function enableApplyButton() {
    if (!appliedSettings) {
        const btn = document.getElementById('applyBtn');
        if (btn) { btn.classList.remove('disabled'); btn.disabled = false; }
    }
}

function updateStatusText() {
    const statusText = document.getElementById('cleanupStatusText');
    if (!statusText) return;

    if (!appliedSettings) {
        const msg = '<i class="fa-solid fa-power-off" style="color:#94a3b8"></i> Auto-eliminación desactivada.';
        if (statusText.innerHTML !== msg) {
            statusText.innerHTML = msg;
            statusText.style.color = "#94a3b8";
        }
    } else {
        const time = calculateGlobalTimeLeft();
        let statusMsg = `<i class="fa-solid fa-trash-can" style="color:#ef4444"></i> Regla activa: Eliminando en <strong>${time.formatted}</strong>`;

        if (time.total <= 0) {
            if (showHidden) {
                statusMsg = `<i class="fa-solid fa-eye" style="color:#3b82f6"></i> Tiempo cumplido (Visualizando eliminados).`;
            } else {
                statusMsg = `<i class="fa-solid fa-check-circle" style="color:#22c55e"></i> Tiempo cumplido. Proyectos ocultos.`;
            }
        }

        // Optimización: Solo tocar el DOM si el texto cambió
        if (statusText.innerHTML !== statusMsg) {
            statusText.innerHTML = statusMsg;
            statusText.style.color = "#0f172a";
        }
    }
}

// ============================================================================
// 3. LISTENERS Y ACCIONES
// ============================================================================

function setupEventListeners() {
    const weekSelect = document.getElementById('weekFilter');
    if (weekSelect) {
        weekSelect.addEventListener('change', () => {
            updateWeekChart();
            enableApplyButton();
        });
    }

    const valInput = document.getElementById('cleanVal');
    const unitSelect = document.getElementById('cleanUnit');

    if (valInput) valInput.addEventListener('input', enableApplyButton);
    if (unitSelect) unitSelect.addEventListener('change', enableApplyButton);
}


function calculateGlobalTimeLeft() {
    if (!appliedSettings) return { total: 0, formatted: "" };
    const now = Date.now();
    let multiplier = 1000 * 60;
    if (appliedSettings.unit === 'hours') multiplier = 1000 * 60 * 60;
    if (appliedSettings.unit === 'days') multiplier = 1000 * 60 * 60 * 24;

    const expirationTime = appliedSettings.startTime + (appliedSettings.val * multiplier);
    const diff = expirationTime - now;

    return { total: diff, formatted: formatMsToTime(diff) };
}

function formatMsToTime(diff) {
    if (diff <= 0) return "00:00:00";
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    const f = (n) => n < 10 ? '0' + n : n;
    if (days > 0) return `${days}d ${f(hours)}h ${f(minutes)}m`;
    return `${f(hours)}:${f(minutes)}:${f(seconds)}`;
}

// ============================================================================
// 5. BUCLES Y RELOJ AZUL
// ============================================================================

function startDataSyncLoop() {
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => { fetchData(); }, 30000);
}

function startVisualUpdateLoop() {
    if (chartUpdateInterval) clearInterval(chartUpdateInterval);

    let wasExpired = false; // Estado anterior para detectar cambios

    chartUpdateInterval = setInterval(() => {
        // Sólo actualizar textos y reloj cada segundo
        updateGlobalBlueClock();
        updateStatusText();

        // Detectar transición de "No Expirado" a "Expirado" para actualizar gráfico UNO sola vez
        if (appliedSettings) {
            const time = calculateGlobalTimeLeft();
            const isExpired = time.total <= 0;

            if (isExpired !== wasExpired) {
                updateWeekChart(); // Actualizar gráfico solo cuando cambia el estado de expiración
                wasExpired = isExpired;
            }
        }
    }, 1000);
}

function updateGlobalBlueClock() {
    const timerBadge = document.getElementById('refreshTimer');
    if (!timerBadge) return;
    if (!appliedSettings || !globalData) {
        timerBadge.style.display = 'none';
        return;
    }
    timerBadge.style.display = 'inline-flex';

    const time = calculateGlobalTimeLeft();

    if (time.total > 0) {
        timerBadge.innerHTML = `<i class="fa-solid fa-clock" style="margin-right:6px;"></i> Limpieza en: <strong>${time.formatted}</strong>`;
        if (time.total < 3600000) {
            timerBadge.style.color = '#dc2626'; timerBadge.style.backgroundColor = '#fef2f2'; timerBadge.style.borderColor = '#fecaca';
        } else {
            timerBadge.style.color = '#2563eb'; timerBadge.style.backgroundColor = '#eff6ff'; timerBadge.style.borderColor = '#dbeafe';
        }
    } else {
        timerBadge.innerHTML = `<i class="fa-solid fa-check-circle" style="margin-right:6px;"></i> Limpieza completada`;
        timerBadge.style.color = '#059669'; timerBadge.style.backgroundColor = '#ecfdf5'; timerBadge.style.borderColor = '#a7f3d0';
    }
}

// ============================================================================
// 6. GESTIÓN DE DATOS Y GRÁFICO
// ============================================================================

async function fetchData() {
    try {
        console.log('🔄 Fetching data from /logistica/dashboard/data...');
        const res = await fetch('/logistica/dashboard/data', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        console.log('Estado HTTP:', res.status, res.statusText);

        if (!res.ok) {
            const errText = `Error HTTP ${res.status}`;
            console.warn(errText);

            hidePreloader();
            return;
        }

        const data = await res.json();
        console.log('📦 Datos recibidos (JSON):', data);

        if (data.error) {
            console.warn('Error en los datos:', data.error);
            hidePreloader();
            return;
        }

        globalData = data;
        console.log('▶️ updateUI iniciándose...');
        updateUI(data);
        console.log('✅ updateUI completado.');
        hidePreloader();
    } catch (e) {
        console.warn('No se pudo conectar con el servidor del dashboard:', e.message);
        hidePreloader();
    }
}

function hidePreloader() {
    const preloader = document.getElementById('dashboardPreloader');
    if (preloader) {
        preloader.classList.add('hidden');
        // Remover del DOM después de la animación
        setTimeout(() => {
            if (preloader.parentNode) {
                preloader.remove();
            }
        }, 400); // Tiempo de la transición CSS
    }
}

function updateUI(data) {
    console.log('📊 Actualizando KPIs...');
    updateKPI('kpi-activos', data.kpis.activos);
    updateKPI('kpi-entregados', data.kpis.entregados);
    updateKPI('kpi-reprocesos', data.kpis.reprocesos);
    updateKPI('kpi-pendientes', data.kpis.pendientes);
    handleAlert(data.has_reproceso, data.reproceso_projects);

    console.log('📊 Actualizando gráficas...');
    updateStatusChart(data);
    updateChart('closing', data.closing, closingColorsMap);
    updateCriticalityChart(data.criticality);
    updateWeekChart();
}

function updateStatusChart(data) {
    if (!charts.status) {
        console.warn('⚠️ updateStatusChart: Abortando porque charts.status no está definido');
        return;
    }
    console.log('🔄 Actualizando statusChart con datos:', data.status);
    const labels = Object.keys(data.status);
    const order = ['En curso', 'Entregado', 'Cerrado', 'Reproceso'];
    labels.sort((a, b) => order.indexOf(a) - order.indexOf(b));

    const counts = labels.map(k => data.status[k]);
    const bgColors = labels.map(k => statusColorsMap[k] || '#cbd5e1');

    // Lista de proyectos
    const projectMap = {};
    Object.values(data.weeks).forEach(week => {
        week.forEach(p => {
            let status = 'En curso';
            if (p.progress === 100) status = 'Entregado';
            if (p.is_reproceso) status = 'Reproceso';
            if (!projectMap[status]) projectMap[status] = [];
            if (!projectMap[status].includes(p.name)) projectMap[status].push(p.name);
        });
    });
    const metas = labels.map(status => projectMap[status] || []);

    // FIX: Mutar propiedades en lugar de reemplazar objeto para Chart.js 2.x
    charts.status.data.labels = labels;
    charts.status.data.datasets = [{
        data: counts,
        backgroundColor: bgColors,
        // borderRadius ignorado en v2, pero no rompe
        barThickness: 50,
        maxBarThickness: 60,
        customData: metas
    }];
    charts.status.update();
}

function updateChart(key, dataObj, colorMap) {
    if (!charts[key]) return;
    if (key === 'status') return;

    console.log(`🔄 Actualizando gráfico ${key}...`, dataObj);

    const labels = Object.keys(dataObj);
    const counts = labels.map(k => dataObj[k].count || dataObj[k]);
    const metas = labels.map(k => dataObj[k].projects || []);
    const bgColors = labels.map(k => colorMap[k] || '#cbd5e1');

    // FIX: Mutar propiedades
    charts[key].data.labels = labels;
    charts[key].data.datasets = [{
        data: counts,
        backgroundColor: bgColors,
        customData: metas
    }];

    try {
        charts[key].update();
        console.log(`✅ Gráfico ${key} actualizado con éxito.`);
    } catch (e) {
        console.error(`❌ Error al actualizar ${key}:`, e);
    }
}

function updateCriticalityChart(critData) {
    if (!charts.criticality) return;
    console.log('🔄 Actualizando Criticality Chart...', critData);

    const labels = Object.keys(critData);
    const counts = labels.map(k => critData[k].count);
    const metas = labels.map(k => critData[k].projects || []);
    const colors = labels.map(l => {
        const k = l.toLowerCase();
        if (k.includes('muy urgente')) return '#ef4444';
        if (k.includes('urgente')) return '#9333ea';
        if (k.includes('importante')) return '#facc15';
        return '#3b82f6';
    });

    // FIX: Mutar propiedades
    charts.criticality.data.labels = labels;
    charts.criticality.data.datasets = [{
        data: counts,
        backgroundColor: colors,
        borderWidth: 2,
        borderColor: '#fff',
        customData: metas
    }];

    try {
        charts.criticality.update();
        console.log('✅ Criticality Chart actualizado.');
    } catch (e) {
        console.error('❌ Error updating Criticality:', e);
    }
}

function updateWeekChart() {
    if (!globalData || !charts.progress) return;

    const currentWeekVal = document.getElementById('weekFilter').value;
    let weekData = globalData.weeks[currentWeekVal] || [];
    const globalTime = calculateGlobalTimeLeft();
    const isExpired = globalTime.total <= 0;

    const filteredData = weekData.filter(p => {
        if (!appliedSettings) return true;
        if (p.progress === 100) {
            if (isExpired) return showHidden;
            return true;
        }
        return true;
    });

    // FIX: Mutar propiedades
    charts.progress.data.labels = filteredData.map(p => p.name);
    charts.progress.data.datasets = [{
        label: '% Avance',
        data: filteredData.map(p => p.progress),
        customData: filteredData,
        backgroundColor: (ctx) => {
            // Chart.js 2.x context puede ser diferente, validamos
            if (!ctx) return '#3b82f6';
            // En v2.x scriptable options reciben (context), donde context.dataIndex existe?
            // A veces en v2 es: function(context) { var index = context.dataIndex; ... }
            // Vamos a simplificar asumiendo que el chart re-render funciona
            const index = ctx.dataIndex;
            if (index === undefined) return '#3b82f6';

            const p = filteredData[index];
            if (p && p.is_reproceso) return '#ef4444';
            if (ctx.dataset.data[index] === 100) {
                if (isExpired && showHidden) return '#94a3b8';
                return '#22c55e';
            }
            return '#3b82f6';
        },
        borderWidth: 0,
        barThickness: 30
    }];

    // FIX: Chart.js 2.x no soporta 'none' como modo de update.
    // Usar .update() normal o .update(0) para sin animación
    charts.progress.update();
    console.log(`✅ Gráfico Avance actualizado para: ${currentWeekVal}`);
}

// ============================================================================
// 7. CHART CONFIG (VISUALIZACIÓN)
// ============================================================================

// --- CONFIG: TOOLTIP CON LISTA (PARA GRAF 1, 3 y 4) - Chart.js 2.x ---
const commonListTooltip = {
    enabled: true,
    backgroundColor: 'rgba(15, 23, 42, 0.98)',
    titleFontSize: 14,
    titleFontStyle: 'bold',
    titleFontFamily: "'Inter', sans-serif",
    bodyFontSize: 12,
    bodyFontFamily: "'Inter', sans-serif",
    xPadding: 12,
    yPadding: 12,
    cornerRadius: 8,
    callbacks: {
        label: (tooltipItem, data) => {
            const val = tooltipItem.yLabel || tooltipItem.value;
            let sum = 0;
            data.datasets[tooltipItem.datasetIndex].data.forEach(n => sum += n);
            const percent = sum > 0 ? ((val / sum) * 100).toFixed(1) : 0;
            return ` Cantidad: ${val} (${percent}%)`;
        },
        afterBody: (tooltipItems, data) => {
            const item = tooltipItems[0];
            const dataset = data.datasets[item.datasetIndex];
            const index = item.index;
            const projects = dataset.customData ? dataset.customData[index] : [];

            if (!projects || projects.length === 0) return [];

            const maxLines = 5;
            const displayList = projects.slice(0, maxLines);
            const remaining = projects.length - maxLines;

            let lines = ["", "📋 Proyectos:"];
            displayList.forEach(p => lines.push(`• ${p}`));
            if (remaining > 0) lines.push(`... y ${remaining} más.`);
            return lines;
        }
    }
};

// --- CONFIG: ETIQUETAS EXTERNAS DE CALIDAD (GRAF 3 y 4) - Chart.js 2.x ---
const circularDatalabels = {
    color: '#1e293b', // Texto oscuro para que se vea fuera
    backgroundColor: '#ffffff', // Fondo blanco
    borderColor: '#e2e8f0',
    borderWidth: 1,
    borderRadius: 4,
    font: { weight: 'bold', size: 11 },
    padding: 6,
    formatter: (val, ctx) => {
        let sum = 0;
        ctx.chart.data.datasets[0].data.forEach(n => sum += n);
        const percent = sum > 0 ? ((val / sum) * 100).toFixed(0) : 0;
        // En Chart.js 2.x chartjs-plugin-datalabels 0.7.0, salto de línea funciona igual
        return val > 0 ? `${val}\n${percent}%` : '';
    },
    anchor: 'end', // Anclado al final del segmento
    align: 'end',  // Empujado hacia afuera
    offset: 8      // Separación del gráfico
};

function initCharts() {
    console.log('🎨 Inicializando gráficos...');
    // Chart.js 2.x usa 'global' en lugar de 'defaults'
    if (Chart.defaults.global) {
        Chart.defaults.global.defaultFontFamily = "'Inter', 'Segoe UI', sans-serif";
    }
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: 40 }, // MUCHO PADDING para que quepan las etiquetas externas
        legend: {
            position: 'bottom',
            labels: { usePointStyle: true, padding: 20 }
        }
    };

    // 1. STATUS (Chart.js 2.x: usar 'horizontal' en lugar de indexAxis: 'y')
    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        console.log('✅ Canvas #statusChart encontrado. Creando instancia Chart...');
        charts.status = new Chart(statusCanvas, {
            type: 'horizontalBar', // Chart.js 2.x usa 'horizontalBar' en lugar de indexAxis
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 40 } },
                legend: { display: false },
                tooltips: commonListTooltip, // Chart.js 2.x usa 'tooltips' en lugar de 'tooltip'
                plugins: {
                    datalabels: {
                        color: '#0f172a',
                        anchor: 'end',
                        align: 'end',
                        font: { weight: 'bold', size: 13 },
                        formatter: (val) => val
                    }
                },
                scales: {
                    xAxes: [{ display: false }],
                    yAxes: [{ gridLines: { display: false } }]
                }
            }
        });
        console.log('✅ Chart status creado:', charts.status);
    } else {
        console.error('❌ FATAL: Elemento #statusChart NO encontrado en el DOM.');
    }

    // 2. CLOSING (Cierres) - ETIQUETAS EXTERNAS
    const closingCanvas = document.getElementById('closingChart');
    if (closingCanvas) {
        console.log('✅ Canvas #closingChart encontrado. Creando instancia Chart...');
        charts.closing = new Chart(closingCanvas, {
            type: 'pie',
            data: { labels: [], datasets: [] },
            options: {
                responsive: commonOptions.responsive,
                maintainAspectRatio: commonOptions.maintainAspectRatio,
                layout: commonOptions.layout,
                legend: commonOptions.legend,
                tooltips: commonListTooltip, // Chart.js 2.x usa 'tooltips'
                plugins: {
                    datalabels: circularDatalabels // Configuración Externa
                }
            }
        });
        console.log('✅ Chart closing creado:', charts.closing);
    } else {
        console.warn('⚠️ Canvas #closingChart no encontrado');
    }

    // 3. CRITICIDAD (Dona) - ETIQUETAS EXTERNAS
    const criticalityCanvas = document.getElementById('criticalityChart');
    if (criticalityCanvas) {
        console.log('✅ Canvas #criticalityChart encontrado. Creando instancia Chart...');
        charts.criticality = new Chart(criticalityCanvas, {
            type: 'doughnut',
            data: { labels: [], datasets: [] },
            options: {
                responsive: commonOptions.responsive,
                maintainAspectRatio: commonOptions.maintainAspectRatio,
                layout: commonOptions.layout,
                legend: commonOptions.legend,
                tooltips: commonListTooltip, // Chart.js 2.x usa 'tooltips'
                plugins: {
                    datalabels: circularDatalabels // Configuración Externa
                }
            }
        });
        console.log('✅ Chart criticality creado:', charts.criticality);
    } else {
        console.warn('⚠️ Canvas #criticalityChart no encontrado');
    }

    // 4. AVANCE (Adaptado para Chart.js 2.x)
    if (document.getElementById('progressChart')) {
        charts.progress = new Chart(document.getElementById('progressChart'), {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 30 } },
                legend: { display: false },
                tooltips: { // Chart.js 2.x usa 'tooltips'
                    enabled: true,
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleFontSize: 13,
                    titleFontStyle: 'bold',
                    bodyFontSize: 12,
                    xPadding: 12,
                    yPadding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        title: (tooltipItems) => {
                            const item = tooltipItems[0];
                            const chart = charts.progress;
                            return chart.data.datasets[item.datasetIndex].customData[item.index].fullName;
                        },
                        label: (tooltipItem) => {
                            const chart = charts.progress;
                            const p = chart.data.datasets[tooltipItem.datasetIndex].customData[tooltipItem.index];
                            let txt = `📊 Avance: ${p.progress}%`;
                            if (p.is_reproceso) txt += ' (⚠️ REPROCESO)';
                            return txt;
                        },
                        afterBody: (tooltipItems) => {
                            const item = tooltipItems[0];
                            const chart = charts.progress;
                            const p = chart.data.datasets[item.datasetIndex].customData[item.index];
                            const lines = [];
                            lines.push(`📅 Inicio: ${p.date}`);
                            if (p.logistica) lines.push(`🚨 Prioridad: ${p.logistica}`);
                            if (p.progress === 100 && appliedSettings) {
                                const time = calculateGlobalTimeLeft();
                                if (time.total > 0) lines.push(`⏳ Ocultando en: ${time.formatted}`);
                                else lines.push(`👁️ Visible por: Botón "Ver Eliminados"`);
                            }
                            return lines;
                        }
                    }
                },
                plugins: {
                    datalabels: {
                        display: (ctx) => {
                            if (appliedSettings && ctx.dataset.data[ctx.dataIndex] === 100) return false;
                            return true;
                        },
                        color: '#fff',
                        font: { weight: 'bold' },
                        formatter: v => v > 5 ? v + '%' : ''
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: { max: 100, beginAtZero: true },
                        gridLines: { color: '#f1f5f9' }
                    }],
                    xAxes: [{
                        gridLines: { display: false },
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: false,
                            fontSize: 10,
                            callback: function (value, index) {
                                const label = this.getLabelForValue ? this.getLabelForValue(value) : this.chart.data.labels[index];
                                return label && label.length > 15 ? label.substr(0, 15) + '...' : label;
                            }
                        }
                    }]
                }
            }
        });
    }
}

// ============================================================================
// 8. PLUGIN VISUAL (ETIQUETAS SOBRE BARRAS)
// ============================================================================

const countdownPlugin = {
    id: 'countdownLabels',
    afterDatasetsDraw: function (chart, easing) { // Chart.js 2.x usa función en lugar de método
        if (!appliedSettings || chart.canvas.id !== 'progressChart') return;

        const ctx = chart.ctx; // Chart.js 2.x: ctx está en chart.ctx
        const globalTime = calculateGlobalTimeLeft();
        const isExpired = globalTime.total <= 0;

        chart.data.datasets.forEach((dataset, i) => {
            const meta = chart.getDatasetMeta(i);
            meta.data.forEach((bar, index) => {
                const value = dataset.data[index];
                if (value === 100) {
                    let text = "";
                    if (!isExpired) {
                        text = `⏱ ${globalTime.formatted}`;
                        if (globalTime.formatted.includes('d')) {
                            const parts = globalTime.formatted.split(' ');
                            text = `⏱ ${parts[0]} ${parts[1]}`;
                        }
                    } else if (showHidden) {
                        text = "AGOTADO";
                    } else {
                        return;
                    }

                    ctx.font = 'bold 10px "Inter", sans-serif';
                    const textWidth = ctx.measureText(text).width;
                    const padding = 6;
                    const rectX = bar._model.x - (textWidth / 2) - padding; // Chart.js 2.x usa _model
                    const rectY = bar._model.y + 5;
                    const rectW = textWidth + (padding * 2);
                    const rectH = 20;

                    ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                    ctx.strokeStyle = '#cbd5e1';
                    ctx.lineWidth = 1;

                    ctx.beginPath();
                    // Chart.js 2.x no tiene roundRect, usar rect o implementar manualmente
                    // Implementación simple de roundRect
                    const radius = 4;
                    ctx.moveTo(rectX + radius, rectY);
                    ctx.lineTo(rectX + rectW - radius, rectY);
                    ctx.quadraticCurveTo(rectX + rectW, rectY, rectX + rectW, rectY + radius);
                    ctx.lineTo(rectX + rectW, rectY + rectH - radius);
                    ctx.quadraticCurveTo(rectX + rectW, rectY + rectH, rectX + rectW - radius, rectY + rectH);
                    ctx.lineTo(rectX + radius, rectY + rectH);
                    ctx.quadraticCurveTo(rectX, rectY + rectH, rectX, rectY + rectH - radius);
                    ctx.lineTo(rectX, rectY + radius);
                    ctx.quadraticCurveTo(rectX, rectY, rectX + radius, rectY);
                    ctx.closePath();
                    ctx.fill();
                    ctx.stroke();

                    if (!isExpired) {
                        ctx.fillStyle = (globalTime.total < 3600000) ? '#ef4444' : '#0f172a';
                    } else {
                        ctx.fillStyle = '#64748b';
                    }
                    ctx.fillText(text, rectX + padding, rectY + 14);
                }
            });
        });
    }
};
// Chart.js 2.x: registrar plugin usando Chart.pluginService
// MOVIDO A registerCustomPlugins() para mayor seguridad
// if (Chart.pluginService) {
//     Chart.pluginService.register(countdownPlugin);
// } else {
//     Chart.register(countdownPlugin);
// }

// ============================================================================
// 9. UTILS & LISTENERS
// ============================================================================

function updateKPI(id, val) { const el = document.getElementById(id); if (el && el.textContent != val) el.textContent = val; }

function handleAlert(hasReproceso, projects) {
    const card = document.getElementById('statusCard'); const badge = document.getElementById('alertBadge');
    if (hasReproceso) {
        if (card) card.classList.add('card-alert-blink'); if (badge) badge.style.display = 'flex';
        if (!isReprocesoActive) {
            isReprocesoActive = true; fillModalList(projects); showModal();
            if (alertInterval) clearInterval(alertInterval);
            alertInterval = setInterval(() => { if (isReprocesoActive) showModal(); }, 120000);
        }
    } else {
        if (card) card.classList.remove('card-alert-blink'); if (badge) badge.style.display = 'none';
        isReprocesoActive = false; if (alertInterval) clearInterval(alertInterval);
    }
}
function fillModalList(projects) {
    const list = document.getElementById('reprocesoList'); if (!list) return;
    list.innerHTML = (projects && projects.length)
        ? projects.map(p => `<li style="display:flex; justify-content:space-between; padding:10px; border-bottom:1px solid #f1f5f9;"><strong style="font-size:13px;">${p.name}</strong><span class="tag critical" style="background:#fee2e2; color:#ef4444; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700;">Reproceso</span></li>`).join('')
        : '<li style="padding:10px;">Sin detalles</li>';
}
function showModal() { document.getElementById('alertModal')?.classList.add('show'); document.getElementById('alertSound')?.play().catch(e => { }); }
function closeModal() { document.getElementById('alertModal')?.classList.remove('show'); }
function manualRefresh() {
    const btnIcon = document.querySelector('#manualRefreshBtn i'); if (btnIcon) btnIcon.classList.add('spinning');
    fetchData().then(() => { setTimeout(() => { if (btnIcon) btnIcon.classList.remove('spinning'); }, 500); });
}
function setupEventListeners() {
    const weekSelect = document.getElementById('weekFilter');
    if (weekSelect) {
        weekSelect.addEventListener('change', function () {
            updateWeekChart();
            if (!appliedSettings) enableApplyButton();
        });
    }
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (toggle && sidebar) toggle.onclick = (e) => { e.stopPropagation(); sidebar.classList.toggle('active'); };
    document.onclick = (e) => { if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('active'); };
    const modal = document.getElementById('alertModal'); if (modal) modal.onclick = (e) => { if (e.target === modal) closeModal(); };
}