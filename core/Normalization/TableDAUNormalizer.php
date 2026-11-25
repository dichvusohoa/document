<?php
declare(strict_types=1);

namespace Core\Normalization;

use Closure;
use Core\Foundation\ExtArray;
use InvalidArgumentException;
use ReflectionReference;
use UnexpectedValueException;

/**
 * Normalize DAU của một bảng, PHP >= 8.0. Không thực thi SQL.
 * Một step JsonNormalizer; mỗi row là một composite leaf.
 *
 * Cấu trúc sai gây InvalidArgumentException. Lỗi row dừng row hiện tại;
 * lỗi field vẫn tiếp tục các field khác. Data giữ mọi biến đổi đã hoàn thành,
 * field lỗi giữ input. Extra dùng contract của JsonNormalizer.
 * row_normalizer tùy chọn chạy sau khi row không có lỗi presence/key/atomic,
 * cho cả DAU. Trả đúng err_number/value: mã 0 hoặc mã custom 1300–1399.
 * Callback thành công thay thế row sau kiểm tra contract DAU, không chạy lại atomic.
 * Callback lỗi giữ row trước callback; value trả về chỉ dùng làm thông tin lỗi.
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

    // Reserved hard errors: 1200–1299; custom callback errors: 1300–1399.
    public const ERR_MISSING_KEY = 1200;
    public const ERR_FORBIDDEN_KEY = 1201;
    public const ERR_MISSING_FIELD = 1202;
    public const ERR_INVALID_KEY = 1203;
    public const ROW_NORMALIZER_ERR_MIN = 1300;
    public const ROW_NORMALIZER_ERR_MAX = 1399;

    private array $fields;
    private array $keys;
    private JsonNormalizer $normalizer;
    private ?Closure $rowNormalizer;

    public function __construct(array $arrData, array $arrTableFieldSchema)
    {
        if (!TableFieldSchema::isValid($arrTableFieldSchema)) {
            throw new InvalidArgumentException('Invalid TableFieldSchema.');
        }
        $this->fields = $arrTableFieldSchema[TableFieldSchema::FIELD_FIELDS];
        $this->keys = $arrTableFieldSchema[TableFieldSchema::FIELD_KEYS];
        $this->rowNormalizer = $arrTableFieldSchema[TableFieldSchema::FIELD_ROW_NORMALIZER] ?? null;
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
        if ($errors === [] && $this->rowNormalizer !== null) {
            $validation = ($this->rowNormalizer)($action, $row);
            $this->validateRowResult($validation);
            $code = $validation[AtomicNormalizer::FIELD_ERR_NUMBER];
            if ($code !== AtomicNormalizer::ERR_NONE) {
                return [self::record([], $code, $row, $validation[AtomicNormalizer::FIELD_VALUE])];
            }
            $normalizedRow = $validation[AtomicNormalizer::FIELD_VALUE];
            $this->validateNormalizedRow($action, $row, $normalizedRow);
            $row = $normalizedRow;
        }
        // Zero at [] means no row-level error; child errors still make Response fail.
        array_unshift($errors, self::record([], AtomicNormalizer::ERR_NONE, $row));
        return $errors;
    }

    private function hasAuto(string $name): bool
    {
        return ($this->fields[$name][AtomicSchema::FIELD_AUTO_VALUE] ?? false) === true;
    }

    private function validateRowResult(array $result): void
    {
        if (count($result) !== 2
            || !array_key_exists(AtomicNormalizer::FIELD_ERR_NUMBER, $result)
            || !array_key_exists(AtomicNormalizer::FIELD_VALUE, $result)) {
            throw new UnexpectedValueException('row_normalizer must return err_number/value.');
        }
        foreach ($result as $key => $_) {
            if (ReflectionReference::fromArrayElement($result, $key) !== null) {
                throw new UnexpectedValueException('row_normalizer result must not contain references.');
            }
        }
        $code = $result[AtomicNormalizer::FIELD_ERR_NUMBER];
        if (!is_int($code) || ($code !== AtomicNormalizer::ERR_NONE
            && ($code < self::ROW_NORMALIZER_ERR_MIN || $code > self::ROW_NORMALIZER_ERR_MAX))) {
            throw new UnexpectedValueException('row_normalizer error code must be 0 or between 1300 and 1399.');
        }
        if ($code === AtomicNormalizer::ERR_NONE && !is_array($result[AtomicNormalizer::FIELD_VALUE])) {
            throw new UnexpectedValueException('Successful row_normalizer must return an array row.');
        }
    }

    /** Kiểm tra contract DAU sau callback; không normalize lại giá trị nghiệp vụ. */
    private function validateNormalizedRow(string $action, array $before, array $after): void
    {
        $sameIdentity = static function (array $old, array $new): bool {
            if (count($old) !== count($new)) {
                return false;
            }
            foreach ($old as $name => $value) {
                if (!array_key_exists($name, $new) || $new[$name] !== $value) {
                    return false;
                }
            }
            return true;
        };

        switch ($action) {
            case self::ACTION_DELETE:
                if (!$sameIdentity($before, $after)) {
                    throw new UnexpectedValueException('row_normalizer must preserve Delete identity.');
                }
                return;

            case self::ACTION_UPDATE:
                if (count($after) !== 2
                    || !array_key_exists(self::FIELD_KEYS, $after)
                    || !array_key_exists(self::FIELD_VALUES, $after)
                    || !is_array($after[self::FIELD_KEYS])
                    || !is_array($after[self::FIELD_VALUES])
                    || $after[self::FIELD_VALUES] === []) {
                    throw new UnexpectedValueException('row_normalizer must return Update keys and nonempty values.');
                }
                if (!$sameIdentity($before[self::FIELD_KEYS], $after[self::FIELD_KEYS])) {
                    throw new UnexpectedValueException('row_normalizer must preserve Update identity.');
                }
                foreach ($after[self::FIELD_VALUES] as $name => $_) {
                    if (!is_string($name) || !array_key_exists($name, $this->fields)) {
                        throw new UnexpectedValueException('row_normalizer returned an unknown Update field.');
                    }
                }
                return;

            case self::ACTION_ADD:
                foreach ($after as $name => $_) {
                    if (!is_string($name) || !array_key_exists($name, $this->fields)) {
                        throw new UnexpectedValueException('row_normalizer returned an unknown Add field.');
                    }
                }
                foreach ($this->keys as $name => $options) {
                    $present = array_key_exists($name, $after);
                    $policy = $options[TableFieldSchema::FIELD_ON_ADD];
                    if ($present && $policy === TableFieldSchema::ON_ADD_FORBIDDEN) {
                        throw new UnexpectedValueException('row_normalizer introduced a forbidden Add key.');
                    }
                    if (!$present && ($policy === TableFieldSchema::ON_ADD_REQUIRED
                        || ($policy === TableFieldSchema::ON_ADD_OPTIONAL && !$this->hasAuto($name)))) {
                        throw new UnexpectedValueException('row_normalizer removed a required Add key.');
                    }
                }
                foreach ($this->fields as $name => $_) {
                    if (!array_key_exists($name, $this->keys) && !array_key_exists($name, $after)
                        && !$this->hasAuto($name)) {
                        throw new UnexpectedValueException('row_normalizer removed a required Add field.');
                    }
                }
                return;

            default:
                throw new UnexpectedValueException('Unknown DAU action.');
        }
    }

    private static function record(array $path, int $code, mixed $value, mixed $errorValue = null): array
    {
        return [JsonNormalizer::FIELD_PATH => $path,
            JsonNormalizer::FIELD_ERR_NUMBER => $code, JsonNormalizer::FIELD_VALUE => $value,
            JsonNormalizer::FIELD_ERROR_VALUE => $code === AtomicNormalizer::ERR_NONE ? null
                : (func_num_args() === 4 ? $errorValue : $value)];
    }
}
