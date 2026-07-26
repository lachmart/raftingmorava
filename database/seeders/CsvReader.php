<?php

namespace Database\Seeders;

// Simple CSV reader with a header row – returns an array of associative rows [column => value].
class CsvReader
{
    /** @return array<int, array<string, string>> */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $rows = [];
        $handle = fopen($path, 'r');
        $header = null;

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            // Skip completely empty lines.
            if ($data === [null] || (count($data) === 1 && trim((string) $data[0]) === '')) {
                continue;
            }

            if ($header === null) {
                $header = array_map('trim', $data);

                continue;
            }

            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
