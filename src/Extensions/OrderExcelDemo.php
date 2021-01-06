<?php
/**
 * Created by robin@2019/9/2 12:49
 */

namespace Ejoy\Shop\Extensions;


use App\Models\Order;
use Encore\Admin\Grid\Exporter;
use Encore\Admin\Grid\Exporters\AbstractExporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Facades\DB;

class OrderExcelDemo   implements FromArray
{
    use Exportable;

    public function array(): array
    {
        return OrderExcelExporter::HEAD;
    }

}
