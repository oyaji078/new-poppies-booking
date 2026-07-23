<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams report rows as CSV so large exports never buffer in memory.
 */
class CsvExporter
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
