<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

const USER_IMPORT_MAX_BYTES = 1048576;
const USER_IMPORT_MAX_ROWS = 100;

function import_xml(string $xml): SimpleXMLElement {
    $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
    if ($parsed === false) {
        throw new RuntimeException('The Excel file contains invalid worksheet data.');
    }
    return $parsed;
}

function import_cell_text(SimpleXMLElement $cell, array $sharedStrings): string {
    $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $type = (string)$cell['t'];
    if ($type === 's') {
        $value = $cell->xpath('./x:v');
        $index = isset($value[0]) ? (int)$value[0] : -1;
        return $sharedStrings[$index] ?? '';
    }
    if ($type === 'inlineStr') {
        $texts = $cell->xpath('.//x:is/x:t');
        return implode('', array_map('strval', $texts ?: []));
    }
    $value = $cell->xpath('./x:v');
    return isset($value[0]) ? (string)$value[0] : '';
}

function import_column_index(string $reference): int {
    if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
        return -1;
    }
    $index = 0;
    foreach (str_split(strtoupper($matches[1])) as $letter) {
        $index = $index * 26 + ord($letter) - 64;
    }
    return $index - 1;
}

function import_first_sheet_path(ZipArchive $archive): string {
    $workbookIndex = $archive->locateName('xl/workbook.xml');
    if ($workbookIndex === false) {
        return 'xl/worksheets/sheet1.xml';
    }
    $workbookStat = $archive->statIndex($workbookIndex);
    if ($workbookStat === false || $workbookStat['size'] > USER_IMPORT_MAX_BYTES) {
        throw new RuntimeException('The Excel workbook metadata is too large to import.');
    }
    $workbookContent = $archive->getFromIndex($workbookIndex);
    if (!is_string($workbookContent)) {
        throw new RuntimeException('The Excel workbook metadata could not be read.');
    }
    $workbook = import_xml($workbookContent);
    $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $sheets = $workbook->xpath('//x:sheets/x:sheet');
    if (!$sheets) {
        throw new RuntimeException('The Excel workbook has no worksheets.');
    }
    $relationshipId = (string)$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $relationshipsIndex = $archive->locateName('xl/_rels/workbook.xml.rels');
    if ($relationshipId === '' || $relationshipsIndex === false) {
        throw new RuntimeException('The first worksheet could not be located in the Excel workbook.');
    }
    $relationshipsStat = $archive->statIndex($relationshipsIndex);
    if ($relationshipsStat === false || $relationshipsStat['size'] > USER_IMPORT_MAX_BYTES) {
        throw new RuntimeException('The Excel worksheet references are too large to import.');
    }
    $relationshipsContent = $archive->getFromIndex($relationshipsIndex);
    if (!is_string($relationshipsContent)) {
        throw new RuntimeException('The Excel worksheet references could not be read.');
    }
    $relationships = import_xml($relationshipsContent);
    $relationships->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
    foreach ($relationships->xpath('//p:Relationship') ?: [] as $relationship) {
        if ((string)$relationship['Id'] !== $relationshipId) {
            continue;
        }
        if ((string)$relationship['TargetMode'] === 'External') {
            break;
        }
        $target = (string)$relationship['Target'];
        $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
        if (!str_starts_with($path, 'xl/worksheets/')
            || preg_match('~(?:^|/)\.\.(?:/|$)|\\\\~', $path) === 1) {
            break;
        }
        return $path;
    }
    throw new RuntimeException('The first worksheet could not be located in the Excel workbook.');
}

function import_spreadsheet_rows(ZipArchive $archive): array {
    if (!class_exists(SimpleXMLElement::class)) {
        throw new RuntimeException('The PHP SimpleXML extension is required to import Excel files.');
    }
    $sheetPath = import_first_sheet_path($archive);
    $sheetIndex = $archive->locateName($sheetPath);
    $sheetStat = $sheetIndex === false ? false : $archive->statIndex($sheetIndex);
    if ($sheetStat === false || $sheetStat['size'] > 5 * USER_IMPORT_MAX_BYTES) {
        throw new RuntimeException('The Excel file must contain a readable first worksheet under 5 MB.');
    }
    $sharedStrings = [];
    $stringsIndex = $archive->locateName('xl/sharedStrings.xml');
    if ($stringsIndex !== false) {
        $stat = $archive->statIndex($stringsIndex);
        if ($stat === false || $stat['size'] > 5 * USER_IMPORT_MAX_BYTES) {
            throw new RuntimeException('The Excel file contains an oversized shared-string table.');
        }
        $stringsContent = $archive->getFromIndex($stringsIndex);
        if (!is_string($stringsContent)) {
            throw new RuntimeException('The Excel file shared-string table could not be read.');
        }
        $stringsXml = import_xml($stringsContent);
        $stringsXml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($stringsXml->xpath('//x:si') ?: [] as $item) {
            $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $texts = $item->xpath('.//x:t');
            $sharedStrings[] = implode('', array_map('strval', $texts ?: []));
        }
    }

    $sheetContent = $archive->getFromIndex($sheetIndex);
    if (!is_string($sheetContent)) {
        throw new RuntimeException('The Excel file worksheet could not be read.');
    }
    $sheet = import_xml($sheetContent);
    $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = [];
    foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $row) {
        $row->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];
        foreach ($row->xpath('./x:c') ?: [] as $cell) {
            $column = import_column_index((string)$cell['r']);
            if ($column >= 0) {
                $cells[$column] = import_cell_text($cell, $sharedStrings);
            }
        }
        if ($cells) {
            ksort($cells);
            $rows[] = [(int)$row['r'], $cells];
            if (count($rows) > USER_IMPORT_MAX_ROWS + 1) {
                throw new RuntimeException('Import at most 100 staff accounts at a time.');
            }
        }
    }
    return $rows;
}

function read_user_import(string $path, string $extension): array {
    $size = filesize($path);
    if ($size === false || $size === 0 || $size > USER_IMPORT_MAX_BYTES) {
        throw new RuntimeException('Choose a non-empty import file no larger than 1 MB.');
    }

    $rows = [];
    if ($extension === 'csv') {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded CSV could not be read.');
        }
        try {
            $line = 0;
            while (($record = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $line++;
                if ($line === 1 && isset($record[0])) {
                    $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', $record[0]);
                }
                if (count(array_filter($record, static fn($value) => trim((string)$value) !== '')) === 0) {
                    continue;
                }
                $rows[] = [$line, array_map(static fn($value) => trim((string)$value), $record)];
                if (count($rows) > USER_IMPORT_MAX_ROWS + 1) {
                    throw new RuntimeException('Import at most 100 staff accounts at a time.');
                }
            }
        } finally {
            fclose($handle);
        }
    } elseif ($extension === 'xlsx') {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX import needs PHP Zip support. Enable extension=zip in php.ini and restart Apache, or import a CSV exported from Excel.');
        }
        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The uploaded file is not a readable .xlsx workbook.');
        }
        try {
            if ($archive->numFiles > 2000) {
                throw new RuntimeException('The Excel workbook contains too many internal files.');
            }
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $stat = $archive->statIndex($i);
                if ($stat === false || $stat['size'] > 5 * USER_IMPORT_MAX_BYTES) {
                    throw new RuntimeException('The Excel workbook contains an oversized internal file.');
                }
            }
            $rows = import_spreadsheet_rows($archive);
        } finally {
            $archive->close();
        }
    } else {
        throw new RuntimeException('Use a .csv or .xlsx file.');
    }

    if (!$rows) {
        throw new RuntimeException('The import file is empty.');
    }
    [$headerLine, $headerValues] = array_shift($rows);
    $headers = array_map(static fn($value) => strtolower(trim((string)$value)), $headerValues);
    $headerMap = [];
    foreach ($headers as $index => $header) {
        $key = match ($header) {
            'username' => 'username',
            'full_name', 'name' => 'full_name',
            'role' => 'role',
            default => '',
        };
        if ($key === '' || isset($headerMap[$key])) {
            throw new RuntimeException('Row ' . $headerLine . ': use only the headers username, full_name, and optional role.');
        }
        $headerMap[$key] = $index;
    }
    if (!isset($headerMap['username'], $headerMap['full_name'])) {
        throw new RuntimeException('The first row must include username and full_name headers.');
    }

    $accounts = [];
    foreach ($rows as [$line, $values]) {
        $username = trim((string)($values[$headerMap['username']] ?? ''));
        $fullName = trim((string)($values[$headerMap['full_name']] ?? ''));
        $roleIndex = $headerMap['role'] ?? null;
        $role = $roleIndex === null ? '' : strtolower(trim((string)($values[$roleIndex] ?? '')));
        if ($username === '' && $fullName === '' && $role === '') {
            continue;
        }
        $account = [
            'username' => $username,
            'full_name' => $fullName,
            'role' => $role === '' ? 'guard' : $role,
        ];
        if ($account['role'] === 'staff') {
            $account['role'] = 'guard';
        }
        $accounts[] = ['line' => $line, 'account' => $account];
    }
    if (!$accounts) {
        throw new RuntimeException('The import file has no staff accounts after its header row.');
    }
    return $accounts;
}

function validate_import_accounts(array $rows): array {
    $errors = [];
    $seen = [];
    foreach ($rows as $row) {
        $line = $row['line'];
        $account = $row['account'];
        if (preg_match('/^[A-Za-z0-9._-]{3,50}$/D', $account['username']) !== 1) {
            $errors[] = "Row $line: usernames must be 3-50 letters, numbers, dots, underscores, or dashes.";
        } elseif (isset($seen[strtolower($account['username'])])) {
            $errors[] = "Row $line: duplicate username in this file.";
        } else {
            $seen[strtolower($account['username'])] = true;
        }
        if ($account['full_name'] === '' || strlen($account['full_name']) > 100) {
            $errors[] = "Row $line: full_name is required and must be at most 100 characters.";
        }
        if (!in_array($account['role'], ['guard'], true)) {
            $errors[] = "Row $line: role must be guard or staff.";
        }
    }
    return $errors;
}
