<?php
namespace local_unileasysregistration;

defined('MOODLE_INTERNAL') || die();

/**
 * Export a generated activation-code batch in formats suitable for school distribution.
 * Plaintext codes are intentionally kept only in the current Moodle session after generation.
 */
class token_export {
    public static function get_batch(): array {
        global $SESSION;
        return !empty($SESSION->unileasys_token_export_batch) && is_array($SESSION->unileasys_token_export_batch)
            ? $SESSION->unileasys_token_export_batch : [];
    }

    public static function set_batch(array $rows, string $title): void {
        global $SESSION;
        $SESSION->unileasys_token_export_batch = [
            'created' => time(),
            'title' => $title,
            'rows' => $rows,
        ];
    }

    public static function clear_batch(): void {
        global $SESSION;
        unset($SESSION->unileasys_token_export_batch);
    }

    public static function csv(array $batch): void {
        $filename = self::filename($batch, 'csv');
        self::download_headers('text/csv; charset=UTF-8', $filename);
        $out = fopen('php://output', 'w');
        // UTF-8 BOM improves Excel compatibility.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Unileasys Token System']);
        fputcsv($out, ['Activation Code Distribution List']);
        fputcsv($out, []);
        fputcsv($out, ['Sl. No.', 'Activation Code', 'School', 'Class', 'Account Type', 'Academic Session', 'Course / Purpose', 'Course License', 'Expires On']);
        foreach ($batch['rows'] as $i => $row) {
            fputcsv($out, [
                $i + 1, $row['token'], $row['school'], $row['class'], $row['accounttype'], $row['session'], $row['course'] ?? 'Account Verification', $row['licenseid'] ?? '', $row['expires'],
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * Excel-compatible SpreadsheetML 2003 file. This requires no third-party PHP library.
     */
    public static function excel(array $batch): void {
        $filename = self::filename($batch, 'xls');
        self::download_headers('application/vnd.ms-excel; charset=UTF-8', $filename);
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<Styles><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="16"/></Style><Style ss:ID="Subtitle"><Font ss:Bold="1" ss:Size="11"/></Style><Style ss:ID="Header"><Font ss:Bold="1"/></Style><Style ss:ID="Code"><NumberFormat ss:Format="@"/></Style></Styles>';
        echo '<Worksheet ss:Name="Activation Codes"><Table>';
        echo '<Column ss:Width="45"/><Column ss:Width="145"/><Column ss:Width="175"/><Column ss:Width="80"/><Column ss:Width="85"/><Column ss:Width="105"/><Column ss:Width="180"/><Column ss:Width="70"/><Column ss:Width="105"/>';
        echo '<Row><Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">Unileasys Token System</Data></Cell></Row>';
        echo '<Row><Cell ss:MergeAcross="8" ss:StyleID="Subtitle"><Data ss:Type="String">Activation Code Distribution List</Data></Cell></Row>';
        echo '<Row><Cell ss:MergeAcross="8"><Data ss:Type="String">One activation code per student. Each code is single-use and confidential.</Data></Cell></Row>';
        $headers = ['Sl. No.', 'Activation Code', 'School', 'Class', 'Account Type', 'Academic Session', 'Course / Purpose', 'Course License', 'Expires On'];
        echo '<Row>';
        foreach ($headers as $header) {
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . self::xml($header) . '</Data></Cell>';
        }
        echo '</Row>';
        foreach ($batch['rows'] as $i => $row) {
            echo '<Row>';
            echo '<Cell><Data ss:Type="Number">' . ($i + 1) . '</Data></Cell>';
            echo '<Cell ss:StyleID="Code"><Data ss:Type="String">' . self::xml($row['token']) . '</Data></Cell>';
            foreach (['school', 'class', 'accounttype', 'session', 'course', 'licenseid', 'expires'] as $key) {
                echo '<Cell><Data ss:Type="String">' . self::xml($row[$key]) . '</Data></Cell>';
            }
            echo '</Row>';
        }
        echo '</Table></Worksheet></Workbook>';
        exit;
    }

    public static function pdf(array $batch): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $pdf = new \pdf();
        $pdf->SetCreator('Unileasys Token System');
        $pdf->SetAuthor('Unileasys Token System');
        $pdf->SetTitle('Unileasys Token System - ' . $batch['title']);
        $pdf->SetSubject('School activation codes');
        $pdf->SetMargins(8, 10, 8);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage('L', 'A4');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->writeHTML('<h1>Unileasys Token System</h1>'
            . '<h2>' . s($batch['title']) . '</h2>'
            . '<p><b>Activation Code Distribution List</b></p>'
            . '<p>Use one activation code per student. Each code is single-use and must be kept confidential.</p>', true, false, true, false, '');

        $html = '<table border="1" cellpadding="3"><tr bgcolor="#eeeeee">'
            . '<th width="4%"><b>No.</b></th><th width="18%"><b>Activation Code</b></th>'
            . '<th width="18%"><b>School</b></th><th width="8%"><b>Class</b></th>'
            . '<th width="8%"><b>Type</b></th><th width="11%"><b>Session</b></th><th width="20%"><b>Course / Purpose</b></th><th width="5%"><b>License</b></th><th width="8%"><b>Expires</b></th></tr>';
        foreach ($batch['rows'] as $i => $row) {
            $html .= '<tr><td>' . ($i + 1) . '</td><td><b>' . s($row['token']) . '</b></td>'
                . '<td>' . s($row['school']) . '</td><td>' . s($row['class']) . '</td>'
                . '<td>' . s($row['accounttype']) . '</td><td>' . s($row['session']) . '</td>'
                . '<td>' . s($row['course'] ?? 'Account Verification') . '</td><td>' . s($row['licenseid'] ?? '') . '</td><td>' . s($row['expires']) . '</td></tr>';
        }
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output(self::filename($batch, 'pdf'), 'D');
        exit;
    }

    private static function download_headers(string $contenttype, string $filename): void {
        if (headers_sent()) {
            throw new \moodle_exception('Cannot download after output has started.');
        }
        header('Content-Type: ' . $contenttype);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    }

    private static function filename(array $batch, string $extension): string {
        $base = clean_filename(strtolower(str_replace(' ', '_', $batch['title'] ?? 'activation_codes')));
        return 'unileasys_token_system_' . ($base ?: 'activation_codes') . '.' . $extension;
    }

    private static function xml(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
