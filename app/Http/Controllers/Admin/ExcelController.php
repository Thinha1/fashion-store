<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminExcel;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelController extends Controller
{
    public function export(Request $request, string $resource, AdminExcel $excel): StreamedResponse
    {
        return $this->download($request, $resource, $excel, false);
    }

    public function template(Request $request, string $resource, AdminExcel $excel): StreamedResponse
    {
        return $this->download($request, $resource, $excel, true);
    }

    public function import(Request $request, string $resource, AdminExcel $excel): RedirectResponse
    {
        $definition = $excel->definition($resource);
        abort_unless($request->user()->hasPermission($definition['permission']), 403);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'file.required' => 'Chọn file Excel để nhập dữ liệu.',
            'file.mimes' => 'Chỉ hỗ trợ file Excel .xlsx. Hãy dùng file mẫu.',
            'file.max' => 'File Excel không được vượt quá 5 MB.',
        ]);
        $result = $excel->import($resource, $request->file('file'), $request);

        return redirect()->route('admin.'.$resource.'.index')->with('status',
            'Nhập Excel thành công: '.$result['created'].' bản ghi mới, '.$result['updated'].' bản ghi cập nhật.'
        );
    }

    private function download(Request $request, string $resource, AdminExcel $excel, bool $template): StreamedResponse
    {
        $definition = $excel->definition($resource);
        abort_unless($request->user()->hasPermission($definition['permission']), 403);
        $book = $excel->workbook($resource, $template);
        $filename = ($template ? 'mau-' : '').$resource.'-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
