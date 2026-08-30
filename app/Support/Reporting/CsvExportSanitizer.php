<?php

namespace App\Support\Reporting;

use Illuminate\Support\Str;

final class CsvExportSanitizer
{
    public function row(array $values): array
    {
        return array_map(
            fn (mixed $value): string|int|float => $this->cell($value),
            $values
        );
    }

    public function cell(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $text = is_scalar($value)
            ? (string) $value
            : json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

        $text = is_string($text) ? $text : '';
        $text = Str::limit($text, 8000, '… [TRUNCATED]');

        /*
         * Spreadsheet applications may treat cells beginning with these
         * characters as formulas. Prefixing an apostrophe keeps exported
         * user-controlled text inert when opened in Excel/Sheets.
         */
        if (
            $text !== ''
            && in_array($text[0], ['=', '+', '-', '@'], true)
        ) {
            return "'".$text;
        }

        return $text;
    }
}
