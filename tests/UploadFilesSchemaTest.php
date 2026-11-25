<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Foundation\ExtArray;
use Core\Normalization\UploadFilesSchema;

function checkUpload(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$schema = new UploadFilesSchema();
$schema->setValue([], ['max_size' => ' 1024 ', 'allowed_types' => [' PDF ' => [' APPLICATION/PDF ']]]);
$schema->setValue(['documents'], ['min_count' => '1', 'max_count' => '5']);
$schema->setValue(['documents', 'contracts'], ['max_size' => '2048']);
$schema->normalize();
checkUpload($schema->getValue([]) === ['max_size' => 1024, 'allowed_types' => ['pdf' => ['application/pdf']]], 'Root policy normalization failed.');
checkUpload($schema->getValue(['documents']) === ['min_count' => 1, 'max_count' => 5], 'Petiole policy failed.');
checkUpload($schema->getValue(['documents', 'contracts']) === ['max_size' => 2048], 'Missing fields were filled.');
$before = $schema->getData();
$schema->normalize();
checkUpload($schema->getData() === $before, 'Normalization not idempotent.');

$raw = ['docs' => ['min_count' => '2', 'max_count' => '2']];
$wrapped = new UploadFilesSchema($raw);
$wrapped->normalize();
checkUpload($raw['docs'] === ['min_count' => 2, 'max_count' => 2], 'Traversal did not commit normalized leaf.');

$badPolicies = [
    ['min_count' => -1], ['max_count' => 0], ['max_size' => null],
    ['max_size' => 1.5], ['max_size' => '1.5'], ['max_total_size' => 'bad'],
    ['min_count' => '5', 'max_count' => '4'], ['max_size' => 10, 'unknown' => []],
    ['allowed_types' => []], ['allowed_types' => ['pdf' => ['application/*']]],
    ['allowed_types' => ['pdf' => [1 => 'application/pdf']]],
    ['allowed_types' => ['PDF' => ['application/pdf'], 'pdf' => ['application/pdf']]],
];
foreach ($badPolicies as $policy) {
    foreach (['set', 'traverse'] as $mode) {
        try {
            $raw = ['docs' => $policy];
            $tree = $mode === 'set' ? new UploadFilesSchema() : new UploadFilesSchema($raw);
            if ($mode === 'set') { $tree->setValue(['docs'], $policy); }
            else { $tree->normalize(); }
        } catch (LogicException $e) {
            checkUpload($e->getCode() > 0 && str_contains($e->getMessage(), 'path=')
                && str_contains($e->getMessage(), 'docs'), 'Missing error code/path.');
            continue;
        }
        throw new RuntimeException('Invalid policy accepted: ' . json_encode($policy));
    }
}
try {
    $schema->setValue(['max_size'], ['min_count' => 0]);
    throw new RuntimeException('Reserved path accepted.');
} catch (LogicException $e) {
    checkUpload($e->getCode() === UploadFilesSchema::ERR_RESERVED_PATH, 'Wrong path error.');
}

ExtArray::setPetioleName('__policy__');
try {
    $tree = new UploadFilesSchema();
    $tree->setValue([], ['max_size' => '100']);
    $tree->setValue(['docs'], ['max_count' => '2']);
    $tree->normalize();
    checkUpload(isset($tree->getData()['__policy__']) && $tree->getValue([])['max_size'] === 100, 'Custom petiole failed.');
} finally {
    ExtArray::setPetioleName(ExtArray::PETIOLE_DEFAULT_NAME);
}
echo "UploadFilesSchema tests passed.\n";
