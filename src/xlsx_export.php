<?php
declare(strict_types=1);

/**
 * خروجی‌ساز سبک XLSX بدون وابستگی به Composer.
 * به ZipArchive نیاز دارد؛ همان افزونه‌ای که برای خواندن اکسل ماهانه‌ی سامانه لازم است.
 */
function xlsx_export_col(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function xlsx_export_xml($value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function xlsx_export_download(string $filename, string $sheetName, array $headers, array $rows): void
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit('افزونه ZIP در PHP فعال نیست. برای خروجی Excel، افزونه zip را در هاست فعال کنید.');
    }

    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'requests.xlsx';
    if (!str_ends_with(strtolower($filename), '.xlsx')) {
        $filename .= '.xlsx';
    }
    $sheetName = mb_substr(preg_replace('/[\\\/\?\*\[\]:]/u', ' ', $sheetName) ?: 'گزارش', 0, 31);
    $columnCount = max(1, count($headers));
    $lastColumn = xlsx_export_col($columnCount);
    $lastRow = max(1, count($rows) + 1);

    $widths = [];
    foreach ($headers as $i => $header) {
        $max = mb_strlen((string)$header);
        foreach ($rows as $row) {
            $max = max($max, mb_strlen((string)($row[$i] ?? '')));
        }
        $widths[$i + 1] = max(12, min(38, $max + 3));
    }

    $colsXml = '<cols>';
    foreach ($widths as $idx => $width) {
        $colsXml .= '<col min="' . $idx . '" max="' . $idx . '" width="' . $width . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    $sheetRows = [];
    $makeRow = static function (array $cells, int $rowNumber, bool $header = false): string {
        $out = '<row r="' . $rowNumber . '"' . ($header ? ' ht="32" customHeight="1"' : ' ht="26" customHeight="1"') . '>';
        foreach ($cells as $i => $value) {
            $ref = xlsx_export_col($i + 1) . $rowNumber;
            $style = $header ? 1 : 2;
            if (!$header && (is_int($value) || is_float($value))) {
                $out .= '<c r="' . $ref . '" s="' . $style . '" t="n"><v>' . (string)$value . '</v></c>';
            } else {
                $text = xlsx_export_xml($value === null ? '' : $value);
                $out .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
            }
        }
        return $out . '</row>';
    };

    $sheetRows[] = $makeRow(array_values($headers), 1, true);
    foreach (array_values($rows) as $index => $row) {
        $cells = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $cells[] = $row[$i] ?? '';
        }
        $sheetRows[] = $makeRow($cells, $index + 2, false);
    }

    $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
        . '<sheetViews><sheetView rightToLeft="1" workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="24"/>'
        . $colsXml . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
        . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>'
        . '<sheet name="' . xlsx_export_xml($sheetName) . '" sheetId="1" r:id="rId1"/>'
        . '</sheets></workbook>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="10"/><name val="Arial"/><family val="2"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF97316"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FFD7DEE8"/></left><right style="thin"><color rgb="FFD7DEE8"/></right>'
        . '<top style="thin"><color rgb="FFD7DEE8"/></top><bottom style="thin"><color rgb="FFD7DEE8"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'elzoco-xlsx-');
    if ($tmp === false) {
        http_response_code(500);
        exit('ساخت فایل موقت برای خروجی Excel ناموفق بود.');
    }
    $zip = new ZipArchive();
    $opened = $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        @unlink($tmp);
        http_response_code(500);
        exit('امکان ساخت فایل Excel وجود ندارد.');
    }
    $zip->addFromString('[Content_Types].xml', $types);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
    $zip->close();

    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
