<?php
declare(strict_types=1);

namespace Core\Normalization;

use InvalidArgumentException;

/**
 * Schema theo function của một controller; không đọc hoặc normalize request.
 * null: function không cần normalization. []: schema không hợp lệ.
 * Nguồn khuyết/null được bỏ qua; array không rỗng chứa toàn null vẫn hợp lệ.
 * UDA tự xử lý function không hỗ trợ trong defineSchema(); lớp nền không suy đoán.
 *
 * path_params/query/post: RowFieldSchema. path_params dùng int >= 0,
 * query/post dùng string không rỗng (xét key thực tế sau ép kiểu key của PHP).
 * json: map string không rỗng -> TableFieldSchema, không hỗ trợ RowFieldSchema.
 * files: array cây policy của UploadFilesSchema; được normalize khi tạo schema.
 * post_json_fields: list tên field POST, chuyển sang json giữ nguyên tên;
 * mỗi tên phải có TableFieldSchema trong json và không có trong post.fields.
 * Các nguồn khác giữ nguyên schema; files áp dụng contract của UploadFilesSchema.
 */
abstract class BaseRequestSchema
{
    public const FIELD_PATH_PARAMS = 'path_params';
    public const FIELD_QUERY = 'query';
    public const FIELD_POST = 'post';
    public const FIELD_JSON = 'json';
    public const FIELD_FILES = 'files';
    public const FIELD_POST_JSON_FIELDS = 'post_json_fields';

    final public function createSchema(string $strFunctionName): ?array
    {
        $arrSchema = $this->defineSchema($strFunctionName);
        if ($arrSchema !== null) {
            $this->validateSchema($arrSchema);
        }
        return $arrSchema;
    }

    abstract protected function defineSchema(string $strFunctionName): ?array;

    private function validateSchema(array &$arrSchema): void
    {
        if ($arrSchema === []) {
            self::invalid('schema', 'Empty array is not allowed; use null.');
        }
        $allowed = [self::FIELD_PATH_PARAMS, self::FIELD_QUERY, self::FIELD_POST,
            self::FIELD_JSON, self::FIELD_FILES, self::FIELD_POST_JSON_FIELDS];
        foreach ($arrSchema as $source => $schema) {
            if (!in_array($source, $allowed, true)) {
                self::invalid((string) $source, 'Unknown schema key.');
            }
            if ($schema === null) {
                continue;
            }
            if (in_array($source, [self::FIELD_PATH_PARAMS, self::FIELD_QUERY, self::FIELD_POST], true)) {
                if (!RowFieldSchema::isValid($schema)) {
                    self::invalid($source, 'Invalid RowFieldSchema.');
                }
                foreach ($schema[RowFieldSchema::FIELD_FIELDS] as $key => $_) {
                    $valid = $source === self::FIELD_PATH_PARAMS
                        ? is_int($key) && $key >= 0 : is_string($key) && $key !== '';
                    if (!$valid) {
                        self::invalid($source . '.fields.' . $key, 'Invalid field key for source.');
                    }
                }
            } elseif ($source === self::FIELD_JSON) {
                if (!is_array($schema) || $schema === []) {
                    self::invalid($source, 'Expected a nonempty map of TableFieldSchema.');
                }
                foreach ($schema as $name => $table) {
                    if (!is_string($name) || $name === '' || !TableFieldSchema::isValid($table)) {
                        self::invalid($source . '.' . $name, 'Invalid table name or TableFieldSchema.');
                    }
                }
            } elseif ($source === self::FIELD_FILES) {
                if (!is_array($schema)) {
                    self::invalid($source, 'Expected an UploadFilesSchema policy array.');
                }
                $uploadSchema = new UploadFilesSchema($schema);
                $uploadSchema->normalize();
                $arrSchema[$source] = $uploadSchema->getData();
            }
        }

        $names = $arrSchema[self::FIELD_POST_JSON_FIELDS] ?? null;
        if ($names === null) {
            return;
        }
        if (!is_array($names) || $names === []) {
            self::invalid(self::FIELD_POST_JSON_FIELDS, 'Expected a nonempty list.');
        }
        $seen = [];
        $index = 0;
        foreach ($names as $key => $name) {
            if ($key !== $index++ || !is_string($name) || $name === '' || in_array($name, $seen, true)) {
                self::invalid(self::FIELD_POST_JSON_FIELDS, 'Expected unique nonempty string names in a list.');
            }
            $seen[] = $name;
            if (!array_key_exists($name, $arrSchema[self::FIELD_JSON] ?? [])) {
                self::invalid(self::FIELD_POST_JSON_FIELDS . '.' . $name, 'Missing corresponding json schema.');
            }
            if (array_key_exists($name, $arrSchema[self::FIELD_POST][RowFieldSchema::FIELD_FIELDS] ?? [])) {
                self::invalid(self::FIELD_POST_JSON_FIELDS . '.' . $name, 'Field also declared in post.fields.');
            }
        }
    }

    private static function invalid(string $path, string $reason): void
    {
        throw new InvalidArgumentException('Invalid request schema at ' . $path . ': ' . $reason);
    }
}
