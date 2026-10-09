@extends('tenant.layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('logistica-dashboard/css/style.css') }}">
@endpush    

@section('content')
    <div class="dashboard">
        {{-- Preloader (solo dentro del dashboard) --}}
        <div id="dashboardPreloader" class="dashboard-preloader">
            <div class="preloader-content">
                <div class="spinner-container">
                    <div class="spinner"></div>
                </div>
                <p class="loading-text">Cargando Dashboard de Logística...</p>
                <p class="loading-subtext">Obteniendo datos del servidor</p>
            </div>
        </div>

        <header class="page-header">
            <h2>Dashboard Logística</h2>
        </header>
        
        <div>
            <div class="card mb-0">

                <audio id="alertSound" src="{{ asset('logistica-dashboard/media/notificacion.mp3') }}" preload="auto"></audio>

                <main class="main-content">
                        
                    <div class="dashboard-content">
                        
                        <div class="kpi-grid">
                            <div class="kpi-card blue">
                                <div class="kpi-header"><span>Proyectos Activos</span><i class="fas fa-briefcase"></i></div>
                                <div class="kpi-value" id="kpi-activos">--</div>
                                <div class="kpi-trend">En curso actualmente</div>
                            </div>
                            <div class="kpi-card green">
                                <div class="kpi-header"><span>Entregados</span><i class="fas fa-check-circle"></i></div>
                                <div class="kpi-value" id="kpi-entregados">--</div>
                                <div class="kpi-trend">Acumulado Mes</div>
                            </div>
                            <div class="kpi-card red">
                                <div class="kpi-header"><span>En Reproceso</span><i class="fas fa-undo"></i></div>
                                <div class="kpi-value" id="kpi-reprocesos">--</div>
                                <div class="kpi-trend">Proyectos devueltos</div>
                            </div>
                            <div class="kpi-card orange">
                                <div class="kpi-header"><span>Cierres Pendientes</span><i class="far fa-clock"></i></div>
                                <div class="kpi-value" id="kpi-pendientes">--</div>
                                <div class="kpi-trend">Falta documentación</div>
                            </div>
                        </div>

                        <div class="charts-grid">
                            
                            <div class="chart-card" id="statusCard">
                                <div class="chart-header">
                                    <div><h3>Estado de Proyectos</h3><p>Distribución actual</p></div>
                                    <a href="https://www.notion.so/PROYECTOS-2a7fbb813b4c816dbe61c83d87d587e6" target="_blank" class="btn-notion-small">
                                        Ver detalles <i class="fas fa-long-arrow-alt-right"></i>
                                    </a>
                                </div>
                                <div class="chart-container"><canvas id="statusChart"></canvas></div>
                            </div>

                            <div class="chart-card">
                                <div class="chart-header" style="flex-direction: column; align-items: flex-start; gap: 15px;">
                                    
                                    <div style="width: 100%; display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <?php
                                                // Mes en Español
                                                $meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
                                                $mesActual = $meses[date('n') - 1];
                                            ?>
                                            <h3>Avance (<?php echo $mesActual; ?>)</h3>
                                            <p>Top 5 Urgentes</p>
                                        </div>
                                        <a href="https://www.notion.so/2a7fbb813b4c8155a36ecb9cb4a94e78" target="_blank" class="btn-notion-small icon-only" title="Ver tabla completa">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>

                                    <div class="control-panel-container">
                                        
                                        <div class="control-group">
                                            <label for="weekFilter">Periodo:</label>
                                            <select id="weekFilter" class="control-input">
                                                <option value="week1">Semana 1</option>
                                                <option value="week2">Semana 2</option>
                                                <option value="week3">Semana 3</option>
                                                <option value="week4">Semana 4</option>
                                                <option value="week5">Semana 5</option> 
                                            </select>
                                        </div>

                                        <div class="control-group">
                                            <label for="cleanVal">Eliminar completados tras:</label>
                                            <div class="input-row">
                                                <input type="number" id="cleanVal" value="24" min="1" oninput="enableApplyButton()" class="control-input small">
                                                <select id="cleanUnit" onchange="enableApplyButton()" class="control-input medium">
                                                    <option value="minutes">Minutos</option>
                                                    <option value="hours" selected>Horas</option>
                                                    <option value="days">Días</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="control-actions">
                                            <button id="applyBtn" class="btn-apply disabled" onclick="applyCleanupSettings()" disabled>
                                                <i class="fas fa-check"></i> Aplicar Regla
                                            </button>

                                            <button id="toggleBtn" class="btn-toggle" onclick="toggleHiddenProjects()" style="display: none;">
                                                <i class="fas fa-eye"></i> Ver Eliminados
                                            </button>

                                            <button id="cancelBtn" class="btn-cancel" onclick="cancelCleanupSettings()" style="display: none;">
                                                <i class="fas fa-ban"></i> Cancelar
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="status-message-bar">
                                        <span id="cleanupStatusText">
                                            <i class="fal fa-power-off"></i> Auto-eliminación desactivada.
                                        </span>
                                        <span id="refreshTimer" class="timer-badge" style="display:none;">
                                            <i class="far fa-clock" style="margin-right:6px;"></i>
                                            Próx. validación: --:--:--
                                        </span>
                                    </div>

                                </div>
                                <div class="chart-container"><canvas id="progressChart"></canvas></div>
                            </div>

                            <div class="chart-card">
                                <div class="chart-header">
                                    <div><h3>Criticidad</h3><p>Nivel de urgencia</p></div>
                                    <a href="https://www.notion.so/2a7fbb813b4c81cf97fbee560735628b" target="_blank" class="btn-notion-small">
                                        Ver detalles <i class="fas fa-long-arrow-alt-right"></i>
                                    </a>
                                </div>
                                <div class="chart-container"><canvas id="criticalityChart"></canvas></div>
                            </div>

                            <div class="chart-card">
                                <div class="chart-header">
                                    <div><h3>Cierres</h3><p>Calidad de entrega</p></div>
                                    <a href="https://www.notion.so/2a7fbb813b4c8155a36ecb9cb4a94e78" target="_blank" class="btn-notion-small">
                                        Ver detalles <i class="fas fa-long-arrow-alt-right"></i>
                                    </a>
                                </div>
                                <div class="chart-container"><canvas id="closingChart"></canvas></div>
                            </div>

                        </div>
                    </div>
                </main>

            </div>
        </div>
        

        <div id="alertModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">
                        <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                        <h2>¡Alerta de Reproceso!</h2>
                    </div>
                    <button class="close-btn" onclick="closeModal()" aria-label="Cerrar modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert-stat">
                        <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 24px;"></i>
                        <div><strong>Atención</strong><p>Proyectos devueltos requieren revisión inmediata.</p></div>
                    </div>
                    <div class="affected-projects">
                        <h4>Proyectos Afectados:</h4>
                        <ul id="reprocesoList" class="affected-projects-list">
                            </ul>
                    </div>
                    <div class="modal-actions"><button class="btn secondary" onclick="closeModal()">Entendido</button></div>
                </div>
            </div>
        </div>

    </div>
@endsection


@push('scripts')
{{-- El sistema ya carga Chart.js internamente, no importar versión externa --}}

<script>
    // Esperar a que el script del sistema cargue
    window.addEventListener('load', function() {
        if (typeof Chart !== 'undefined') {
            // Cargar el script del dashboard
            var script = document.createElement('script');
            script.src = "{{ asset('logistica-dashboard/js/script.js') }}";
            document.body.appendChild(script);
        } else {
            console.error('Chart.js no está disponible en el sistema.');
        }
    });
</script>
@endpush



