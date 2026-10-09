<?php

namespace Modules\Finance\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Finance\Models\SmallCash;

class SmallCashTransactionsExport implements FromView, WithColumnWidths, WithTitle
{
  protected $smallCash;

  public function __construct(SmallCash $smallCash)
  {
    $this->smallCash = $smallCash;
  }

  public function view(): View
  {
    return view('finance::small_cash.export_excel', [
      'smallCash' => $this->smallCash,
      'transactions' => $this->smallCash->transactions,
    ]);
  }

  public function columnWidths(): array
  {
    return [
      'A' => 3,
      'B' => 12, // FECHA
      'C' => 20, // PROVEEDOR / CLIENTE
      'D' => 18, // METODO PAGO
      'E' => 18, // N DOCUMENTO
      'F' => 35, // DESCRIPCION
      'G' => 24, // CATEGORIA / TIPO-GASTO (el rotulo completo necesita este ancho)
      'H' => 18, // RUTA
      'I' => 22, // PROYECTO
      'J' => 18, // RESPONSABLE
      'K' => 15, // MONTO
    ];
  }

  public function title(): string
  {
    return 'Movimientos ' . $this->smallCash->code;
  }
}
