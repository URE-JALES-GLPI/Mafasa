<?php
/** Geração de PDFs dos relatórios Mafasa com o TCPDF incluído no GLPI. */

namespace GlpiPlugin\Mafasa;

final class PdfExport
{
    private static function esc($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,string>> $rows
     * @param array<int,string> $details
     */
    public static function download(
        string $filename,
        string $title,
        array $headers,
        array $rows,
        array $details = [],
        string $footer = ''
    ): void {
        if (!class_exists('\\TCPDF')) {
            http_response_code(503);
            echo self::esc(__('A biblioteca de PDF do GLPI não está disponível.', 'mafasa'));
            return;
        }

        $orientation = count($headers) > 5 ? 'L' : 'P';
        $format = count($headers) > 9 ? 'A3' : 'A4';
        $pdf = new \TCPDF($orientation, 'mm', $format, true, 'UTF-8', false);
        $pdf->SetCreator('Mafasa');
        $pdf->SetTitle($title);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', count($headers) > 9 ? 7 : 8);

        $html = '<h1 style="color:#17253b;font-size:17pt;">' . self::esc($title) . '</h1>';
        if ($details !== []) {
            $safeDetails = [];
            foreach ($details as $detail) {
                $safeDetails[] = self::esc($detail);
            }
            $html .= '<p style="font-size:8pt;color:#52657a;">'
                . implode('<br />', $safeDetails) . '</p>';
        }
        $pdf->writeHTML($html, true, false, true, false, '');
        if ($rows === []) {
            $pdf->writeHTML('<p>' . self::esc(__('Nenhum registro encontrado.', 'mafasa')) . '</p>', true, false, true, false, '');
        } else {
            foreach (array_chunk($rows, 100) as $chunkIndex => $chunk) {
                $table = '<table border="1" cellpadding="4" cellspacing="0" style="border-color:#dbe3ed;font-size:8pt;">'
                    . '<thead><tr style="background-color:#0079bf;color:#ffffff;">';
                foreach ($headers as $header) {
                    $table .= '<th><b>' . self::esc($header) . '</b></th>';
                }
                $table .= '</tr></thead><tbody>';
                foreach ($chunk as $index => $row) {
                    $table .= (($chunkIndex * 100 + $index) % 2 === 0)
                        ? '<tr>' : '<tr style="background-color:#f6faff;">';
                    foreach (array_keys($headers) as $cellIndex) {
                        $table .= '<td>' . nl2br(self::esc($row[$cellIndex] ?? ''), false) . '</td>';
                    }
                    $table .= '</tr>';
                }
                $table .= '</tbody></table>';
                $pdf->writeHTML($table, true, false, true, false, '');
            }
        }
        if ($footer !== '') {
            $pdf->writeHTML('<p style="font-size:8pt;color:#64748b;">' . self::esc($footer) . '</p>', true, false, true, false, '');
        }
        $pdf->Output($filename, 'D');
    }
}
