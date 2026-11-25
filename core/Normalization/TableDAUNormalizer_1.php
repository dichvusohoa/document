<?php
declare(strict_types=1);

namespace Core\Normalization;

use Core\Foundation\ExtArray;
use InvalidArgumentException;

/**
 * Normalize DAU của một bảng, PHP >= 8.0. Không thực thi SQL.
 * Một step JsonNormalizer; mỗi row là một composite leaf.
 *
 * Cấu trúc sai gây InvalidArgumentException. Lỗi row dừng row hiện tại;
 * lỗi field vẫn tiếp tục các field khác. Data giữ mọi biến đổi đã hoàn thành,
 * field lỗi giữ input. Extra dùng contract của JsonNormalizer.
 * Key trong update.values là giá trị mới, có lỗi cấp field như field thường.
 * Nullable/default của key được AtomicNormalizer xử lý nguyên trạng.
 *
 * Presence và key được xét theo thứ tự schema; values theo thứ tự payload.
 * Row error dùng mã riêng dưới đây; field error dùng mã AtomicNormalizer.
 * Giữ index và action khuyết. Không kiểm tra DB uniqueness hoặc duplicate operations.
 * normalize() kế thừa cache kết quả và khóa instance sau exception từ JsonNormalizer.
 */
final class TableDAUNormalizer
{
    public const ACTION_DELETE = 'delete';
    public const ACTION_ADD = 'add';
    public const ACTION_UPDATE = 'update';
    public const FIELD_KEYS = 'keys';
    public const FIELD_VALUES = 'values';

    public const ERR_MISSING_KEY = 1000;
    public const ERR_FORBIDDEN_KEY = 1001;
    public const ERR_MISSING_FIELD = 1002;
    public const ERR_INVALID_KEY = 1003;

    private array $fields;
    private array $keys;
    private JsonNormalizer $normalizer;

    public function __construct(array $arrData, array $arrTableFieldSchema)
    {
        if (!TableFieldSchema::isValid($arrTableFieldSchema)) {
            throw new InvalidArgumentException('Invalid TableFieldSchema.');
        }
        $this->fields = $arrTableFieldSchema[TableFieldSchema::FIELD_FIELDS];
        $this->keys = $arrTableFieldSchema[TableFieldSchema::FIELD_KEYS];
        $this->normalizer = (new JsonNormalizer($arrData))->addStep(
            function (array $path, mixed $value): int {
                return $this->defineLeaf($path, $value);
            },
            function (array $path, mixed $value): array {
                return $this->processRow($path[0], $value);
            }
        );
    }

    private function __clone() {}

    public function normalize(): array
    {
        return $this->normalizer->normalize();
    }

    private function defineLeaf(array $path, mixed $value): int
    {
        if ($path === []) {
            if (!is_array($value) || $value === []) {
                throw new InvalidArgumentException('DAU requires at least one action.');
            }
            foreach ($value as $action => $_) {
                if (!in_array($action, [self::ACTION_DELETE, self::ACTION_ADD, self::ACTION_UPDATE], true)) {
                    throw new InvalidArgumentException('Unknown DAU action.');
                }
            }
            return ExtArray::BRANCH_OR_PETIOLE;
        }
        if (count($path) === 1) {
            if (!is_array($value) || $value === []) {
                throw new InvalidArgumentException('An action must be a nonempty list of rows.');
            }
            $expected = 0;
            foreach ($value as $index => $_) {
                if ($index !== $expected++) {
                    throw new InvalidArgumentException('Row indices must be consecutive integers starting at zero.');
                }
            }
            return ExtArray::BRANCH_OR_PETIOLE;
        }
        if (count($path) !== 2 || !is_array($value)) {
            throw new InvalidArgumentException('Each DAU row must be an array.');
        }
        // Validate the entire row shape before any presence/value errors.
        if ($path[0] === self::ACTION_UPDATE) {
            if (count($value) !== 2 || !array_key_exists(self::FIELD_KEYS, $value)
                || !array_key_exists(self::FIELD_VALUES, $value)
                || !is_array($value[self::FIELD_KEYS]) || !is_array($value[self::FIELD_VALUES])
                || $value[self::FIELD_VALUES] === []) {
                throw new InvalidArgumentException('Update requires keys and nonempty values arrays.');
            }
            $this->validateNames($value[self::FIELD_KEYS], $this->keys);
            $this->validateNames($value[self::FIELD_VALUES], $this->fields);
        } else {
            $this->validateNames($value, $path[0] === self::ACTION_DELETE ? $this->keys : $this->fields);
        }
        return ExtArray::COMPOSITE_LEAF;
    }

    private function validateNames(array $values, array $allowed): void
    {
        foreach ($values as $name => $_) {
            if (!is_string($name) || !array_key_exists($name, $allowed)) {
                throw new InvalidArgumentException('Unknown field in DAU row: ' . (string) $name);
            }
        }
    }

    private function processRow(string $action, array $row): array
    {
        $identity = $action === self::ACTION_UPDATE ? $row[self::FIELD_KEYS] : $row;
        foreach ($this->keys as $name => $options) {
            $present = array_key_exists($name, $identity);
            if ($action !== self::ACTION_ADD) {
                if (!$present) {
                    return [self::record([], self::ERR_MISSING_KEY, $row)];
                }
                continue;
            }
            $policy = $options[TableFieldSchema::FIELD_ON_ADD];
            if ($present && $policy === TableFieldSchema::ON_ADD_FORBIDDEN) {
                return [self::record([], self::ERR_FORBIDDEN_KEY, $row)];
            }
            if (!$present && ($policy === TableFieldSchema::ON_ADD_REQUIRED
                || ($policy === TableFieldSchema::ON_ADD_OPTIONAL && !$this->hasAuto($name)))) {
                return [self::record([], self::ERR_MISSING_KEY, $row)];
            }
        }
        if ($action === self::ACTION_ADD) {
            foreach ($this->fields as $name => $_) {
                if (!array_key_exists($name, $this->keys) && !array_key_exists($name, $row)
                    && !$this->hasAuto($name)) {
                    return [self::record([], self::ERR_MISSING_FIELD, $row)];
                }
            }
        }
        // Normalize identifying keys before all ordinary/new values.
        foreach ($this->keys as $name => $_) {
            if (!array_key_exists($name, $identity)) {
                continue;
            }
            $result = AtomicNormalizer::normalize($identity[$name], $this->fields[$name]);
            if ($result[AtomicNormalizer::FIELD_ERR_NUMBER] !== AtomicNormalizer::ERR_NONE) {
                return [self::record([], self::ERR_INVALID_KEY, $row)];
            }
            if ($action === self::ACTION_UPDATE) {
                $row[self::FIELD_KEYS][$name] = $result[AtomicNormalizer::FIELD_VALUE];
            } else {
                $row[$name] = $result[AtomicNormalizer::FIELD_VALUE];
            }
        }
        $errors = [];
        if ($action !== self::ACTION_DELETE) {
            $values = $action === self::ACTION_UPDATE ? $row[self::FIELD_VALUES] : $row;
            foreach ($values as $name => $value) {
                if ($action === self::ACTION_ADD && array_key_exists($name, $this->keys)) {
                    continue;
                }
                $schema = $this->fields[$name];
                if ($action === self::ACTION_ADD && $value === null
                    && !($schema[AtomicSchema::FIELD_NULLABLE] ?? false)
                    && !array_key_exists(AtomicSchema::FIELD_DEFAULT_VALUE, $schema)
                    && $this->hasAuto($name)) {
                    unset($row[$name]);
                    continue;
                }
                $result = AtomicNormalizer::normalize($value, $schema);
                $normalized = $result[AtomicNormalizer::FIELD_VALUE];
                $relative = $action === self::ACTION_UPDATE ? [self::FIELD_VALUES, $name] : [$name];
                if ($action === self::ACTION_UPDATE) {
                    $row[self::FIELD_VALUES][$name] = $normalized;
                } else {
                    $row[$name] = $normalized;
                }
                if ($result[AtomicNormalizer::FIELD_ERR_NUMBER] !== AtomicNormalizer::ERR_NONE) {
                    $errors[] = self::record($relative, $result[AtomicNormalizer::FIELD_ERR_NUMBER], $normalized);
                }
            }
        }
        // Zero at [] means no row-level error; child errors still make Response fail.
        array_unshift($errors, self::record([], AtomicNormalizer::ERR_NONE, $row));
        return $errors;
    }

    private function hasAuto(string $name): bool
    {
        return ($this->fields[$name][AtomicSchema::FIELD_AUTO_VALUE] ?? false) === true;
    }

    private static function record(array $path, int $code, mixed $value): array
    {
        return [JsonNormalizer::FIELD_PATH => $path,
            JsonNormalizer::FIELD_ERR_NUMBER => $code, JsonNormalizer::FIELD_VALUE => $value];
    }
}
