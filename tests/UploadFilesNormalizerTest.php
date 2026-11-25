<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Foundation\ExtArray;
use Core\Http\Request;
use Core\Http\Response;
use Core\Normalization\UploadFilesNormalizer as Normalizer;
use Core\Normalization\UploadFilesSchema;

function sameUpload(mixed $mixExpected, mixed $mixActual): void
{
    if ($mixExpected !== $mixActual) {
        throw new RuntimeException('Expected ' . var_export($mixExpected, true) . ', got ' . var_export($mixActual, true));
    }
}
function errorCodes(array $arrResult): array
{
    sameUpload(Response::SERVER_LOGIC_ERR_STATUS, $arrResult['status']);
    sameUpload(null, $arrResult['data']);
    return array_column($arrResult['extra'], 'err_number');
}

$strTmp = tempnam(sys_get_temp_dir(), 'upload_normalizer_');
try {
    file_put_contents($strTmp, "Plain text upload fixture.\n");
    $arrFile = ['name' => 'NOTES.TXT', 'type' => 'application/fake', 'tmp_name' => $strTmp,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($strTmp), 'full_path' => 'folder/NOTES.TXT'];
    $iSize = $arrFile['size'];
    $arrNone = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'full_path' => ''];
    $schema = new UploadFilesSchema();
    $schema->setValue([], ['max_size' => $iSize, 'max_count' => 2, 'max_total_size' => $iSize * 2,
        'allowed_types' => ['txt' => ['text/plain']]]);
    $schema->setValue(['docs'], ['min_count' => 2]);
    $schema->setValue(['docs', 8], ['max_size' => $iSize + 1]);
    $schema->normalize();
    $arrInput = ['docs' => [3 => $arrFile, 8 => $arrFile, 12 => $arrNone]];
    $arrBefore = $schema->getData();
    $normalizer = new Normalizer($arrInput, $schema);
    $arrOK = ['status' => Response::SERVER_OK_STATUS, 'data' => $arrInput, 'extra' => null];
    sameUpload($arrOK, $normalizer->normalize());
    sameUpload($arrOK, $normalizer->normalize());
    $arrReturned = $normalizer->normalize();
    $arrReturned['data']['docs'][3]['name'] = 'changed-output.txt';
    sameUpload($arrOK, $normalizer->normalize());
    sameUpload($arrBefore, $schema->getData());

    // Size override does not erase inherited types; type override does not erase size.
    $schema->setValue(['docs', 8], ['max_size' => $iSize - 1]);
    $arrResult = (new Normalizer($arrInput, $schema))->normalize();
    sameUpload([Normalizer::ERR_MAX_SIZE], errorCodes($arrResult));
    sameUpload(['docs', 8], $arrResult['extra'][0]['rule_path']);
    $schema->setValue(['docs', 8], ['allowed_types' => ['txt' => ['application/pdf']]]);
    sameUpload([Normalizer::ERR_ALLOWED_TYPES], errorCodes((new Normalizer($arrInput, $schema))->normalize()));

    $schema = new UploadFilesSchema();
    $schema->setValue([], ['max_size' => 1, 'max_count' => 1, 'max_total_size' => $iSize]);
    $schema->setValue(['docs'], ['max_size' => $iSize, 'max_count' => 3, 'max_total_size' => $iSize * 3]);
    $schema->setValue(['missing', 'nested'], ['min_count' => 1]);
    $schema->normalize();
    $arrResult = (new Normalizer($arrInput, $schema))->normalize();
    sameUpload([Normalizer::ERR_MAX_COUNT, Normalizer::ERR_MAX_TOTAL_SIZE, Normalizer::ERR_MIN_COUNT], errorCodes($arrResult));
    sameUpload([[], [], ['missing', 'nested']], array_column($arrResult['extra'], 'path'));
    sameUpload([2, $iSize * 2, 0], array_column($arrResult['extra'], 'value'));

    $schema = new UploadFilesSchema();
    $schema->setValue([], ['min_count' => 1, 'max_size' => 1, 'allowed_types' => ['pdf' => ['application/pdf']]]);
    $schema->normalize();
    sameUpload([Normalizer::ERR_MIN_COUNT], errorCodes((new Normalizer([], $schema))->normalize()));
    sameUpload([Normalizer::ERR_MIN_COUNT], errorCodes((new Normalizer(['none' => $arrNone], $schema))->normalize()));
    foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION, 999] as $iError) {
        $arrFailed = array_replace($arrFile, ['error' => $iError]);
        $arrResult = (new Normalizer(['failed' => $arrFailed], $schema))->normalize();
        sameUpload([Normalizer::ERR_UPLOAD], errorCodes($arrResult));
        sameUpload($iError, $arrResult['extra'][0]['value']);
    }

    $schema = new UploadFilesSchema();
    $schema->setValue([], ['allowed_types' => ['txt' => ['text/plain'], 'pdf' => ['application/pdf']]]);
    $schema->normalize();
    foreach ([['name' => 'fake.pdf', 'type' => 'application/pdf'], ['name' => 'file.exe'], ['name' => 'no_extension']] as $arrChange) {
        sameUpload([Normalizer::ERR_ALLOWED_TYPES], errorCodes((new Normalizer(['file' => array_replace($arrFile, $arrChange)], $schema))->normalize()));
    }
    foreach (['', $strTmp . '.missing', 'php://memory', "bad\0path"] as $strBadPath) {
        sameUpload([Normalizer::ERR_READ_FILE], errorCodes((new Normalizer(['file' => array_replace($arrFile, ['tmp_name' => $strBadPath])], $schema))->normalize()));
    }
    foreach ([['size' => -1], ['size' => '1'], ['error' => '0'], ['name' => 1], ['full_path' => null]] as $arrChange) {
        sameUpload([Normalizer::ERR_STRUCTURE], errorCodes((new Normalizer(['file' => array_replace($arrFile, $arrChange)], $schema))->normalize()));
    }
    foreach (['bad', ['name' => 'a.pdf'], array_diff_key($arrFile, ['full_path' => true]),
        $arrFile + ['unknown' => 'x']] as $mixBad) {
        try {
            (new Normalizer(['file' => $mixBad], $schema))->normalize();
        } catch (LogicException $e) {
            continue;
        }
        throw new RuntimeException('Malformed tree must throw.');
    }
    $arrCollision = array_fill_keys(['name', 'full_path', 'type', 'tmp_name', 'error', 'size'], $arrFile);
    sameUpload([Normalizer::ERR_STRUCTURE], errorCodes((new Normalizer(['group' => $arrCollision], $schema))->normalize()));
    $arrIgnored = array_replace($arrNone, ['name' => [], 'size' => 'invalid']);
    $schemaEmpty = new UploadFilesSchema();
    sameUpload(['none' => $arrIgnored], (new Normalizer(['none' => $arrIgnored], $schemaEmpty))->normalize()['data']);

    // Empty schema and structural groups named like upload attributes.
    $schema = new UploadFilesSchema();
    $arrGroups = ['name' => ['error' => [7 => $arrFile]], 'empty' => []];
    sameUpload($arrGroups, (new Normalizer($arrGroups, $schema))->normalize()['data']);
    sameUpload(['status' => Response::SERVER_OK_STATUS, 'data' => [], 'extra' => null], (new Normalizer([], $schema))->normalize());

    ExtArray::setPetioleName('__policy__');
    $schema = new UploadFilesSchema();
    $schema->setValue([], ['max_count' => 1]);
    $schema->setValue(['docs'], ['min_count' => 2]);
    $schema->normalize();
    sameUpload([Normalizer::ERR_MAX_COUNT], errorCodes((new Normalizer($arrInput, $schema))->normalize()));
    ExtArray::setPetioleName(ExtArray::PETIOLE_DEFAULT_NAME);

    // Constructor keeps ordinary array values and does not normalize caller's schema.
    $arrRawSchema = ['max_size' => '100'];
    $schema = new UploadFilesSchema($arrRawSchema);
    new Normalizer([], $schema);
    sameUpload('100', $arrRawSchema['max_size']);
    $schema->normalize();
    $arrPreparedInput = ['file' => $arrFile];
    $normalizer = new Normalizer($arrPreparedInput, $schema);
    $arrSavedFile = $arrFile;
    $arrFile['name'] = 'changed';
    sameUpload(['file' => $arrSavedFile], $normalizer->normalize()['data']);

    // Integration with Request's native PHP upload transpose.
    $arrSavedGlobals = [$_GET, $_POST, $_FILES, $_SERVER];
    try {
        $_GET = $_POST = [];
        $_SERVER = ['CONTENT_TYPE' => 'multipart/form-data'];
        $_FILES = [];
        foreach ($arrSavedFile as $strAttribute => $mixValue) {
            $_FILES['docs'][$strAttribute] = [5 => $mixValue];
        }
        $request = new Request();
        sameUpload(['docs' => [5 => $arrSavedFile]], (new Normalizer($request->file(), $schema))->normalize()['data']);
    } finally {
        [$_GET, $_POST, $_FILES, $_SERVER] = $arrSavedGlobals;
    }
} finally {
    ExtArray::setPetioleName(ExtArray::PETIOLE_DEFAULT_NAME);
    unlink($strTmp);
}
echo "UploadFilesNormalizer tests passed.\n";
