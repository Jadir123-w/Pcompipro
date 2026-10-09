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
document.addEventListener('DOMContentLoaded', () => {
    const dateEl = document.getElementById('currentDate');
    if(dateEl) dateEl.textContent = new Date().toLocaleDateString();
    
    loadSettingsFromStorage();
    initCharts();
    setupEventListeners();
    fetchData(); 
    
    startDataSyncLoop();      
    startVisualUpdateLoop();  
});

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
        
        if(savedVal && savedUnit && savedTime) {
            appliedSettings = {
                val: parseFloat(savedVal),
                unit: savedUnit,
                week: savedWeek || 'week1', 
                startTime: parseInt(savedTime)
            };
            
            document.getElementById('cleanVal').value = savedVal;
            document.getElementById('cleanUnit').value = savedUnit;
            if(savedWeek) document.getElementById('weekFilter').value = savedWeek;
            
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
    
    if(isActive) {
        if(applyBtn) applyBtn.style.display = 'none'; 
        if(cancelBtn) cancelBtn.style.display = 'flex';
        if(toggleBtn) toggleBtn.style.display = 'flex'; 
    } else {
        if(applyBtn) {
            applyBtn.style.display = 'flex';
            applyBtn.classList.remove('disabled');
            applyBtn.disabled = false;
        }
        if(cancelBtn) cancelBtn.style.display = 'none';
        if(toggleBtn) {
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
    if(!btn) return;
    if(showHidden) {
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
        if(btn) { btn.classList.remove('disabled'); btn.disabled = false; }
    }
}

function updateStatusText() {
    const statusText = document.getElementById('cleanupStatusText');
    if(!statusText) return;

    if(!appliedSettings) {
        statusText.innerHTML = '<i class="fa-solid fa-power-off" style="color:#94a3b8"></i> Auto-eliminación desactivada.';
        statusText.style.color = "#94a3b8";
    } else {
        const time = calculateGlobalTimeLeft();
        let statusMsg = `<i class="fa-solid fa-trash-can" style="color:#ef4444"></i> Regla activa: Eliminando en <strong>${time.formatted}</strong>`;
        
        if(time.total <= 0) {
            if(showHidden) {
                statusMsg = `<i class="fa-solid fa-eye" style="color:#3b82f6"></i> Tiempo cumplido (Visualizando eliminados).`;
            } else {
                statusMsg = `<i class="fa-solid fa-check-circle" style="color:#22c55e"></i> Tiempo cumplido. Proyectos ocultos.`;
            }
        }
        statusText.innerHTML = statusMsg;
        statusText.style.color = "#0f172a";
    }
}

// ============================================================================
// 4. MATEMÁTICA GLOBAL
// ============================================================================

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
    const f = (n) => n < 10 ? '0'+n : n;
    if (days > 0) return `${days}d ${f(hours)}h ${f(minutes)}m`;
    return `${f(hours)}:${f(minutes)}:${f(seconds)}`;
}

// ============================================================================
// 5. BUCLES Y RELOJ AZUL
// ============================================================================

function startDataSyncLoop() {
    if(countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => { fetchData(); }, 30000); 
}

function startVisualUpdateLoop() {
    if(chartUpdateInterval) clearInterval(chartUpdateInterval);
    chartUpdateInterval = setInterval(() => {
        if(charts.progress) {
             charts.progress.update('none'); 
             updateWeekChart(); 
        }
        updateGlobalBlueClock(); 
        updateStatusText(); 
    }, 1000);
}

function updateGlobalBlueClock() {
    const timerBadge = document.getElementById('refreshTimer');
    if(!timerBadge) return;
    if(!appliedSettings || !globalData) {
        timerBadge.style.display = 'none';
        return;
    }
    timerBadge.style.display = 'inline-flex';
    
    const time = calculateGlobalTimeLeft();

    if(time.total > 0) {
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
        const res = await fetch('/logistica/dashboard/data');
        const data = await res.json();
        if(data.error) return; 
        globalData = data; 
        updateUI(data);
    } catch(e) { console.error(e); }
}

function updateUI(data) {
    updateKPI('kpi-activos', data.kpis.activos);
    updateKPI('kpi-entregados', data.kpis.entregados);
    updateKPI('kpi-reprocesos', data.kpis.reprocesos); 
    updateKPI('kpi-pendientes', data.kpis.pendientes); 
    handleAlert(data.has_reproceso, data.reproceso_projects);
    
    updateStatusChart(data); 
    updateChart('closing', data.closing, closingColorsMap);
    updateCriticalityChart(data.criticality); 
    updateWeekChart(); 
}

function updateStatusChart(data) {
    if (!charts.status) return;
    const labels = Object.keys(data.status);
    const order = ['En curso','Entregado','Cerrado','Reproceso'];
    labels.sort((a,b) => order.indexOf(a) - order.indexOf(b));
    
    const counts = labels.map(k => data.status[k]);
    const bgColors = labels.map(k => statusColorsMap[k] || '#cbd5e1'); 

    // Lista de proyectos
    const projectMap = {};
    Object.values(data.weeks).forEach(week => {
        week.forEach(p => {
            let status = 'En curso';
            if(p.progress === 100) status = 'Entregado'; 
            if(p.is_reproceso) status = 'Reproceso';
            if(!projectMap[status]) projectMap[status] = [];
            if(!projectMap[status].includes(p.name)) projectMap[status].push(p.name);
        });
    });
    const metas = labels.map(status => projectMap[status] || []);

    charts.status.data = {
        labels: labels,
        datasets: [{
            data: counts, 
            backgroundColor: bgColors,
            borderRadius: 6,
            barThickness: 50, 
            maxBarThickness: 60,
            customData: metas 
        }]
    };
    charts.status.update();
}

function updateChart(key, dataObj, colorMap) {
    if (!charts[key] || key === 'status') return; 
    const labels = Object.keys(dataObj);
    const counts = labels.map(k => dataObj[k].count || dataObj[k]);
    const metas = labels.map(k => dataObj[k].projects || []);
    const bgColors = labels.map(k => colorMap[k] || '#cbd5e1'); 

    charts[key].data = {
        labels: labels,
        datasets: [{
            data: counts, 
            backgroundColor: bgColors,
            borderRadius: 6,
            customData: metas 
        }]
    };
    charts[key].update();
}

function updateCriticalityChart(critData) {
    if (!charts.criticality) return;
    const labels = Object.keys(critData);
    const counts = labels.map(k => critData[k].count);
    const metas = labels.map(k => critData[k].projects || []);
    const colors = labels.map(l => {
        const k = l.toLowerCase();
        if(k.includes('muy urgente')) return '#ef4444';
        if(k.includes('urgente')) return '#9333ea';
        if(k.includes('importante')) return '#facc15';
        return '#3b82f6';
    });

    charts.criticality.data = {
        labels: labels,
        datasets: [{ 
            data: counts, 
            backgroundColor: colors, 
            borderWidth: 2, 
            borderColor: '#fff',
            customData: metas 
        }]
    };
    charts.criticality.update();
}

function updateWeekChart() {
    if(!globalData || !charts.progress) return;

    const currentWeekVal = document.getElementById('weekFilter').value;
    let weekData = globalData.weeks[currentWeekVal] || [];
    
    const globalTime = calculateGlobalTimeLeft();
    const isExpired = globalTime.total <= 0;

    const filteredData = weekData.filter(p => {
        if (!appliedSettings) return true;
        
        // CORRECCIÓN FINAL: Si es 100%, el tiempo manda (sea reproceso o no)
        if (p.progress === 100) {
            if (isExpired) return showHidden; 
            return true;
        }
        return true; 
    });

    charts.progress.data = {
        labels: filteredData.map(p => p.name), 
        datasets: [{
            label: '% Avance', 
            data: filteredData.map(p => p.progress),
            customData: filteredData, 
            backgroundColor: (ctx) => {
                const p = filteredData[ctx.dataIndex];
                if (p && p.is_reproceso) return '#ef4444'; 
                if (ctx.raw === 100) {
                    if (isExpired && showHidden) return '#94a3b8';
                    return '#22c55e';
                }
                return '#3b82f6';
            },
            borderRadius: 6, 
            barThickness: 30
        }]
    };
    charts.progress.update('none');
}

// ============================================================================
// 7. CHART CONFIG (VISUALIZACIÓN)
// ============================================================================

// --- CONFIG: TOOLTIP CON LISTA (PARA GRAF 1, 3 y 4) ---
const commonListTooltip = {
    enabled: true,
    backgroundColor: 'rgba(15, 23, 42, 0.98)',
    titleFont: { size: 14, weight: 'bold', family: "'Inter', sans-serif" },
    bodyFont: { size: 12, family: "'Inter', sans-serif" },
    padding: 12,
    cornerRadius: 8,
    callbacks: {
        label: (ctx) => {
            const val = ctx.raw;
            let sum = 0;
            ctx.chart.data.datasets[0].data.forEach(n => sum += n);
            const percent = sum > 0 ? ((val / sum) * 100).toFixed(1) : 0;
            return ` Cantidad: ${val} (${percent}%)`;
        },
        afterBody: (ctx) => {
            const dataset = ctx[0].dataset;
            const index = ctx[0].dataIndex;
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

// --- CONFIG: ETIQUETAS EXTERNAS DE CALIDAD (GRAF 3 y 4) ---
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
        // Salto de línea para mostrar Numero arriba y % abajo
        return val > 0 ? `${val}\n${percent}%` : '';
    },
    anchor: 'end', // Anclado al final del segmento
    align: 'end',  // Empujado hacia afuera
    offset: 8      // Separación del gráfico
};

function initCharts() {
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    const commonOptions = { 
        responsive: true, 
        maintainAspectRatio: false, 
        layout: { padding: 40 }, // MUCHO PADDING para que quepan las etiquetas externas
        plugins: { 
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
        }
    };

    // 1. STATUS
    if(document.getElementById('statusChart')) {
        charts.status = new Chart(document.getElementById('statusChart'), {
            type: 'bar', 
            data: {labels:[], datasets:[]},
            options: { 
                indexAxis: 'y', 
                responsive: true, maintainAspectRatio: false,
                layout: { padding: { right: 40 } }, 
                plugins: { 
                    legend: { display: false },
                    datalabels: {
                        color: '#0f172a', anchor: 'end', align: 'end',
                        font: { weight: 'bold', size: 13 },
                        formatter: (val) => val
                    },
                    tooltip: commonListTooltip
                },
                scales: { x: { display: false }, y: { grid: { display: false } } } 
            },
            plugins: [ChartDataLabels]
        });
    }

    // 2. CLOSING (Cierres) - ETIQUETAS EXTERNAS
    if(document.getElementById('closingChart')) {
        charts.closing = new Chart(document.getElementById('closingChart'), { 
            type: 'pie', 
            data: {labels:[], datasets:[]}, 
            options: {
                ...commonOptions,
                plugins: { 
                    ...commonOptions.plugins,
                    datalabels: circularDatalabels, // Configuración Externa
                    tooltip: commonListTooltip      // Lista de proyectos
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    // 3. CRITICIDAD (Dona) - ETIQUETAS EXTERNAS
    if(document.getElementById('criticalityChart')) {
        charts.criticality = new Chart(document.getElementById('criticalityChart'), { 
            type: 'doughnut', 
            data: {labels:[], datasets:[]}, 
            options: {
                ...commonOptions,
                plugins: { 
                    ...commonOptions.plugins,
                    datalabels: circularDatalabels, // Configuración Externa
                    tooltip: commonListTooltip      // Lista de proyectos
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    // 4. AVANCE (Sin cambios)
    if(document.getElementById('progressChart')) {
        charts.progress = new Chart(document.getElementById('progressChart'), {
            type: 'bar', data: {labels:[], datasets:[]},
            options: {
                responsive: true, maintainAspectRatio: false,
                layout: { padding: { top: 30 } },
                plugins: { 
                    legend: {display:false}, 
                    datalabels: { 
                        display: (ctx) => {
                            if (appliedSettings && ctx.dataset.data[ctx.dataIndex] === 100) return false;
                            return true;
                        },
                        color: '#fff', font: { weight:'bold' }, formatter: v => v > 5 ? v+'%' : ''
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: (ctx) => ctx[0].dataset.customData[ctx[0].dataIndex].fullName,
                            label: (ctx) => {
                                const p = ctx.dataset.customData[ctx.dataIndex];
                                let txt = `📊 Avance: ${p.progress}%`;
                                if(p.is_reproceso) txt += ' (⚠️ REPROCESO)';
                                return txt;
                            },
                            afterBody: (ctx) => {
                                const p = ctx[0].dataset.customData[ctx[0].dataIndex];
                                const lines = [];
                                lines.push(`📅 Inicio: ${p.date}`);
                                if(p.logistica) lines.push(`🚨 Prioridad: ${p.logistica}`);
                                if(p.progress === 100 && appliedSettings) {
                                    const time = calculateGlobalTimeLeft();
                                    if(time.total > 0) lines.push(`⏳ Ocultando en: ${time.formatted}`);
                                    else lines.push(`👁️ Visible por: Botón "Ver Eliminados"`);
                                }
                                return lines;
                            }
                        }
                    }
                },
                scales: { 
                    y: {max:100, beginAtZero:true, grid:{color:'#f1f5f9'}}, 
                    x: {
                        grid:{display:false},
                        ticks: {
                            maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 10 },
                            callback: function(val) {
                                const label = this.getLabelForValue(val);
                                return label.length > 15 ? label.substr(0, 15) + '...' : label;
                            }
                        }
                    } 
                }
            },
            plugins: [ChartDataLabels] 
        });
    }
}

// ============================================================================
// 8. PLUGIN VISUAL (ETIQUETAS SOBRE BARRAS)
// ============================================================================

const countdownPlugin = {
    id: 'countdownLabels',
    afterDatasetsDraw(chart, args, options) {
        if (!appliedSettings || chart.canvas.id !== 'progressChart') return;

        const { ctx } = chart;
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
                        if(globalTime.formatted.includes('d')) {
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
                    const rectX = bar.x - (textWidth / 2) - padding;
                    const rectY = bar.y + 5;
                    const rectW = textWidth + (padding * 2);
                    const rectH = 20;
                    
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                    ctx.strokeStyle = '#cbd5e1';
                    ctx.lineWidth = 1;
                    
                    ctx.beginPath();
                    ctx.roundRect(rectX, rectY, rectW, rectH, 4);
                    ctx.fill(); ctx.stroke();

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
Chart.register(countdownPlugin);

// ============================================================================
// 9. UTILS & LISTENERS
// ============================================================================

function updateKPI(id, val) { const el = document.getElementById(id); if(el && el.textContent != val) el.textContent = val; }

function handleAlert(hasReproceso, projects) {
    const card = document.getElementById('statusCard'); const badge = document.getElementById('alertBadge');
    if (hasReproceso) {
        if(card) card.classList.add('card-alert-blink'); if(badge) badge.style.display = 'flex';
        if (!isReprocesoActive) {
            isReprocesoActive = true; fillModalList(projects); showModal(); 
            if(alertInterval) clearInterval(alertInterval);
            alertInterval = setInterval(() => { if(isReprocesoActive) showModal(); }, 120000);
        }
    } else {
        if(card) card.classList.remove('card-alert-blink'); if(badge) badge.style.display = 'none';
        isReprocesoActive = false; if(alertInterval) clearInterval(alertInterval);
    }
}
function fillModalList(projects) {
    const list = document.getElementById('reprocesoList'); if(!list) return;
    list.innerHTML = (projects && projects.length) 
        ? projects.map(p => `<li style="display:flex; justify-content:space-between; padding:10px; border-bottom:1px solid #f1f5f9;"><strong style="font-size:13px;">${p.name}</strong><span class="tag critical" style="background:#fee2e2; color:#ef4444; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700;">Reproceso</span></li>`).join('')
        : '<li style="padding:10px;">Sin detalles</li>';
}
function showModal() { document.getElementById('alertModal')?.classList.add('show'); document.getElementById('alertSound')?.play().catch(e=>{}); }
function closeModal() { document.getElementById('alertModal')?.classList.remove('show'); }
function manualRefresh() {
    const btnIcon = document.querySelector('#manualRefreshBtn i'); if(btnIcon) btnIcon.classList.add('spinning');
    fetchData().then(() => { setTimeout(() => { if(btnIcon) btnIcon.classList.remove('spinning'); }, 500); });
}
function setupEventListeners() {
    const weekSelect = document.getElementById('weekFilter');
    if(weekSelect) {
        weekSelect.addEventListener('change', function() {
            updateWeekChart(); 
            if(!appliedSettings) enableApplyButton();
        });
    }
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if(toggle && sidebar) toggle.onclick = (e) => { e.stopPropagation(); sidebar.classList.toggle('active'); };
    document.onclick = (e) => { if(window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('active'); };
    const modal = document.getElementById('alertModal'); if(modal) modal.onclick = (e) => { if(e.target === modal) closeModal(); };
}