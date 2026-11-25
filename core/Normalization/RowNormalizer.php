<?php
declare(strict_types=1);

namespace Core\Normalization;

use Closure;
use Core\Http\Response;
use InvalidArgumentException;
use ReflectionReference;
use UnexpectedValueException;

/**
 * Normalize các field có mặt; không áp đặt presence hoặc thao tác DB.
 * Field lạ/schema sai gây exception. Field lỗi giữ input, field đúng được
 * normalize; chỉ gọi row_normalizer khi không có lỗi field.
 * Callback có thể hiệu chỉnh row: thành công nhận value làm data, không
 * normalize lại; thất bại giữ row trước callback và dùng value mô tả lỗi.
 * Mỗi normalize() xử lý lại input, không cache. Không dùng ExtArray.
 */
final class RowNormalizer
{
    // Reserved hard errors: 1000–1099; custom callback errors: 1100–1199.
    public const ROW_NORMALIZER_ERR_MIN = 1100;
    public const ROW_NORMALIZER_ERR_MAX = 1199;

    private array $arrRow;
    private array $arrField;
    private ?Closure $fnRowNormalizer;

    public function __construct(array $arrRow, array $arrRowFieldSchema)
    {
        if (!RowFieldSchema::isValid($arrRowFieldSchema)) {
            throw new InvalidArgumentException('Invalid RowFieldSchema.');
        }
        $this->arrField = $arrRowFieldSchema[RowFieldSchema::FIELD_FIELDS];
        foreach ($arrRow as $name => $_) {
            // Schema đã xác nhận key là string không rỗng hoặc int không âm.
            if (!array_key_exists($name, $this->arrField)) {
                throw new InvalidArgumentException('Unknown field in row: ' . (string) $name);
            }
        }
        $this->arrRow = $arrRow;
        $this->fnRowNormalizer = $arrRowFieldSchema[RowFieldSchema::FIELD_ROW_NORMALIZER] ?? null;
    }

    /** @return array{status: string, data: array, extra: ?array} */
    public function normalize(): array
    {
        // Tạo array mới để phép gán giá trị canonical không ghi qua reference input.
        $arrRow = [];
        $arrError = [];
        foreach ($this->arrRow as $name => $value) {
            $arrResult = AtomicNormalizer::normalize($value, $this->arrField[$name]);
            $arrRow[$name] = $arrResult[AtomicNormalizer::FIELD_VALUE];
            if ($arrResult[AtomicNormalizer::FIELD_ERR_NUMBER] !== AtomicNormalizer::ERR_NONE) {
                $arrError[$name] = $arrResult;
            }
        }
        if ($arrError !== []) {
            return self::response($arrRow, $arrError);
        }
        if ($this->fnRowNormalizer !== null) {
            $arrResult = ($this->fnRowNormalizer)($arrRow);
            self::validateRowResult($arrResult);
            if ($arrResult[AtomicNormalizer::FIELD_ERR_NUMBER] !== AtomicNormalizer::ERR_NONE) {
                return self::response($arrRow, $arrResult);
            }
            $arrRow = $arrResult[AtomicNormalizer::FIELD_VALUE];//lưu lại kết quả thay đổi
        }
        return self::response($arrRow, null);
    }

    private static function validateRowResult(array $arrResult): void
    {
        if (count($arrResult) !== 2
            || !array_key_exists(AtomicNormalizer::FIELD_ERR_NUMBER, $arrResult)
            || !array_key_exists(AtomicNormalizer::FIELD_VALUE, $arrResult)) {
            throw new UnexpectedValueException('row_normalizer must return err_number/value.');
        }
        foreach ($arrResult as $key => $_) {
            if (ReflectionReference::fromArrayElement($arrResult, $key) !== null) {
                throw new UnexpectedValueException('row_normalizer result must not contain references.');
            }
        }
        $iCode = $arrResult[AtomicNormalizer::FIELD_ERR_NUMBER];
        if (!is_int($iCode) || ($iCode !== AtomicNormalizer::ERR_NONE
            && ($iCode < self::ROW_NORMALIZER_ERR_MIN || $iCode > self::ROW_NORMALIZER_ERR_MAX))) {
            throw new UnexpectedValueException('row_normalizer error code must be 0 or between 1100 and 1199.');
        }
        if ($iCode === AtomicNormalizer::ERR_NONE && !is_array($arrResult[AtomicNormalizer::FIELD_VALUE])) {
            throw new UnexpectedValueException('Successful row_normalizer must return an array row.');
        }
    }

    private static function response(array $arrRow, ?array $arrError): array
    {
        return [
            'status' => $arrError === null ? Response::SERVER_OK_STATUS : Response::SERVER_LOGIC_ERR_STATUS,
            'data' => $arrRow,
            'extra' => $arrError,
        ];
    }
}
