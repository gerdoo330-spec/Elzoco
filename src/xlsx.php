<?php
declare(strict_types=1);

/**
 * خواننده‌ی سبک فایل xlsx بدون وابستگی خارجی (فقط ZipArchive و XMLReader).
 * برای هر سلول: مقدار محاسبه‌شده (cached value) و رنگ پس‌زمینه را برمی‌گرداند.
 */
final class XlsxReader
{
    private ZipArchive $zip;
    /** @var string[] */
    private array $shared = [];
    /** @var array<int,string> شماره‌ی استایل → رنگ پس‌زمینه (ARGB) */
    private array $xfFill = [];
    /** @var array<int,string> شماره‌ی style → کد قالب عددی اکسل */
    private array $xfNumFmt = [];
    /** @var array<int,string> شناسه‌ی قالب عددی → کد قالب */
    private array $numFmtCodes = [];
    /** @var array<int,string> شماره‌ی fill → رنگ */
    private array $fills = [];
    /** @var array<string,string> نام شیت → مسیر داخل zip */
    private array $sheetPaths = [];

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException('فایل اکسل باز نشد.');
        }
        $this->loadShared();
        $this->loadStyles();
        $this->loadSheetMap();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /** @return string[] */
    public function sheetNames(): array
    {
        return array_keys($this->sheetPaths);
    }

    /**
     * خروجی: [ شماره‌ی ردیف (۱-based) => [ اندیس ستون (۰-based) => ['v'=>mixed,'fill'=>string,'err'=>bool] ] ]
     * فقط سلول‌های دارای مقدار یا رنگ برگردانده می‌شوند.
     */
    public function readSheet(string $name): array
    {
        $name = $this->findSheet($name);
        if ($name === null) {
            throw new RuntimeException('شیت «' . $name . '» در فایل پیدا نشد.');
        }
        $xml = $this->zip->getFromName($this->sheetPaths[$name]);
        $rows = [];
        $reader = new XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_NOBLANKS);
        $cellRef = null; $type = null; $style = null; $value = null; $inValue = false; $inInline = false;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                $ln = $reader->localName;
                if ($ln === 'c') {
                    $cellRef = $reader->getAttribute('r');
                    $type = $reader->getAttribute('t');
                    $style = (int)($reader->getAttribute('s') ?? 0);
                    $value = null;
                    if ($reader->isEmptyElement) {
                        $this->storeCell($rows, $cellRef, null, $type, $style, false);
                        $cellRef = null;
                    }
                } elseif ($ln === 'v' && $cellRef !== null) {
                    $inValue = true;
                } elseif ($ln === 'is' && $cellRef !== null) {
                    $inInline = true;
                    $value = '';
                } elseif ($ln === 't' && $inInline) {
                    $value = (string)$value . $this->readText($reader);
                }
            } elseif ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                if ($inValue) {
                    $value = (string)$reader->value;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                $ln = $reader->localName;
                if ($ln === 'v') {
                    $inValue = false;
                } elseif ($ln === 'is') {
                    $inInline = false;
                } elseif ($ln === 'c' && $cellRef !== null) {
                    $this->storeCell($rows, $cellRef, $value, $type, $style, true);
                    $cellRef = null;
                    $value = null;
                }
            }
        }
        $reader->close();
        return $rows;
    }

    /** نام شیت را با حذف فاصله‌های اضافی و یکسان‌سازی «ی/ک» پیدا می‌کند */
    public function findSheet(string $wanted): ?string
    {
        $w = self::norm($wanted);
        foreach ($this->sheetPaths as $n => $_) {
            if (self::norm($n) === $w) {
                return $n;
            }
        }
        return null;
    }

    private function storeCell(array &$rows, string $ref, ?string $raw, ?string $type, int $style, bool $hasValue): void
    {
        [$col, $row] = self::splitRef($ref);
        $fill = $this->xfFill[$style] ?? '';
        $numFmt = $this->xfNumFmt[$style] ?? '';
        $err = false;
        $v = null;
        if ($hasValue && $raw !== null && $raw !== '') {
            switch ($type) {
                case 's':
                    $v = $this->shared[(int)$raw] ?? '';
                    break;
                case 'b':
                    $v = $raw === '1';
                    break;
                case 'e':
                    $err = true;
                    break;
                case 'str':
                case 'inlineStr':
                    $v = $raw;
                    break;
                default:
                    $v = is_numeric($raw) ? (float)$raw : $raw;
            }
        }
        if ($v === null && !$err && $fill === '') {
            return;
        }
        $display = $v;
        if (is_numeric($raw) && !in_array($type, ['s', 'str', 'inlineStr', 'b', 'e'], true)) {
            $display = $this->formatNumericDisplay((string)$raw, $numFmt);
        }
        $rows[$row][$col] = [
            'v' => $v,
            'display' => is_scalar($display) ? (string)$display : '',
            'raw' => $raw,
            'type' => (string)$type,
            'num_fmt' => $numFmt,
            'fill' => $fill,
            'err' => $err,
        ];
    }

    private function readText(XMLReader $r): string
    {
        return $r->readString();
    }

    private function loadShared(): void
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        $r = new XMLReader();
        $r->XML($xml, null, LIBXML_NONET);
        $buf = null;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') {
                $buf = '';
            } elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 't' && $buf !== null) {
                $buf .= $r->readString();
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'si') {
                $this->shared[] = $buf ?? '';
                $buf = null;
            }
        }
        $r->close();
    }

    private function loadStyles(): void
    {
        $xml = $this->zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return;
        }
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            return;
        }
        $ns = $sx->getNamespaces(true);
        $main = $sx->children($ns[''] ?? '');
        $fillIdx = 0;
        foreach ($sx->fills->fill ?? [] as $f) {
            $color = '';
            if (isset($f->patternFill->fgColor['rgb'])) {
                $color = strtoupper((string)$f->patternFill->fgColor['rgb']);
            }
            $this->fills[$fillIdx++] = $color;
        }
        // قالب‌های عددی سفارشی را نگه می‌داریم تا صفرهای ابتدایی کدهای اکسل حفظ شوند.
        foreach ($sx->numFmts->numFmt ?? [] as $fmt) {
            $this->numFmtCodes[(int)$fmt['numFmtId']] = (string)$fmt['formatCode'];
        }
        $builtIn = [0 => 'General', 1 => '0', 2 => '0.00', 3 => '#,##0', 4 => '#,##0.00'];
        $xfIdx = 0;
        foreach ($sx->cellXfs->xf ?? [] as $xf) {
            $fid = (int)($xf['fillId'] ?? 0);
            $numFmtId = (int)($xf['numFmtId'] ?? 0);
            $this->xfFill[$xfIdx] = $this->fills[$fid] ?? '';
            $this->xfNumFmt[$xfIdx] = $this->numFmtCodes[$numFmtId] ?? ($builtIn[$numFmtId] ?? '');
            $xfIdx++;
        }
    }

    /** مقدار عددی سلول را با قالب صفرپُر اکسل به متن قابل استفاده برای کد تبدیل می‌کند. */
    private function formatNumericDisplay(string $raw, string $formatCode): string
    {
        $numeric = $raw;
        if (preg_match('/^-?\d+(?:\.0+)?$/', $numeric)) {
            $numeric = preg_replace('/\.0+$/', '', $numeric) ?? $numeric;
        }
        if ($formatCode === '' || strcasecmp($formatCode, 'General') === 0) {
            return $numeric;
        }
        $section = explode(';', $formatCode)[0] ?? $formatCode;
        $section = preg_replace('/"(?:[^"]|"")*"/', '', $section) ?? $section;
        $section = preg_replace('/\\\\./', '', $section) ?? $section;
        $section = preg_replace('/\[[^]]*\]/', '', $section) ?? $section;
        // قالب‌هایی مانند 000 یا 000-000؛ جداکننده‌ها در کد نهایی وارد نمی‌شوند.
        if (preg_match('/^0+(?:[-\/. ]+0+)*$/', $section)) {
            $width = substr_count($section, '0');
            if ($width > 0 && preg_match('/^-?\d+$/', $numeric)) {
                $negative = str_starts_with($numeric, '-');
                $digits = $negative ? substr($numeric, 1) : $numeric;
                return ($negative ? '-' : '') . str_pad($digits, $width, '0', STR_PAD_LEFT);
            }
        }
        return $numeric;
    }

    private function loadSheetMap(): void
    {
        $wb = simplexml_load_string((string)$this->zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string)$this->zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string)$rel['Id']] = (string)$rel['Target'];
        }
        foreach ($wb->sheets->sheet as $s) {
            $rid = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = $relMap[$rid] ?? '';
            $target = ltrim($target, '/');
            if (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . $target;
            }
            $this->sheetPaths[(string)$s['name']] = $target;
        }
    }

    /** @return array{0:int,1:int} ستون (۰-based) و ردیف (۱-based) */
    public static function splitRef(string $ref): array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
            return [0, 0];
        }
        $col = 0;
        foreach (str_split($m[1]) as $ch) {
            $col = $col * 26 + (ord($ch) - 64);
        }
        return [$col - 1, (int)$m[2]];
    }

    public static function colIndex(string $letters): int
    {
        return self::splitRef($letters . '1')[0];
    }

    public static function norm(string $s): string
    {
        $s = str_replace(["\u{200c}", "\u{200f}", "\u{200e}"], '', $s);
        $s = strtr($s, ['ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه']);
        return preg_replace('/\s+/u', '', trim($s));
    }
}
