<?php

namespace App\Services\Ai\ExcelImport;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;
use ZipArchive;

/**
 * A size-bounded, formula-free copy of an uploaded .xlsx as plain arrays of
 * cells, so nothing downstream (the model prompt, the mapper) ever touches
 * PhpSpreadsheet objects or evaluates anything from the file. Limits mirror
 * AdminExcel's: this is untrusted input that ends up in an LLM prompt.
 */
final class SpreadsheetGrid
{
    public const MAX_ROWS = 2000;

    public const MAX_COLUMNS = 30;

    public const MAX_SHEETS = 6;

    private const SNAPSHOT_ROWS = 15;

    private const SNAPSHOT_CELL_CHARS = 60;

    /** @param array<string, list<list<string|int|float|null>>> $sheets */
    private function __construct(private readonly array $sheets) {}

    /**
     * @param  array<string, list<list<string|int|float|null>>>  $sheets
     */
    public static function fromArray(array $sheets): self
    {
        return new self($sheets);
    }

    public static function fromPath(string $path): self
    {
        self::assertSafeArchive($path);

        try {
            $reader = new Xlsx;
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Không đọc được file Excel. Hãy lưu lại dưới định dạng .xlsx rồi thử lại.']);
        }

        $sheets = [];
        try {
            foreach ($book->getWorksheetIterator() as $sheet) {
                $highestRow = $sheet->getHighestDataRow();
                $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
                if ($highestRow <= 1 && $sheet->getCell('A1')->getValue() === null) {
                    continue;
                }
                if ($highestRow > self::MAX_ROWS || $highestColumn > self::MAX_COLUMNS) {
                    throw ValidationException::withMessages(['file' => 'Sheet "'.$sheet->getTitle().'" quá lớn. Tối đa '.self::MAX_ROWS.' dòng và '.self::MAX_COLUMNS.' cột.']);
                }
                if (count($sheets) >= self::MAX_SHEETS) {
                    throw ValidationException::withMessages(['file' => 'File có quá nhiều sheet có dữ liệu (tối đa '.self::MAX_SHEETS.').']);
                }
                $range = 'A1:'.Coordinate::stringFromColumnIndex($highestColumn).$highestRow;
                $sheets[$sheet->getTitle()] = array_map(
                    fn (array $row): array => array_map(self::cleanCell(...), $row),
                    $sheet->rangeToArray($range, null, false, false, false),
                );
            }
        } finally {
            $book->disconnectWorksheets();
        }

        if ($sheets === []) {
            throw ValidationException::withMessages(['file' => 'File Excel không có dữ liệu.']);
        }

        return new self($sheets);
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    public function hasSheet(string $name): bool
    {
        return isset($this->sheets[$name]);
    }

    /** @return list<list<string|int|float|null>> */
    public function rows(string $sheet): array
    {
        return $this->sheets[$sheet] ?? [];
    }

    public function columnCount(string $sheet): int
    {
        return max(array_map('count', $this->sheets[$sheet] ?? [[]]));
    }

    public function rowCount(string $sheet): int
    {
        return count($this->sheets[$sheet] ?? []);
    }

    /**
     * The compact text the model sees: every sheet's name, size and first rows,
     * with column letters as the only handle it can answer with.
     */
    public function snapshot(): string
    {
        $blocks = [];
        foreach ($this->sheets as $name => $rows) {
            $columns = $this->columnCount($name);
            $letters = array_map(Coordinate::stringFromColumnIndex(...), range(1, max(1, $columns)));
            $lines = [
                'Sheet "'.$name.'" — '.count($rows).' dòng, '.$columns.' cột ('.$letters[0].'–'.end($letters).')',
                'Dòng | '.implode(' | ', $letters),
            ];
            foreach (array_slice($rows, 0, self::SNAPSHOT_ROWS, true) as $index => $row) {
                $cells = [];
                for ($column = 0; $column < $columns; $column++) {
                    $cells[] = $this->previewCell($row[$column] ?? null);
                }
                $lines[] = ($index + 1).' | '.implode(' | ', $cells);
            }
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    private function previewCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return mb_strlen($text) > self::SNAPSHOT_CELL_CHARS ? mb_substr($text, 0, self::SNAPSHOT_CELL_CHARS).'…' : $text;
    }

    private static function cleanCell(mixed $value): string|int|float|null
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_string($value)) {
            $value = trim($value);

            // Formulas are never evaluated or forwarded — only plain values.
            return ($value === '' || str_starts_with($value, '=')) ? null : $value;
        }

        return is_int($value) || is_float($value) ? $value : null;
    }

    private static function assertSafeArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'File không phải Excel .xlsx hợp lệ.']);
        }
        try {
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $expanded += $zip->statIndex($index)['size'];
            }
            if ($expanded > 50 * 1024 * 1024 || $zip->numFiles > 1000) {
                throw ValidationException::withMessages(['file' => 'File Excel quá lớn. Hãy chia thành các file nhỏ hơn.']);
            }
        } finally {
            $zip->close();
        }
    }
}
