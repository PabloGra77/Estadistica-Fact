<?php
/**
 * XlsxWriter — generador de archivos .xlsx real (Open XML / OOXML).
 * No requiere dependencias externas; usa ZipArchive (PHP estándar).
 */
class XlsxWriter
{
    // ── Constantes de estilo ────────────────────────────────────────────────
    const S_DEF      = 0;   // sin estilo
    const S_TITLE    = 1;   // título: 14pt bold blanco sobre #1E3A5F
    const S_INFO_LBL = 2;   // label info: 10pt bold #1E3A5F sobre #DBEAFE
    const S_INFO_VAL = 3;   // valor info: 10pt #1E3A5F sobre #DBEAFE
    const S_SEC_HDR  = 4;   // sección: 10pt bold blanco sobre #2563EB
    const S_LBL      = 5;   // label datos: normal con borde
    const S_VAL      = 6;   // valor datos: bold derecha con borde
    const S_CUR      = 7;   // moneda: bold verde #,##0
    const S_PCT      = 8;   // porcentaje: bold naranja 0%
    const S_COL_HDR  = 9;   // encabezado columna: 9pt bold blanco #1E40AF wrap
    const S_CELL     = 10;  // celda normal centrada
    const S_CELL_L   = 11;  // celda normal izquierda
    const S_OK       = 12;  // servicio OK: verde
    const S_NO       = 13;  // servicio NO: rojo
    const S_CELL_PCT = 14;  // celda porcentaje centrada
    const S_CELL_CUR = 15;  // celda moneda
    const S_CUR_OK   = 16;  // moneda OK (verde bg)
    const S_CUR_EV   = 17;  // moneda Evento (azul bg)
    const S_TOT      = 18;  // fila total
    const S_TOT_CUR  = 19;  // total moneda
    const S_P1       = 20;  // badge Paquete 1
    const S_P2       = 21;  // badge Paquete 2
    const S_EV       = 22;  // badge Evento

    private array $sheets = [];
    private array $ss     = [];   // shared strings
    private array $ssMap  = [];

    // ── API pública ─────────────────────────────────────────────────────────

    public function addSheet(string $name): int
    {
        $idx = count($this->sheets);
        $this->sheets[$idx] = ['name' => $name, 'rows' => [], 'colWidths' => []];
        return $idx;
    }

    /**
     * Agrega una fila a una hoja.
     * @param array $cells  cada celda es [valor, estilo, ?span]
     *   - valor: string|int|float|null
     *   - estilo: constante S_*
     *   - span: columnas a combinar (default 1)
     * @param int $height   altura de fila en puntos (0 = automático)
     */
    public function row(int $sh, array $cells, int $height = 0): void
    {
        $this->sheets[$sh]['rows'][] = ['c' => $cells, 'h' => $height];
    }

    /** Fila vacía (separador visual) */
    public function emptyRow(int $sh, int $height = 8): void
    {
        $this->sheets[$sh]['rows'][] = ['c' => [], 'h' => $height];
    }

    public function colWidth(int $sh, int $col, float $width): void
    {
        $this->sheets[$sh]['colWidths'][$col] = $width;
    }

    /** Genera el binario .xlsx listo para enviar */
    public function build(): string
    {
        // Construir worksheets primero (llena shared strings)
        $sheetXmls = [];
        foreach ($this->sheets as $i => $_) {
            $sheetXmls[$i] = $this->buildSheet($i);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',         $this->buildContentTypes());
        $zip->addFromString('_rels/.rels',                 $this->buildRootRels());
        $zip->addFromString('xl/workbook.xml',             $this->buildWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels',  $this->buildWorkbookRels());
        $zip->addFromString('xl/styles.xml',               $this->buildStyles());
        $zip->addFromString('xl/sharedStrings.xml',        $this->buildSharedStrings());
        foreach ($sheetXmls as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
        }
        $zip->close();
        $content = file_get_contents($tmp);
        unlink($tmp);
        return $content;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function ssIdx(string $v): int
    {
        if (!isset($this->ssMap[$v])) {
            $this->ssMap[$v] = count($this->ss);
            $this->ss[] = $v;
        }
        return $this->ssMap[$v];
    }

    private static function col2letter(int $c): string
    {
        $c++;
        $s = '';
        while ($c > 0) {
            $c--;
            $s = chr(65 + $c % 26) . $s;
            $c = intdiv($c, 26);
        }
        return $s;
    }

    private function buildSheet(int $shIdx): string
    {
        $sh = $this->sheets[$shIdx];
        $o  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        if (!empty($sh['colWidths'])) {
            $o .= '<cols>';
            foreach ($sh['colWidths'] as $ci => $w) {
                $n  = $ci + 1;
                $o .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
            }
            $o .= '</cols>';
        }

        $o      .= '<sheetData>';
        $merges  = [];

        foreach ($sh['rows'] as $ri => $row) {
            $rn    = $ri + 1;
            $hAttr = $row['h'] ? ' ht="' . $row['h'] . '" customHeight="1"' : '';
            $o    .= '<row r="' . $rn . '"' . $hAttr . '>';
            $ci    = 0;

            foreach ($row['c'] as $cell) {
                $val  = $cell[0];
                $sty  = $cell[1] ?? 0;
                $span = $cell[2] ?? 1;
                $ref  = self::col2letter($ci) . $rn;
                $sA   = $sty ? ' s="' . $sty . '"' : '';

                if ($val === null || $val === '') {
                    $o .= '<c r="' . $ref . '"' . $sA . '/>';
                } elseif (is_int($val) || is_float($val)) {
                    $o .= '<c r="' . $ref . '"' . $sA . '><v>' . $val . '</v></c>';
                } elseif (is_numeric($val)) {
                    $o .= '<c r="' . $ref . '"' . $sA . '><v>' . $val . '</v></c>';
                } else {
                    $idx = $this->ssIdx((string)$val);
                    $o  .= '<c r="' . $ref . '" t="s"' . $sA . '><v>' . $idx . '</v></c>';
                }

                if ($span > 1) {
                    $endRef = self::col2letter($ci + $span - 1) . $rn;
                    $merges[] = $ref . ':' . $endRef;
                    for ($m = 1; $m < $span; $m++) {
                        $ci++;
                        $mRef = self::col2letter($ci) . $rn;
                        $o   .= '<c r="' . $mRef . '" s="' . $sty . '"/>';
                    }
                }
                $ci++;
            }
            $o .= '</row>';
        }

        $o .= '</sheetData>';
        if (!empty($merges)) {
            $o .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $m) {
                $o .= '<mergeCell ref="' . $m . '"/>';
            }
            $o .= '</mergeCells>';
        }
        $o .= '</worksheet>';
        return $o;
    }

    private function buildSharedStrings(): string
    {
        $cnt = count($this->ss);
        $o   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
             . ' count="' . $cnt . '" uniqueCount="' . $cnt . '">';
        foreach ($this->ss as $s) {
            $o .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        return $o . '</sst>';
    }

    private function buildWorkbook(): string
    {
        $o = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
           . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
           . '<sheets>';
        foreach ($this->sheets as $i => $sh) {
            $name = htmlspecialchars($sh['name'], ENT_XML1, 'UTF-8');
            $o   .= '<sheet name="' . $name . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return $o . '</sheets></workbook>';
    }

    private function buildWorkbookRels(): string
    {
        $n = count($this->sheets);
        $o = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $_) {
            $o .= '<Relationship Id="rId' . ($i + 1) . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $o .= '<Relationship Id="rId' . ($n + 1) . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            . ' Target="styles.xml"/>';
        $o .= '<Relationship Id="rId' . ($n + 2) . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"'
            . ' Target="sharedStrings.xml"/>';
        return $o . '</Relationships>';
    }

    private function buildRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1"'
             . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
             . ' Target="xl/workbook.xml"/>'
             . '</Relationships>';
    }

    private function buildContentTypes(): string
    {
        $o = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
           . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
           . '<Default Extension="xml"  ContentType="application/xml"/>'
           . '<Override PartName="/xl/workbook.xml"'
           . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $i => $_) {
            $o .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $o .= '<Override PartName="/xl/sharedStrings.xml"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        $o .= '<Override PartName="/xl/styles.xml"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        return $o . '</Types>';
    }

    private function buildStyles(): string
    {
        $e = fn(string $c): string => 'FF' . ltrim($c, '#');

        // ── Fuentes (17, índices 0–16) ────────────────────────────────────
        $font = fn(int $sz, bool $bold, string $clr = ''): string =>
            '<font><sz val="' . $sz . '"/>'
            . ($bold ? '<b/>' : '')
            . ($clr  ? '<color rgb="' . $e($clr) . '"/>' : '')
            . '<name val="Calibri"/></font>';

        $fonts =
            $font(10, false)          // 0  normal
            . $font(14, true, 'FFFFFF')  // 1  título
            . $font(10, true, '1E3A5F')  // 2  info label
            . $font(10, false,'1E3A5F')  // 3  info valor
            . $font(10, true, 'FFFFFF')  // 4  sección / col header base
            . $font(10, true)            // 5  valor bold
            . $font(10, true, '166534')  // 6  moneda verde
            . $font(10, true, '92400E')  // 7  porcentaje naranja
            . $font(9,  true, 'FFFFFF')  // 8  col header
            . $font(9,  false)           // 9  celda normal
            . $font(9,  true, '166534')  // 10 OK / tot-cur
            . $font(9,  true, '991B1B')  // 11 NO
            . $font(9,  true)            // 12 pct / total
            . $font(9,  true, '1E40AF')  // 13 EV moneda
            . $font(8,  true, '0C4A6E')  // 14 P1 badge
            . $font(8,  true, 'FFFFFF')  // 15 P2 badge
            . $font(8,  true, '78350F'); // 16 EV badge

        // ── Rellenos (12, índices 0–11) ───────────────────────────────────
        $fill = fn(string $clr): string =>
            '<fill><patternFill patternType="solid"><fgColor rgb="' . $e($clr) . '"/></patternFill></fill>';

        $fills =
            '<fill><patternFill patternType="none"/></fill>'   // 0
            . '<fill><patternFill patternType="gray125"/></fill>' // 1
            . $fill('1E3A5F')   // 2 título
            . $fill('DBEAFE')   // 3 info
            . $fill('2563EB')   // 4 sección
            . $fill('1E40AF')   // 5 col header
            . $fill('DCFCE7')   // 6 OK
            . $fill('FEE2E2')   // 7 NO
            . $fill('F1F5F9')   // 8 total
            . $fill('BAE6FD')   // 9 P1
            . $fill('7C3AED')   // 10 P2
            . $fill('FDE68A');  // 11 EV

        // ── Bordes (11, índices 0–10) ─────────────────────────────────────
        $sides = fn(string $clr, string $sty = 'thin'): string =>
            '<left style="'   . $sty . '"><color rgb="' . $e($clr) . '"/></left>'
            . '<right style="'  . $sty . '"><color rgb="' . $e($clr) . '"/></right>'
            . '<top style="'    . $sty . '"><color rgb="' . $e($clr) . '"/></top>'
            . '<bottom style="' . $sty . '"><color rgb="' . $e($clr) . '"/></bottom>';

        $bdr = fn(string $clr, string $sty = 'thin'): string =>
            '<border>' . $sides($clr, $sty) . '<diagonal/></border>';

        $borders =
            '<border><left/><right/><top/><bottom/><diagonal/></border>'  // 0 ninguno
            . $bdr('CBD5E1')         // 1 label/valor
            . $bdr('E2E8F0')         // 2 celda
            . $bdr('94A3B8', 'medium') // 3 total
            . $bdr('BBF7D0')         // 4 OK
            . $bdr('FECACA')         // 5 NO
            . $bdr('BFDBFE')         // 6 EV moneda
            . $bdr('FCD34D')         // 7 EV badge
            . $bdr('7DD3FC')         // 8 P1 badge
            . $bdr('6D28D9')         // 9 P2 badge
            . $bdr('1D4ED8');        // 10 col header

        // ── Cell XFs (23, índices 0–22) ──────────────────────────────────
        $xf = function(
            int    $font,
            int    $fill,
            int    $border,
            int    $numFmt,
            string $hAlign = 'general',
            bool   $wrap   = false
        ): string {
            $attrs = ' xfId="0"'
                . ($font   > 0 ? ' applyFont="1"'         : '')
                . ($fill   > 0 ? ' applyFill="1"'         : '')
                . ($border > 0 ? ' applyBorder="1"'       : '')
                . ($numFmt > 0 ? ' applyNumberFormat="1"' : '')
                . ' applyAlignment="1"';
            $hA = $hAlign !== 'general' ? ' horizontal="' . $hAlign . '"' : '';
            $wA = $wrap ? ' wrapText="1"' : '';
            return '<xf numFmtId="' . $numFmt . '" fontId="' . $font
                . '" fillId="' . $fill . '" borderId="' . $border . '"' . $attrs . '>'
                . '<alignment' . $hA . ' vertical="center"' . $wA . '/>'
                . '</xf>';
        };

        $xfs =
            $xf(0,  0,  0,   0,   'general')        // 0  DEF
            . $xf(1,  2,  0,   0,   'center')         // 1  TITLE
            . $xf(2,  3,  1,   0,   'left')            // 2  INFO_LBL
            . $xf(3,  3,  1,   0,   'left')            // 3  INFO_VAL
            . $xf(4,  4,  1,   0,   'left')            // 4  SEC_HDR
            . $xf(0,  0,  1,   0,   'left')            // 5  LBL
            . $xf(5,  0,  1,   0,   'right')           // 6  VAL
            . $xf(6,  0,  1, 164,   'right')           // 7  CUR
            . $xf(7,  0,  1, 165,   'right')           // 8  PCT
            . $xf(8,  5, 10,   0,   'center', true)    // 9  COL_HDR
            . $xf(9,  0,  2,   0,   'center')          // 10 CELL
            . $xf(9,  0,  2,   0,   'left')            // 11 CELL_L
            . $xf(10, 6,  4,   0,   'center')          // 12 OK
            . $xf(11, 7,  5,   0,   'center')          // 13 NO
            . $xf(12, 0,  2, 165,   'center')          // 14 CELL_PCT
            . $xf(9,  0,  2, 164,   'right')           // 15 CELL_CUR
            . $xf(10, 6,  4, 164,   'right')           // 16 CUR_OK
            . $xf(13, 3,  6, 164,   'right')           // 17 CUR_EV
            . $xf(12, 8,  3,   0,   'center')          // 18 TOT
            . $xf(10, 8,  3, 164,   'right')           // 19 TOT_CUR
            . $xf(14, 9,  8,   0,   'center')          // 20 P1
            . $xf(15,10,  9,   0,   'center')          // 21 P2
            . $xf(16,11,  7,   0,   'center');         // 22 EV

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<numFmts count="2">'
             . '<numFmt numFmtId="164" formatCode="#,##0"/>'
             . '<numFmt numFmtId="165" formatCode="0&quot;%&quot;"/>'
             . '</numFmts>'
             . '<fonts count="17">'   . $fonts   . '</fonts>'
             . '<fills count="12">'   . $fills   . '</fills>'
             . '<borders count="11">' . $borders . '</borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellStyleXfs>'
             . '<cellXfs count="23">' . $xfs . '</cellXfs>'
             . '</styleSheet>';
    }
}
