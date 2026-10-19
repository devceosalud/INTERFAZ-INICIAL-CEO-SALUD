<?php

namespace App\Support\Scheduling;

/**
 * Minimal Office Open XML spreadsheet writer for management exports.
 * Reuses the tested CSV rows and writes one filtered worksheet without Composer
 * dependencies or ZipArchive. ZIP entries are stored (no compression).
 */
final class ManagementXlsx
{
    private const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public static function contentType(): string
    {
        return self::MIME;
    }

    /** @param resource $csv @param resource $destination */
    public static function fromCsv($csv, $destination, string $sheetName = 'Reporte'): void
    {
        rewind($csv);
        // The UTF-8 BOM must be consumed before CSV parsing; otherwise PHP treats
        // the opening quote of the first header as literal text.
        if (fread($csv, 3) !== "\xEF\xBB\xBF") {
            rewind($csv);
        }
        $headers = fgetcsv($csv, 0, ';', '"', '');
        if ($headers === false || $headers === [null]) {
            throw new \RuntimeException('Reporte sin encabezados.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $columns = count($headers);
        if ($columns < 1 || $columns > 150) {
            throw new \RuntimeException('Número de columnas no soportado.');
        }

        $sheet = fopen('php://temp/maxmemory:2097152', 'w+b');
        if (!$sheet) {
            throw new \RuntimeException('No se puede preparar el archivo XLSX.');
        }
        try {
            fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="16"/><cols>');
            foreach ($headers as $i => $heading) {
                $width = min(45, max(14, strlen((string) $heading) + 4));
                fwrite($sheet, '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>');
            }
            fwrite($sheet, '</cols><sheetData>');
            self::writeRow($sheet, 1, $headers, $headers, true);
            $rowIndex = 1;
            while (($cells = fgetcsv($csv, 0, ';', '"', '')) !== false) {
                if ($rowIndex >= 1048576) {
                    throw new \RuntimeException('El reporte excede el límite de filas de Excel. Reduzca el periodo.');
                }
                $rowIndex++;
                self::writeRow($sheet, $rowIndex, $cells, $headers, false);
            }
            fwrite($sheet, '</sheetData><autoFilter ref="A1:'.self::columnName($columns).$rowIndex.'"/>'
                .'<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
                .'</worksheet>');

            $sheetName = self::xml(substr($sheetName, 0, 31));
            $static = [
                '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                    .'<Default Extension="xml" ContentType="application/xml"/>'
                    .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                    .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                    .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                    .'</Types>',
                '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                    .'</Relationships>',
                'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                    .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                    .'<sheets><sheet name="'.$sheetName.'" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                    .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                    .'</Relationships>',
                'xl/styles.xml' => self::styles(),
                'xl/worksheets/sheet1.xml' => $sheet,
            ];
            self::writeZip($destination, $static);
        } finally {
            fclose($sheet);
        }
    }

    private static function writeRow($sheet, int $n, array $cells, array $headers, bool $heading): void
    {
        fwrite($sheet, '<row r="'.$n.'"'.($heading ? ' ht="24" customHeight="1"' : '').'>');
        foreach ($headers as $i => $label) {
            $val = (string) ($cells[$i] ?? '');
            if ($val === '') {
                continue;
            }
            $reference = self::columnName($i + 1).$n;
            if (!$heading) {
                $kind = self::numericKind((string) $label);
                if ($kind !== null && preg_match('/^-?\d+(?:[.,]\d+)?$/D', $val)) {
                    $value = str_replace(',', '.', $val);
                    $style = $kind === 'money' ? 2 : ($kind === 'percent' ? 3 : 0);
                    fwrite($sheet, '<c r="'.$reference.'"'.($style ? ' s="'.$style.'"' : '').'><v>'.$value.'</v></c>');
                    continue;
                }
                // CSV adds a leading quote to guard Excel's formula evaluation, and to
                // preserve leading-zero identifiers. XLSX inline text is intrinsically safe.
                if (str_starts_with($val, "'")) {
                    $val = substr($val, 1);
                }
            }
            fwrite($sheet, '<c r="'.$reference.'" t="inlineStr"'.($heading ? ' s="1"' : '').'>'
                .'<is><t xml:space="preserve">'.self::xml($val).'</t></is></c>');
        }
        fwrite($sheet, '</row>');
    }

    private static function numericKind(string $heading): ?string
    {
        if (str_contains($heading, 'S/')) {
            return 'money';
        }
        if (str_starts_with($heading, 'Porcentaje ocupación')) {
            return 'percent';
        }
        if ($heading === 'Valor descuento') {
            return 'plain';
        }
        if (preg_match('/^(?:ID (?:cita|paciente|profesional|pago|voucher|sede intervalo)|Duración minutos|Minutos intervalo|Total slots del día|Slots ocupados del día|Slots libres del día)$/u', $heading)) {
            return 'plain';
        }
        return null;
    }

    private static function columnName(int $column): string
    {
        $result = '';
        while ($column > 0) {
            $column--;
            $result = chr(65 + ($column % 26)).$result;
            $column = intdiv($column, 26);
        }
        return $result;
    }

    private static function xml(string $text): string
    {
        // SpreadsheetML requires XML 1.0 characters; user-entered notes can contain controls.
        $clean = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
        return htmlspecialchars($clean ?? '', ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;S/&quot; #,##0.00"/>'
            .'<numFmt numFmtId="165" formatCode="0.00&quot;%&quot;"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF237D82"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /**
     * ZIP stored entries: no ZipArchive or Composer dependency, O(1) memory for sheet XML.
     * A ZIP64 archive is deliberately not generated; export validation limits row counts.
     * @param resource $destination
     * @param array<string,resource|string> $entries
     */
    private static function writeZip($destination, array $entries): void
    {
        $offset = 0;
        $directory = '';
        $count = 0;
        foreach ($entries as $name => $value) {
            if (is_resource($value)) {
                rewind($value);
                $ctx = hash_init('crc32b');
                hash_update_stream($ctx, $value);
                $crc = (int) hexdec(hash_final($ctx));
                $size = ftell($value);
                rewind($value);
            } else {
                $crc = (int) hexdec(hash('crc32b', $value));
                $size = strlen($value);
            }
            if ($size > 0xFFFFFFFF) {
                throw new \RuntimeException('Archivo demasiado grande para ZIP estándar.');
            }
            $flag = 0x0800; // UTF-8 names
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, $flag, 0, 0, 0, $crc, $size, $size, strlen($name), 0).$name;
            fwrite($destination, $local);
            if (is_resource($value)) {
                stream_copy_to_stream($value, $destination);
            } else {
                fwrite($destination, $value);
            }
            $central = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flag, 0, 0, 0, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
            $directory .= $central;
            $offset += strlen($local) + $size;
            $count++;
        }
        fwrite($destination, $directory);
        fwrite($destination, pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($directory), $offset, 0));
    }
}
