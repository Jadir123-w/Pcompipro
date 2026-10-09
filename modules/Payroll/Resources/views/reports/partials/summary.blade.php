
<div class="row mt-4">
    <div class="col-md-12">
        <h3>Resumen de Planillas</h3>
        <p>Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</p>

        <div class="row">
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-file-invoice-dollar"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Planillas</span>
                        <span class="info-box-number">{{ $summary->total_planillas }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Ingresos</span>
                        <span class="info-box-number">S/ {{ number_format($summary->total_ingresos, 2) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-hand-holding-usd"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Descuentos</span>
                        <span class="info-box-number">S/ {{ number_format($summary->total_descuentos, 2) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-piggy-bank"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Neto</span>
                        <span class="info-box-number">S/ {{ number_format($summary->total_neto, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
