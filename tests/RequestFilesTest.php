<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Foundation\ExtArray;
use Core\Http\Request;

function same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function fixture(array $files): Request {
    $saved = [$_GET, $_POST, $_FILES, $_SERVER];
    try {
        $_GET = $_POST = [];
        $_SERVER = ['CONTENT_TYPE' => 'multipart/form-data'];
        $_FILES = $files;
        $request = new Request();
        same($files, $_FILES);
        return $request;
    } finally {
        [$_GET, $_POST, $_FILES, $_SERVER] = $saved;
    }
}

$globals = $_FILES;
$file = ['name' => 'a.pdf', 'full_path' => 'folder/a.pdf', 'type' => 'application/pdf',
    'tmp_name' => '/tmp/upload', 'error' => 0, 'size' => 12];
$raw = ['avatar' => $file];
foreach ($file as $attribute => $value) {
    $raw['documents'][$attribute] = ['group' => [3 => $value, 8 => $value]];
}
$request = fixture($raw);
$expected = ['avatar' => $file, 'documents' => ['group' => [3 => $file, 8 => $file]]];
same($expected, $request->file());
// Reads and prepared writes must not transform files again.
same($expected, $request->file());
$request->set('files', ['normalized' => $file]);
same(['normalized' => $file], $request->file());

$request = fixture([]);
same([], $request->file());

$noFile = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
$request = fixture(['avatar' => $noFile]);
same(['avatar' => $noFile], $request->file());

foreach ([['avatar' => ['unexpected' => 'x']], ['avatar' => ['name' => null]],
    ['avatar' => 'x'], [ExtArray::PETIOLE_DEFAULT_NAME => $file],
    ['avatar' => ['name' => [ExtArray::PETIOLE_DEFAULT_NAME => 'x']]]] as $bad) {
    // Invalid files fail during construction, without changing superglobals.
    $input = ['valid' => $file] + $bad;
    try {
        fixture($input);
        throw new RuntimeException('Expected UnexpectedValueException');
    } catch (UnexpectedValueException $e) {
        same($globals, $_FILES);
    }
}
ExtArray::setPetioleName('_reserved');
try {
    try {
        fixture(['_reserved' => $file]);
        throw new RuntimeException('Expected custom petiole rejection');
    } catch (UnexpectedValueException $e) {
        same($globals, $_FILES);
    }
} finally {
    ExtArray::setPetioleName(ExtArray::PETIOLE_DEFAULT_NAME);
}
same($globals, $_FILES);
echo "PASS: constructor upload transpose, sparse nested keys, empty/no-file sources, prepared writes, failure isolation and active petiole.\n";
