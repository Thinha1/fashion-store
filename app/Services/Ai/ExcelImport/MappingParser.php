<?php

namespace App\Services\Ai\ExcelImport;

use App\Services\Ai\InvalidAiResponseException;

/**
 * Validates the model's column-mapping answer against the real spreadsheet:
 * the sheet must exist, the row numbers must be inside it, and every column
 * letter must fall within the sheet's actual width. Anything else the model
 * "invented" is rejected or dropped rather than trusted.
 */
class MappingParser
{
    /**
     * @return array{sheet: string, header_row: int, first_data_row: int, columns: array<string, string|null>, note: string}
     *
     * @throws InvalidAiResponseException
     */
    public function parse(string $raw, SpreadsheetGrid $grid): array
    {
        $json = trim($raw);
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/(?:\A```[a-z]*\n?)|(?:\n?```\z)/i', '', $json) ?? $json;
        }
        $decoded = json_decode(trim($json), true);
        if (! is_array($decoded)) {
            throw new InvalidAiResponseException('AI response is not a JSON object.');
        }

        $sheet = is_string($decoded['sheet'] ?? null) ? trim($decoded['sheet']) : '';
        if (! $grid->hasSheet($sheet)) {
            // A single-sheet file doesn't need the model to repeat the name correctly.
            if (count($grid->sheetNames()) !== 1) {
                throw new InvalidAiResponseException('AI chose a sheet that does not exist.');
            }
            $sheet = $grid->sheetNames()[0];
        }

        $rowCount = $grid->rowCount($sheet);
        $headerRow = (int) ($decoded['header_row'] ?? 1);
        if ($headerRow < 1 || $headerRow > $rowCount) {
            $headerRow = 1;
        }
        $firstDataRow = (int) ($decoded['first_data_row'] ?? $headerRow + 1);
        if ($firstDataRow <= $headerRow || $firstDataRow > $rowCount) {
            $firstDataRow = $headerRow + 1;
        }

        $width = $grid->columnCount($sheet);
        $columns = [];
        $used = [];
        foreach (MappingPromptBuilder::FIELDS as $field) {
            $letter = $decoded['columns'][$field] ?? null;
            $letter = is_string($letter) ? strtoupper(trim($letter)) : null;
            $index = $letter !== null && preg_match('/\A[A-Z]{1,2}\z/', $letter) ? self::columnIndex($letter) : null;
            // Out-of-range letters and a column claimed by two fields are dropped, not guessed.
            $valid = $index !== null && $index < $width && ! isset($used[$letter]);
            $columns[$field] = $valid ? $letter : null;
            if ($valid) {
                $used[$letter] = true;
            }
        }

        if ($columns['name'] === null) {
            throw new InvalidAiResponseException('AI did not find the product name column.');
        }

        return [
            'sheet' => $sheet,
            'header_row' => $headerRow,
            'first_data_row' => $firstDataRow,
            'columns' => $columns,
            'note' => is_string($decoded['note'] ?? null) ? trim($decoded['note']) : '',
        ];
    }

    /** 0-based index of a column letter ("A" => 0, "AA" => 26). */
    public static function columnIndex(string $letter): int
    {
        $index = 0;
        foreach (str_split($letter) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }
}
