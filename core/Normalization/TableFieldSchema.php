<?php
declare(strict_types=1);

namespace Core\Normalization;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * Schema các field và vai trò khóa của một bảng. PHP >= 8.0.
 *
 * Cấu trúc:
 * [
 *     'fields' => [
 *         'id'   => ['type' => 'int', 'gte' => 1],
 *         'name' => ['type' => 'string', 'max_length' => 50],
 *     ],
 *     'keys' => [
 *         'id' => ['on_add' => 'optional'],
 *     ],
 *     'row_normalizer' => $closure, // tùy chọn; null cũng hợp lệ
 * ]
 *
 * Quy ước cấu trúc:
 * - Root bắt buộc fields và keys; tùy chọn row_normalizer (Closure|null).
 * - row_normalizer: function (string $action, array $row): array, không tham chiếu.
 * - fields và keys đều là map không rỗng.
 * - Tên field là PHP string không rỗng; không tự trim hoặc đổi tên.
 * - Mỗi field có một AtomicSchema hợp lệ.
 * - Mỗi field khóa phải được khai báo trong fields.
 * - Mỗi cấu hình khóa có đúng on_add; thuộc tính này bắt buộc.
 * - Không chấp nhận thuộc tính lạ ở root hoặc cấu hình khóa.
 *
 * Ý nghĩa on_add do TableDAUNormalizer thực hiện:
 * - required: field phải có mặt; giá trị kể cả null giao AtomicNormalizer.
 * - optional: chỉ được thiếu khi auto_value=true; có mặt thì giao AtomicNormalizer.
 * - forbidden: field không được xuất hiện, kể cả mang giá trị null.
 *
 * AtomicSchema vẫn là schema giá trị; nullable/default_value không được
 * dùng để suy ra on_add. Class này không thay đổi các AtomicSchema khóa.
 * Update/delete bắt buộc đủ bộ key; null/default do AtomicNormalizer xử lý.
 *
 * Class chỉ kiểm tra schema, không chuẩn hóa payload, không truy vấn DB
 * và không kiểm tra khả năng sinh khóa hoặc tính duy nhất trong DB.
 * AtomicSchema và các dependency của nó phải được autoload đầy đủ.
 */
final class TableFieldSchema
{
    public const FIELD_FIELDS = 'fields';
    public const FIELD_KEYS = 'keys';
    public const FIELD_ON_ADD = 'on_add';
    public const FIELD_ROW_NORMALIZER = 'row_normalizer';

    public const ON_ADD_REQUIRED = 'required';
    public const ON_ADD_OPTIONAL = 'optional';
    public const ON_ADD_FORBIDDEN = 'forbidden';

    private function __construct()
    {
    }

    /**
     * Kiểm tra toàn bộ schema các field và bộ khóa.
     *
     * Nhận mixed để đầu vào sai dạng cũng trả false, không gây TypeError.
     * Không sửa schema và không tự bổ sung thuộc tính còn thiếu.
     * Không bắt các lỗi hệ thống như thiếu class dependency.
     *
     * @param mixed $schema Schema cần kiểm tra.
     * @return bool true nếu schema đáp ứng đầy đủ cấu trúc và AtomicSchema.
     */
    public static function isValid(mixed $schema): bool
    {
        if (!is_array($schema)
            || !array_key_exists(self::FIELD_FIELDS, $schema)
            || !array_key_exists(self::FIELD_KEYS, $schema)) {
            return false;
        }

        foreach ($schema as $name => $_) {
            if (!in_array($name, [self::FIELD_FIELDS, self::FIELD_KEYS, self::FIELD_ROW_NORMALIZER], true)) {
                return false;
            }
        }
        $validator = $schema[self::FIELD_ROW_NORMALIZER] ?? null;
        if ($validator !== null && !self::isValidRowNormalizer($validator)) {
            return false;
        }

        $arrFieldSchema = $schema[self::FIELD_FIELDS];
        $arrKeySchema = $schema[self::FIELD_KEYS];

        if (!is_array($arrFieldSchema) || $arrFieldSchema === []
            || !is_array($arrKeySchema) || $arrKeySchema === []) {
            return false;
        }

        // Kiểm tra mọi field, kể cả field chưa xuất hiện trong payload.
        foreach ($arrFieldSchema as $strFieldName => $arrAtomicSchema) {
            if (!is_string($strFieldName) || $strFieldName === ''
                || !AtomicSchema::isValid($arrAtomicSchema)) {
                return false;
            }
        }

        foreach ($arrKeySchema as $strKeyName => $arrKeyOption) {
            if (!is_string($strKeyName) || $strKeyName === ''
                || !array_key_exists($strKeyName, $arrFieldSchema)
                || !self::isValidKeyOption($arrKeyOption)) {
                return false;
            }
        }

        return true;
    }

    /** Prototype chính xác, hai tham số bắt buộc, không tham chiếu/variadic. */
    private static function isValidRowNormalizer(mixed $validator): bool
    {
        if (!$validator instanceof Closure) {
            return false;
        }
        $reflection = new ReflectionFunction($validator);
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();
        if ($reflection->returnsReference() || count($parameters) !== 2
            || !$returnType instanceof ReflectionNamedType
            || $returnType->getName() !== 'array' || $returnType->allowsNull()) {
            return false;
        }
        foreach (['string', 'array'] as $index => $expected) {
            $parameter = $parameters[$index];
            $type = $parameter->getType();
            if ($parameter->isPassedByReference() || $parameter->isVariadic() || $parameter->isOptional()
                || !$type instanceof ReflectionNamedType
                || $type->getName() !== $expected || $type->allowsNull()) {
                return false;
            }
        }
        return true;
    }

    /** Cấu hình key chỉ có on_add; không có giá trị mặc định. */
    private static function isValidKeyOption(mixed $option): bool
    {
        return is_array($option)
            && count($option) === 1
            && array_key_exists(self::FIELD_ON_ADD, $option)
            && in_array($option[self::FIELD_ON_ADD], [
                self::ON_ADD_REQUIRED,
                self::ON_ADD_OPTIONAL,
                self::ON_ADD_FORBIDDEN,
            ], true);
    }
}
