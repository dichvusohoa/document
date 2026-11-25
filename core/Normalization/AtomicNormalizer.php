<?php
declare(strict_types=1);

namespace Core\Normalization;

use InvalidArgumentException;

/**
 * Normalize và validate một atomic value theo AtomicSchema. PHP >= 8.0.
 *
 * normalize(): strict=false.
 * validate(): strict do caller chọn, mặc định true.
 * isValid(): gọi validate(), chỉ trả bool.
 *
 * Kết quả: ['err_number' => int, 'value' => mixed].
 * - Thành công: value là giá trị canonical, thỏa constraint.
 * - Thất bại: value là input nguyên gốc của lần gọi hiện tại.
 * - Schema sai: throw InvalidArgumentException, kể cả khi gọi isValid().
 *
 * strict=true yêu cầu input khác null đã canonical. Input null vẫn có thể
 * được thay bằng default_value theo chính sách dưới đây.
 * strict=false chỉ thực hiện những chuyển đổi được mô tả ở từng type.
 * Tham số strict độc lập với declare(strict_types=1) của PHP.
 *
 * Không dùng tham chiếu ở API công khai, không sửa biến input của caller.
 * Input null: nullable=true trả null, ưu tiên hơn default_value.
 * Input null: nullable=false dùng default_value nếu có, kể cả strict=true;
 * thiếu default_value thì trả ERR_NULL_NOT_ALLOWED.
 * Default phải canonical và thỏa constraint, được AtomicSchema kiểm tra
 * trước khi xử lý input. Default sai là lỗi schema và gây exception.
 * Không đổi '' thành null; không thay input khác null bị lỗi bằng default.
 * Không tạo message trên đường xử lý thông thường; gọi showMessage() khi cần.
 * Không phụ thuộc ValidUtility hoặc ExtArray.
 */
final class AtomicNormalizer
{
    public const FIELD_ERR_NUMBER = 'err_number';
    public const FIELD_VALUE = 'value';

    public const ERR_NONE = 0;
    public const ERR_NULL_NOT_ALLOWED = 1;
    public const ERR_TYPE = 2;
    public const ERR_FORMAT = 3;
    public const ERR_NOT_CANONICAL = 4;
    public const ERR_NUMBER_OUT_OF_RANGE = 5;
    public const ERR_NOT_FINITE = 6;
    public const ERR_INVALID_UTF8 = 7;
    public const ERR_MIN_LENGTH = 8;
    public const ERR_MAX_LENGTH = 9;
    public const ERR_SCALE = 10;
    public const ERR_GT = 11;
    public const ERR_GTE = 12;
    public const ERR_LT = 13;
    public const ERR_LTE = 14;

    private const RANGE_ERROR = [
        AtomicSchema::FIELD_GT => self::ERR_GT,
        AtomicSchema::FIELD_GTE => self::ERR_GTE,
        AtomicSchema::FIELD_LT => self::ERR_LT,
        AtomicSchema::FIELD_LTE => self::ERR_LTE,
    ];

    private function __construct()
    {
    }

    /**
     * Chấp nhận input có thể chuyển đổi theo quy tắc của type.
     * Ví dụ: normalize('123 ', ['type' => 'int']) trả value=123.
     * @return array{err_number: int, value: mixed}
     * @throws InvalidArgumentException Khi schema không hợp lệ.
     */
    public static function normalize(mixed $value, array $arrSchema): array
    {
        return self::process($value, $arrSchema, false);
    }

    /**
     * strict=true: input khác null phải canonical và thỏa constraint.
     * Input null áp dụng nullable/default_value ở cả hai chế độ strict.
     * strict=false: tương đương normalize(), trả value đã chuyển đổi.
     * @return array{err_number: int, value: mixed}
     * @throws InvalidArgumentException Khi schema không hợp lệ.
     */
    public static function validate(mixed $value, array $arrSchema, bool $strict = true): array
    {
        return self::process($value, $arrSchema, $strict);
    }

    /**
     * Cùng quy tắc và cùng lỗi schema như validate(); chỉ rút gọn result.
     * strict=false có thể trả true cho input chưa canonical, ví dụ '123 '.
     * Input null có default hợp lệ và nullable=false trả true, kể cả strict=true.
     * @throws InvalidArgumentException Khi schema không hợp lệ.
     */
    public static function isValid(mixed $value, array $arrSchema, bool $strict = true): bool
    {
        $arrResult = self::validate($value, $arrSchema, $strict);
        return $arrResult[self::FIELD_ERR_NUMBER] === self::ERR_NONE;
    }

    /**
     * Pipeline chung. Schema được kiểm tra đúng một lần mỗi lần gọi API.
     * Helper chỉ sửa bản candidate nội bộ; lỗi luôn trả input ban đầu.
     * Thứ tự: kiểm tra schema (gồm default) -> nullable/default_value
     * -> kiểu/dạng/chuyển đổi -> constraint của input khác null.
     * Dừng ở lỗi đầu tiên của atomic value.
     */
    private static function process(mixed $value, array $arrSchema, bool $strict): array
    {
        if (!AtomicSchema::isValid($arrSchema)) {
            throw new InvalidArgumentException('Atomic schema không hợp lệ.');
        }
        if ($value === null) {
            if ($arrSchema[AtomicSchema::FIELD_NULLABLE] ?? false) {
                return self::result(self::ERR_NONE, null);
            }

            // Default đã được AtomicSchema kiểm tra canonical và constraint.
            // Không normalize lại; chính sách này độc lập với strict.
            // array_key_exists nhận diện đúng cả default 0, false hoặc ''.
            if (array_key_exists(AtomicSchema::FIELD_DEFAULT_VALUE, $arrSchema)) {
                return self::result(
                    self::ERR_NONE,
                    $arrSchema[AtomicSchema::FIELD_DEFAULT_VALUE]
                );
            }

            return self::result(self::ERR_NULL_NOT_ALLOWED, null);
        }

        $candidate = $value;
        $strType = $arrSchema[AtomicSchema::FIELD_TYPE];
        $iError = match ($strType) {
            AtomicSchema::TYPE_INT => self::processInt($candidate, $strict),
            AtomicSchema::TYPE_FLOAT => self::processFloat($candidate, $strict),
            AtomicSchema::TYPE_DECIMAL => self::processDecimal($candidate, $strict),
            AtomicSchema::TYPE_STRING => self::processString($candidate, $arrSchema, $strict),
            AtomicSchema::TYPE_BOOL => self::processBool($candidate, $strict),
            AtomicSchema::TYPE_DATE, AtomicSchema::TYPE_TIME, AtomicSchema::TYPE_DATETIME
                => self::processTemporal($candidate, $strType, $strict),
        };
        if ($iError === self::ERR_NONE) {
            $iError = self::validateConstraints($candidate, $arrSchema);
        }
        return self::result($iError, $iError === self::ERR_NONE ? $candidate : $value);
    }

    /**
     * int:
     * - Strict: chỉ nhận PHP int.
     * - Không strict: nhận thêm chuỗi số nguyên, trim khoảng trắng ASCII;
     *   cho phép dấu +/- và số 0 đầu. Ví dụ ' +00123 ' -> 123.
     * - Từ chối float, bool, dấu chấm, exponent và chuỗi chỉ chứa một phần số.
     * - Kiểm tra giới hạn PHP_INT_MIN/MAX trước khi cast, không dùng float.
     */
    private static function processInt(mixed &$value, bool $strict): int
    {
        if (is_int($value)) {
            return self::ERR_NONE;
        }
        if ($strict || !is_string($value)) {
            return self::ERR_TYPE;
        }
        $strInput = self::trimWhitespace($value);
        if (preg_match('/\A([+-]?)([0-9]+)\z/', $strInput, $arrMatch) !== 1) {
            return self::ERR_FORMAT;
        }
        $strDigits = ltrim($arrMatch[2], '0');
        $strDigits = $strDigits === '' ? '0' : $strDigits;
        $isNegative = $arrMatch[1] === '-' && $strDigits !== '0';
        $strLimit = $isNegative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (strlen($strDigits) > strlen($strLimit)
            || (strlen($strDigits) === strlen($strLimit) && strcmp($strDigits, $strLimit) > 0)) {
            return self::ERR_NUMBER_OUT_OF_RANGE;
        }
        $value = (int) (($isNegative ? '-' : '') . $strDigits);
        return self::ERR_NONE;
    }

    /**
     * float:
     * - Strict: chỉ nhận PHP float hữu hạn (int cũng bị từ chối).
     * - Không strict: nhận thêm int hoặc chuỗi số, cho phép exponent và trim.
     * - Tuân theo độ chính xác float của PHP; không bảo đảm chính xác thập phân.
     * - Từ chối bool, NAN/INF, overflow và underflow từ chuỗi khác 0 thành 0.
     */
    private static function processFloat(mixed &$value, bool $strict): int
    {
        if (is_float($value)) {
            return is_finite($value) ? self::ERR_NONE : self::ERR_NOT_FINITE;
        }
        if ($strict) {
            return self::ERR_TYPE;
        }
        if (is_int($value)) {
            $value = (float) $value;
            return self::ERR_NONE;
        }
        if (!is_string($value)) {
            return self::ERR_TYPE;
        }
        $strInput = self::trimWhitespace($value);
        if (preg_match('/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/', $strInput) !== 1) {
            return self::ERR_FORMAT;
        }
        $fValue = (float) $strInput;
        if (!is_finite($fValue)) {
            return self::ERR_NUMBER_OUT_OF_RANGE;
        }
        $strMantissa = preg_split('/[eE]/', $strInput)[0];
        if ($fValue == 0.0 && preg_match('/[1-9]/', $strMantissa) === 1) {
            return self::ERR_NUMBER_OUT_OF_RANGE;
        }
        $value = $fValue;
        return self::ERR_NONE;
    }

    /**
     * decimal:
     * - Canonical: string thập phân không exponent, dấu + hay số 0 đầu.
     *   Phần nguyên bắt buộc; nếu có dấu chấm thì phải có chữ số phía sau.
     * - Không strict: nhận thêm int, trim chuỗi, bỏ dấu + và số 0 thừa đầu.
     * - Luôn từ chối float để tránh đưa sai số nhị phân vào decimal.
     * - Giữ số 0 cuối phần lẻ và dấu âm của zero; không làm tròn/padding.
     *   Ví dụ ' +0012.50 ' -> '12.50'; '-0.00' giữ nguyên.
     */
    private static function processDecimal(mixed &$value, bool $strict): int
    {
        if (!is_string($value) && !(!$strict && is_int($value))) {
            return self::ERR_TYPE;
        }
        $strInput = $strict ? $value : self::trimWhitespace((string) $value);
        if (preg_match('/\A([+-]?)([0-9]+)(?:\.([0-9]+))?\z/', $strInput, $arrMatch) !== 1) {
            return self::ERR_FORMAT;
        }
        $strInteger = ltrim($arrMatch[2], '0');
        $strCanonical = ($arrMatch[1] === '-' ? '-' : '')
            . ($strInteger === '' ? '0' : $strInteger)
            . (isset($arrMatch[3]) ? '.' . $arrMatch[3] : '');
        if ($strict && $value !== $strCanonical) {
            return self::ERR_NOT_CANONICAL;
        }
        $value = $strCanonical;
        return self::ERR_NONE;
    }

    /**
     * string: luôn yêu cầu PHP string UTF-8 hợp lệ; không tự cast scalar khác.
     * trim=false (mặc định): giữ khoảng trắng ở cả hai chế độ.
     * trim=true: strict từ chối chuỗi chưa trim; không strict thực hiện trim.
     * Trim ở đây gồm ASCII space, tab, CR, LF, VT, FF; không bỏ byte NUL.
     */
    private static function processString(mixed &$value, array $arrSchema, bool $strict): int
    {
        if (!is_string($value)) {
            return self::ERR_TYPE;
        }
        if (preg_match('//u', $value) !== 1) {
            return self::ERR_INVALID_UTF8;
        }
        if ($arrSchema[AtomicSchema::FIELD_TRIM] ?? false) {
            $strTrimmed = self::trimWhitespace($value);
            if ($strict && $value !== $strTrimmed) {
                return self::ERR_NOT_CANONICAL;
            }
            $value = $strTrimmed;
        }
        return self::ERR_NONE;
    }

    /**
     * bool:
     * - Strict: chỉ nhận PHP bool.
     * - Không strict: nhận thêm int 0/1 và chuỗi '0'/'1'/'true'/'false'
     *   không phân biệt hoa thường, có trim.
     * - Không dùng (bool) để suy diễn truthiness; không nhận '', yes/no, float.
     */
    private static function processBool(mixed &$value, bool $strict): int
    {
        if (is_bool($value)) {
            return self::ERR_NONE;
        }
        if ($strict) {
            return self::ERR_TYPE;
        }
        if (is_int($value)) {
            if ($value !== 0 && $value !== 1) {
                return self::ERR_FORMAT;
            }
            $value = $value === 1;
            return self::ERR_NONE;
        }
        if (!is_string($value)) {
            return self::ERR_TYPE;
        }
        $strInput = strtolower(self::trimWhitespace($value));
        if (!in_array($strInput, ['0', '1', 'true', 'false'], true)) {
            return self::ERR_FORMAT;
        }
        $value = $strInput === '1' || $strInput === 'true';
        return self::ERR_NONE;
    }

    /**
     * date/time/datetime luôn nhận string, không nhận timestamp hoặc object.
     * Canonical theo AtomicSchema: Y-m-d, H:i:s, Y-m-d H:i:s.
     * Không strict: trim, thêm zero ở tháng/ngày/giờ/phút/giây, thêm giây 00
     * khi thiếu; datetime nhận cả dấu cách hoặc T giữa ngày và giờ.
     * Ví dụ '9:15' -> '09:15:00', '2026-9-5T9:15' -> '2026-09-05 09:15:00'.
     * Strict: input phải trùng chính xác chuỗi canonical.
     * Kiểm tra ngày thực, năm 0001..9999, giờ 0..23, phút/giây 0..59;
     * không cho PHP tự cuộn ngày sai, không timezone hoặc microsecond.
     */
    private static function processTemporal(mixed &$value, string $strType, bool $strict): int
    {
        if (!is_string($value)) {
            return self::ERR_TYPE;
        }
        $strInput = $strict ? $value : self::trimWhitespace($value);
        $strDatePattern = '(?<y>[0-9]{4})-(?<m>[0-9]{1,2})-(?<d>[0-9]{1,2})';
        $strTimePattern = '(?<h>[0-9]{1,2}):(?<i>[0-9]{1,2})(?::(?<s>[0-9]{1,2}))?';
        $strPattern = match ($strType) {
            AtomicSchema::TYPE_DATE => $strDatePattern,
            AtomicSchema::TYPE_TIME => $strTimePattern,
            AtomicSchema::TYPE_DATETIME => $strDatePattern . '[ T]' . $strTimePattern,
        };
        if (preg_match('/\A' . $strPattern . '\z/', $strInput, $arrMatch) !== 1) {
            return self::ERR_FORMAT;
        }
        $strDate = '';
        $strTime = '';
        if ($strType !== AtomicSchema::TYPE_TIME) {
            $iYear = (int) $arrMatch['y'];
            $iMonth = (int) $arrMatch['m'];
            $iDay = (int) $arrMatch['d'];
            if (!checkdate($iMonth, $iDay, $iYear)) {
                return self::ERR_FORMAT;
            }
            $strDate = sprintf('%04d-%02d-%02d', $iYear, $iMonth, $iDay);
        }
        if ($strType !== AtomicSchema::TYPE_DATE) {
            $iHour = (int) $arrMatch['h'];
            $iMinute = (int) $arrMatch['i'];
            $iSecond = (int) ($arrMatch['s'] ?? 0);
            if ($iHour > 23 || $iMinute > 59 || $iSecond > 59) {
                return self::ERR_FORMAT;
            }
            $strTime = sprintf('%02d:%02d:%02d', $iHour, $iMinute, $iSecond);
        }
        $strCanonical = match ($strType) {
            AtomicSchema::TYPE_DATE => $strDate,
            AtomicSchema::TYPE_TIME => $strTime,
            AtomicSchema::TYPE_DATETIME => $strDate . ' ' . $strTime,
        };
        if ($strict && $value !== $strCanonical) {
            return self::ERR_NOT_CANONICAL;
        }
        $value = $strCanonical;
        return self::ERR_NONE;
    }

    /**
     * Kiểm tra constraint chung cho cả strict và không strict.
     * Input tại đây đã canonical; schema đã hợp lệ.
     * Thứ tự: độ dài -> semantic format; scale; sau đó range gt/gte/lt/lte.
     */
    private static function validateConstraints(mixed $value, array $arrSchema): int
    {
        $strType = $arrSchema[AtomicSchema::FIELD_TYPE];
        if ($strType === AtomicSchema::TYPE_STRING) {
            if (isset($arrSchema[AtomicSchema::FIELD_MIN_LENGTH])
                || isset($arrSchema[AtomicSchema::FIELD_MAX_LENGTH])) {
                // Đếm Unicode code point, không đếm byte hoặc grapheme cluster.
                $iLength = preg_match_all('/./us', $value);
                if ($iLength === false) {
                    return self::ERR_INVALID_UTF8;
                }
                if (isset($arrSchema[AtomicSchema::FIELD_MIN_LENGTH])
                    && $iLength < $arrSchema[AtomicSchema::FIELD_MIN_LENGTH]) {
                    return self::ERR_MIN_LENGTH;
                }
                if (isset($arrSchema[AtomicSchema::FIELD_MAX_LENGTH])
                    && $iLength > $arrSchema[AtomicSchema::FIELD_MAX_LENGTH]) {
                    return self::ERR_MAX_LENGTH;
                }
            }
            if (isset($arrSchema[AtomicSchema::FIELD_FORMAT])
                && !StringFormatValidator::isValidFormat(
                    $value,
                    $arrSchema[AtomicSchema::FIELD_FORMAT]
                )) {
                return self::ERR_FORMAT;
            }
        }
        if ($strType === AtomicSchema::TYPE_DECIMAL && isset($arrSchema[AtomicSchema::FIELD_SCALE])) {
            $iDot = strpos($value, '.');
            $iScale = $iDot === false ? 0 : strlen($value) - $iDot - 1;
            if ($iScale > $arrSchema[AtomicSchema::FIELD_SCALE]) {
                return self::ERR_SCALE;
            }
        }
        foreach (self::RANGE_ERROR as $strField => $iError) {
            if (!array_key_exists($strField, $arrSchema)) {
                continue;
            }
            $bound = $arrSchema[$strField];
            $iCmp = match ($strType) {
                AtomicSchema::TYPE_DECIMAL => self::compareDecimal($value, $bound),
                AtomicSchema::TYPE_FLOAT => $value <=> (float) $bound,
                AtomicSchema::TYPE_INT => $value <=> $bound,
                default => strcmp($value, $bound) <=> 0,
            };
            $isValid = match ($strField) {
                AtomicSchema::FIELD_GT => $iCmp > 0,
                AtomicSchema::FIELD_GTE => $iCmp >= 0,
                AtomicSchema::FIELD_LT => $iCmp < 0,
                AtomicSchema::FIELD_LTE => $iCmp <= 0,
            };
            if (!$isValid) {
                return $iError;
            }
        }
        return self::ERR_NONE;
    }

    /** So sánh hai decimal canonical chính xác; không dùng float/BCMath. */
    private static function compareDecimal(string $strA, string $strB): int
    {
        $arrA = explode('.', ltrim($strA, '-'), 2);
        $arrB = explode('.', ltrim($strB, '-'), 2);
        $strFracA = rtrim($arrA[1] ?? '', '0');
        $strFracB = rtrim($arrB[1] ?? '', '0');
        $iSignA = $arrA[0] === '0' && $strFracA === '' ? 0 : ($strA[0] === '-' ? -1 : 1);
        $iSignB = $arrB[0] === '0' && $strFracB === '' ? 0 : ($strB[0] === '-' ? -1 : 1);
        if ($iSignA !== $iSignB) {
            return $iSignA <=> $iSignB;
        }
        $iCmp = strlen($arrA[0]) <=> strlen($arrB[0]);
        if ($iCmp === 0) {
            $iCmp = strcmp($arrA[0], $arrB[0]) <=> 0;
        }
        if ($iCmp === 0) {
            $iLength = max(strlen($strFracA), strlen($strFracB));
            $iCmp = strcmp(str_pad($strFracA, $iLength, '0'), str_pad($strFracB, $iLength, '0')) <=> 0;
        }
        return $iSignA * $iCmp;
    }

    private static function trimWhitespace(string $strValue): string
    {
        return trim($strValue, " \t\r\n\v\f");
    }

    private static function result(int $iError, mixed $value): array
    {
        return [self::FIELD_ERR_NUMBER => $iError, self::FIELD_VALUE => $value];
    }

    /**
     * Tạo message theo yêu cầu; trả string, không echo và không sửa result.
     * Truyền cùng schema và value từ result. Không đưa nguyên input vào message.
     * Text thuần; caller chịu trách nhiệm escape nếu hiển thị trong HTML.
     * Không gọi hàm này tự động trong vòng lặp normalize table.
     */
    public static function showMessage(int $err_number, mixed $value, array $arrSchema): string
    {
        if (!AtomicSchema::isValid($arrSchema)) {
            throw new InvalidArgumentException('Atomic schema không hợp lệ.');
        }
        return match ($err_number) {
            self::ERR_NONE => '',
            self::ERR_NULL_NOT_ALLOWED => 'Giá trị không được là null và schema không khai báo default_value.',
            self::ERR_TYPE => 'Kiểu đầu vào ' . get_debug_type($value)
                . ' không được chấp nhận cho type ' . $arrSchema[AtomicSchema::FIELD_TYPE] . '.',
            self::ERR_FORMAT => 'Giá trị không đúng định dạng yêu cầu.',
            self::ERR_NOT_CANONICAL => 'Giá trị chưa ở dạng canonical theo schema.',
            self::ERR_NUMBER_OUT_OF_RANGE => 'Giá trị vượt giới hạn biểu diễn của kiểu số.',
            self::ERR_NOT_FINITE => 'Giá trị phải là số hữu hạn.',
            self::ERR_INVALID_UTF8 => 'Chuỗi phải là UTF-8 hợp lệ.',
            self::ERR_MIN_LENGTH => 'Độ dài chuỗi nhỏ hơn giới hạn tối thiểu.',
            self::ERR_MAX_LENGTH => 'Độ dài chuỗi lớn hơn giới hạn tối đa.',
            self::ERR_SCALE => 'Số chữ số phần thập phân vượt quá scale.',
            self::ERR_GT => 'Giá trị phải lớn hơn cận gt.',
            self::ERR_GTE => 'Giá trị phải lớn hơn hoặc bằng cận gte.',
            self::ERR_LT => 'Giá trị phải nhỏ hơn cận lt.',
            self::ERR_LTE => 'Giá trị phải nhỏ hơn hoặc bằng cận lte.',
            default => throw new InvalidArgumentException('Mã lỗi atomic không hợp lệ.'),
        };
    }
}
