<?php

namespace Modules\Finance\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación del LISTADO de cajas chicas (la grilla de Finanzas > Caja Chica).
 *
 * Las fechas y los importes se escriben como valores reales de Excel —no como
 * texto— para que se puedan ordenar, sumar y usar en fórmulas. El formato de
 * presentación (dd/mm/yyyy y #,##0.00) se aplica por columna.
 *
 * WithStrictNullComparison es imprescindible: sin él, la librería compara cada
 * valor contra null de forma laxa y, como en PHP 0 == null, los importes en
 * cero se escribirían como celdas vacías en lugar de 0.00.
 */
class SmallCashExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths, WithColumnFormatting, WithStrictNullComparison
{
  protected $records;

  public function __construct($records)
  {
    $this->records = $records;
  }

  public function collection()
  {
    return $this->records;
  }

  public function headings(): array
  {
    return [
      'Código',
      'Usuario',
      'Fecha Apertura',
      'Fecha Cierre',
      'Saldo Inicial',
      'Total Ingresos',
      'Total Egresos',
      'Saldo Final',
      'Estado',
      'Referencia',
      'Descripción'
    ];
  }

  public function map($row): array
  {
    return [
      $row->code,
      $row->user->name,
      $this->fechaExcel($row->date_opening),
      $this->fechaExcel($row->date_closed),
      (float) $row->beginning_balance,
      (float) $row->income,
      (float) $row->expense,
      (float) $row->final_balance,
      $row->state === 'open' ? 'Abierta' : 'Cerrada',
      $row->reference_number ?? '',
      $row->description ?? ''
    ];
  }

  /**
   * Convierte la fecha a número de serie de Excel para que la celda sea una
   * fecha de verdad (ordenable y filtrable). Si no hay fecha, deja la celda
   * vacía —por ejemplo, una caja que sigue abierta no tiene fecha de cierre.
   *
   * @param  mixed  $fecha
   * @return float|string
   */
  private function fechaExcel($fecha)
  {
    if (empty($fecha)) {
      return '';
    }

    if ($fecha instanceof \DateTimeInterface) {
      return ExcelDate::dateTimeToExcel($fecha);
    }

    try {
      return ExcelDate::dateTimeToExcel(new \DateTime($fecha));
    } catch (\Exception $e) {
      // Si el valor no se puede interpretar como fecha, se muestra tal cual.
      return (string) $fecha;
    }
  }

  public function columnWidths(): array
  {
    return [
      'A' => 14,  // Código
      'B' => 22,  // Usuario
      'C' => 15,  // Fecha Apertura
      'D' => 15,  // Fecha Cierre
      'E' => 14,  // Saldo Inicial
      'F' => 14,  // Total Ingresos
      'G' => 14,  // Total Egresos
      'H' => 14,  // Saldo Final
      'I' => 11,  // Estado
      'J' => 18,  // Referencia
      'K' => 40,  // Descripción
    ];
  }

  public function columnFormats(): array
  {
    return [
      'C' => NumberFormat::FORMAT_DATE_DDMMYYYY,
      'D' => NumberFormat::FORMAT_DATE_DDMMYYYY,
      'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
      'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
      'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
      'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
    ];
  }

  public function styles(Worksheet $sheet)
  {
    $ultimaFila = $sheet->getHighestRow();

    // Importes alineados a la derecha; fechas y estado centrados.
    if ($ultimaFila > 1) {
      $sheet->getStyle('E2:H' . $ultimaFila)->getAlignment()->setHorizontal('right');
      $sheet->getStyle('C2:D' . $ultimaFila)->getAlignment()->setHorizontal('center');
      $sheet->getStyle('I2:I' . $ultimaFila)->getAlignment()->setHorizontal('center');
    }

    // La cabecera queda fija al desplazarse.
    $sheet->freezePane('A2');

    return [
      1 => [
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
      ],
    ];
  }

  public function title(): string
  {
    return 'Caja Chica';
  }
}
