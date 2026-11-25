<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Normalization\BaseRequestSchema;

function checkSchema(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function provider(?array $schema): BaseRequestSchema
{
    return new class($schema) extends BaseRequestSchema {
        public function __construct(private ?array $schema) {}
        protected function defineSchema(string $strFunctionName): ?array
        {
            if ($strFunctionName !== 'save') {
                throw new LogicException('Unsupported function from UDA.');
            }
            return $this->schema;
        }
    };
}

$row = ['fields' => ['name' => ['type' => 'string']]];
$table = ['fields' => ['id' => ['type' => 'int']], 'keys' => ['id' => ['on_add' => 'required']]];
$files = [
    'single' => ['min_count' => 0, 'max_size' => 2097152,
        'allowed_types' => ['jpg' => ['image/jpeg'], 'png' => ['image/png']]],
    'multiple' => ['min_count' => 1, 'max_count' => 5, 'max_size' => 10485760,
        'max_total_size' => 26214400, 'allowed_types' => ['pdf' => ['application/pdf']]],
];
$callback = static function (array $row): array { throw new RuntimeException('Must not run callback.'); };
$valid = [null, ['query' => null], ['post_json_fields' => null], ['files' => null],
    ['query' => $row], ['post' => $row + ['row_normalizer' => $callback]],
    ['path_params' => ['fields' => [3 => ['type' => 'int'], 7 => ['type' => 'string']]]],
    ['json' => ['relations' => $table]], ['files' => $files],
    // Metadata order must not matter; callback must not be executed.
    ['post_json_fields' => ['relations'], 'post' => $row, 'json' => ['relations' => $table]],
    ['path_params' => null, 'query' => null, 'post' => null, 'json' => null, 'files' => null],
];
foreach ($valid as $i => $schema) {
    checkSchema(provider($schema)->createSchema('save') === $schema, "Valid schema $i changed or rejected.");
}

$invalid = [[], ['unknown' => null], ['segments' => null], ['json_fields_in_post' => null],
    ['query' => []], ['post' => []], ['path_params' => []], ['json' => []], ['files' => false],
    ['post_json_fields' => []], ['query' => false],
    ['path_params' => $row], ['query' => ['fields' => [0 => ['type' => 'int']]]],
    ['post' => ['fields' => ['0' => ['type' => 'int']]]],
    ['path_params' => ['fields' => [-1 => ['type' => 'int']]]],
    ['json' => ['relations' => $row]], ['json' => ['relations' => null]], ['json' => [0 => $table]],
    ['post_json_fields' => ['relations']], ['post_json_fields' => ['relations'], 'json' => null],
    ['post_json_fields' => ['relations', 'relations'], 'json' => ['relations' => $table]],
    ['post_json_fields' => [1 => 'relations'], 'json' => ['relations' => $table]],
    ['post_json_fields' => [null]],
    ['post_json_fields' => ['relations'], 'json' => ['relations' => $table],
        'post' => ['fields' => ['relations' => ['type' => 'string']]]],
];
foreach ($invalid as $i => $schema) {
    try {
        provider($schema)->createSchema('save');
    } catch (InvalidArgumentException $e) {
        checkSchema(str_contains($e->getMessage(), 'Invalid request schema at '), 'Missing diagnostic path.');
        continue;
    }
    throw new RuntimeException("Invalid request schema $i accepted.");
}
try {
    provider(null)->createSchema('unsupported');
    throw new RuntimeException('UDA exception swallowed.');
} catch (LogicException $e) {
    checkSchema($e->getMessage() === 'Unsupported function from UDA.', 'UDA exception changed.');
}

// Upload policy normalization is now owned by UploadFilesSchema.
checkSchema(provider(['files' => []])->createSchema('save') === ['files' => []], 'Empty policy tree rejected.');
$input = ['files' => ['documents' => ['max_size' => '1024', 'min_count' => '1']]];
$output = provider($input)->createSchema('save');
checkSchema($output['files']['documents'] === ['max_size' => 1024, 'min_count' => 1], 'Upload policy not normalized.');
checkSchema($input['files']['documents']['max_size'] === '1024', 'Original schema changed.');
try {
    provider(['files' => ['documents' => ['min_count' => 2, 'max_count' => 1]]])->createSchema('save');
    throw new RuntimeException('Invalid upload policy accepted.');
} catch (LogicException $e) {
    checkSchema(str_contains($e->getMessage(), 'err_number=1402'), 'Upload schema diagnostic changed.');
}
echo "PASS: request schema validation and upload policy normalization.\n";
