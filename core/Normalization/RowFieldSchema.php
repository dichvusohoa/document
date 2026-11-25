<?php
declare(strict_types=1);

namespace Core\Normalization;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * Contract của một row tổng quát; schema là array. PHP >= 8.0.
 *
 * Cấu trúc $arrSchema truyền vào RowFieldSchema::isValid() và làm tham số
 * thứ hai của constructor RowNormalizer($arrRow, $arrSchema).
 * RowFieldSchema có constructor private không nhận tham số, không tạo object schema.
 *
 * [
 *     'fields' => [
 *         <tên field> => <array AtomicSchema>,
 *         ...
 *     ],
 *     'row_normalizer' => <Closure|null>, // Optional, có thể bỏ key.
 * ]
 *
 * - fields bắt buộc, là map không rỗng; mỗi giá trị là AtomicSchema hợp lệ.
 * - Tên field là string nguyên thủy không rỗng (không trim) hoặc int >= 0.
 *   Cho phép key số thưa và trộn string/int; xét key sau khi PHP tạo array.
 * - row_normalizer khuyết/null nghĩa là chỉ normalize từng field.
 *   Nếu có Closure, prototype là function (array $arrRow): array;
 *   callback kiểm tra/hiệu chỉnh nghiệp vụ sau khi các field có mặt đều hợp lệ.
 * - Không nhận thuộc tính khác ở root schema. Field thiếu trong dữ liệu được
 *   bỏ qua; field ngoài schema gây exception tại RowNormalizer.
 *
 * Ví dụ:
 *     $arrSchema = [
 *         'fields' => [
 *             'pageSize' => ['type' => 'int'],
 *             'keyword' => ['type' => 'string', 'trim' => true],
 *         ],
 *         'row_normalizer' => static function (array $arrRow): array {
 *             if (array_key_exists('pageSize', $arrRow)) {
 *                 $arrRow['pageSize'] = max(1, min(100, $arrRow['pageSize']));
 *             }
 *             return ['err_number' => 0, 'value' => $arrRow];
 *         },
 *     ];
 *     $arrRow = ['pageSize' => '200', 'keyword' => ' PHP '];
 *     $arrResponse = (new RowNormalizer($arrRow, $arrSchema))->normalize();
 *     // $arrResponse['data'] = ['pageSize' => 100, 'keyword' => 'PHP'].
 */
final class RowFieldSchema
{
    public const FIELD_FIELDS = 'fields';
    public const FIELD_ROW_NORMALIZER = 'row_normalizer';

    private function __construct() {}

    /** Không normalize dữ liệu; kiểm tra cả schema của field vắng mặt. */
    public static function isValid(mixed $arrSchema): bool
    {
        if (!is_array($arrSchema)
            || !array_key_exists(self::FIELD_FIELDS, $arrSchema)
            || !is_array($arrSchema[self::FIELD_FIELDS])
            || $arrSchema[self::FIELD_FIELDS] === []) {
            return false;
        }
        foreach ($arrSchema as $key => $_) {
            if (!in_array($key, [self::FIELD_FIELDS, self::FIELD_ROW_NORMALIZER], true)) {
                return false;
            }
        }
        foreach ($arrSchema[self::FIELD_FIELDS] as $name => $arrAtomicSchema) {
            // Xét key thực tế của PHP array; không trim hoặc đánh lại chỉ số.
            $validName = (is_string($name) && $name !== '')
                || (is_int($name) && $name >= 0);
            if (!$validName || !AtomicSchema::isValid($arrAtomicSchema)) {
                return false;
            }
        }
        // Khuyết hoặc null đều có nghĩa không sử dụng callback toàn dòng.
        $fnRowNormalizer = $arrSchema[self::FIELD_ROW_NORMALIZER] ?? null;
        return $fnRowNormalizer === null || self::isValidRowNormalizer($fnRowNormalizer);
    }

    /**
     * Kiểm tra prototype của callback chuẩn hóa nghiệp vụ toàn dòng:
     *     function (array $arrRow): array
     *
     * $fnRowNormalizer phải là Closure (cho phép static), nhận đúng một tham số
     * array bắt buộc và khai báo trả array. Không nullable, tham số mặc định,
     * variadic, nhận tham chiếu hoặc trả tham chiếu.
     *
     * RowNormalizer chỉ gọi callback sau khi mọi field có mặt đã normalize
     * thành công. $arrRow vẫn có thể thiếu field; UDA tự kiểm tra nếu cần.
     * Callback được phép kiểm tra và hiệu chỉnh dữ liệu theo nghiệp vụ.
     * Kết quả có đúng hai key err_number và value, không là reference trực tiếp:
     * - err_number = 0: value là array dòng kết quả, được nhận làm Response.data.
     * - err_number >= 1000: value là thông tin lỗi tùy ý; giữ data trước callback.
     * err_number phải là int. Không normalize lại dòng callback trả về.
     * Hàm này chỉ kiểm tra prototype; RowNormalizer kiểm tra kết quả khi gọi.
     *
     * Ví dụ (pageSize có AtomicSchema type=int, không đặt gte/lte để UDA tự sửa):
     *     $fnRowNormalizer = static function (array $arrRow): array {
     *         if (!array_key_exists('pageSize', $arrRow)) {
     *             return ['err_number' => 1000, 'value' => ['pageSize']];
     *         }
     *         $arrRow['pageSize'] = max(1, min(100, $arrRow['pageSize']));
     *         return ['err_number' => 0, 'value' => $arrRow];
     *     };
     *
     * @param mixed $fnRowNormalizer Callback cần kiểm tra.
     * @return bool true nếu là Closure có đúng prototype yêu cầu.
     */
    private static function isValidRowNormalizer(mixed $fnRowNormalizer): bool
    {
        if (!$fnRowNormalizer instanceof Closure) {
            return false;
        }
        $reflection = new ReflectionFunction($fnRowNormalizer);
        $arrParameter = $reflection->getParameters();
        $returnType = $reflection->getReturnType();
        if ($reflection->returnsReference() || count($arrParameter) !== 1
            || !$returnType instanceof ReflectionNamedType
            || $returnType->getName() !== 'array' || $returnType->allowsNull()) {
            return false;
        }
        $parameter = $arrParameter[0];
        $type = $parameter->getType();
        return !$parameter->isPassedByReference() && !$parameter->isVariadic()
            && !$parameter->isOptional() && $type instanceof ReflectionNamedType
            && $type->getName() === 'array' && !$type->allowsNull();
    }
}
