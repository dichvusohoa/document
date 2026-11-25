<?php
declare(strict_types=1);

namespace Core\Normalization;

/**
 * Contract cho mot atomic value; schema van la array, khong phai object.
 * PHP >= 8.0. Khong normalize du lieu, khong chua runtime error codes.
 *
 * Quy uoc cua ban de xuat nay:
 * - Chỉ chấp nhận field thuộc type; null chỉ được phép ở default_value khi nullable=true.
 * - nullable mac dinh false; trim mac dinh false (do Normalizer ap dung).
 * - required thuộc tầng cấu trúc. default_value là field tùy chọn của mọi type.
 * - auto_value là metadata bool tùy chọn của mọi type; khuyết tương đương false.
 *   Tầng table diễn giải khả năng DB cấp giá trị khi Add thiếu field.
 *   Không thay đổi nullable/default_value; không cấm cùng tồn tại với default_value.
 * - default_value phải canonical và thỏa constraint; không tự normalize default.
 * - default_value=null chỉ hợp lệ khi nullable=true.
 * - Khi input=null: nullable=true giữ null; nullable=false dùng default_value
 *   nếu có, nếu không thì Normalizer báo lỗi. Áp dụng cả chế độ strict.
 * - Schema luôn kiểm tra default đã khai báo, dù nullable=true khiến nó không
 *   được dùng khi input=null. Không có default_value vẫn là schema hợp lệ.
 * - Không phụ thuộc AtomicNormalizer; không có vòng gọi giữa hai class.
 * - Moi phia range chi dung mot trong gt/gte va lt/lte.
 * - decimal la chuoi thap phan chinh xac, khong exponent; khong dung float.
 * - scale neu co la so chu so thap phan toi da; khong ngam lam tron/padding.
 * - string length tinh bang Unicode code point sau normalize.
 * - date/time/datetime la chuoi canonical, khong timezone, khong microsecond.
 * - Temporal input format tuy chinh, enum, regex va precision chua ho tro.
 *
 * isValid() kiem tra contract va mau thuan range truc tiep; khong cam ket
 * moi to hop constraint deu co gia tri thoa man (vi du scale ket hop range).
 */
final class AtomicSchema
{
    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'bool';
    public const TYPE_DATE = 'date';
    public const TYPE_TIME = 'time';
    public const TYPE_DATETIME = 'datetime';

    public const FIELD_TYPE = 'type';
    public const FIELD_NULLABLE = 'nullable';
    public const FIELD_DEFAULT_VALUE = 'default_value';
    public const FIELD_AUTO_VALUE = 'auto_value';
    public const FIELD_GT = 'gt';
    public const FIELD_GTE = 'gte';
    public const FIELD_LT = 'lt';
    public const FIELD_LTE = 'lte';
    public const FIELD_SCALE = 'scale';
    public const FIELD_TRIM = 'trim';
    public const FIELD_MIN_LENGTH = 'min_length';
    public const FIELD_MAX_LENGTH = 'max_length';
    public const FIELD_FORMAT = 'format';

    public const FORMAT_EMAIL = 'email';
    public const FORMAT_HTTP_URL = 'http_url';
    public const FORMAT_UUID = 'uuid';
    public const DATE_FORMAT = 'Y-m-d';
    public const TIME_FORMAT = 'H:i:s';
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

    private const RANGE_FIELDS = [
        self::FIELD_GT, self::FIELD_GTE, self::FIELD_LT, self::FIELD_LTE,
    ];

    private function __construct()
    {
    }

    /** Nhan mixed de schema sai dang cung tra false, khong gay TypeError. */
    public static function isValid(mixed $schema): bool
    {
        if (!is_array($schema)) {
            return false;
        }

        return match ($schema[self::FIELD_TYPE] ?? null) {
            self::TYPE_INT => self::isValidIntSchema($schema),
            self::TYPE_FLOAT => self::isValidFloatSchema($schema),
            self::TYPE_DECIMAL => self::isValidDecimalSchema($schema),
            self::TYPE_STRING => self::isValidStringSchema($schema),
            self::TYPE_BOOL => self::isValidBoolSchema($schema),
            self::TYPE_DATE => self::isValidDateSchema($schema),
            self::TYPE_TIME => self::isValidTimeSchema($schema),
            self::TYPE_DATETIME => self::isValidDatetimeSchema($schema),
            default => false,
        };
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu PHP int.
     *
     * Các field hợp lệ: type, nullable, default_value, gt, gte, lt, lte.
     * Bắt buộc: 'type' => 'int'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'int', 'nullable' => false, 'gte' => 1, 'lt' => 100, 'default_value' => 10]
     *
     * Cận phải là int, không nhận chuỗi số, float hoặc bool. Range phải chứa ít nhất một số nguyên trong miền PHP_INT_MIN..PHP_INT_MAX.
     *
     * gt/gte: cận dưới (> / >=); lt/lte: cận trên (< / <=).
     * Mỗi phía chỉ khai báo một cận; có thể bỏ cả hai phía.
     * Nếu có hai cận, cận dưới phải nhỏ hơn cận trên; bằng nhau chỉ hợp lệ
     * khi dùng gte và lte.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidIntSchema(array $arrSchema): bool
    {
        if (!self::isValidBase($arrSchema, self::TYPE_INT, self::RANGE_FIELDS)
            || !self::isValidRange($arrSchema, 'is_int',
                static fn($a, $b): int => $a <=> $b)) {
            return false;
        }

        // Kiem tra range rong tren mien so nguyen, tranh overflow +/- 1.
        $iLower = $arrSchema[self::FIELD_GTE] ?? PHP_INT_MIN;
        $iUpper = $arrSchema[self::FIELD_LTE] ?? PHP_INT_MAX;
        if (array_key_exists(self::FIELD_GT, $arrSchema)) {
            if ($arrSchema[self::FIELD_GT] === PHP_INT_MAX) {
                return false;
            }
            $iLower = $arrSchema[self::FIELD_GT] + 1;
        }
        if (array_key_exists(self::FIELD_LT, $arrSchema)) {
            if ($arrSchema[self::FIELD_LT] === PHP_INT_MIN) {
                return false;
            }
            $iUpper = $arrSchema[self::FIELD_LT] - 1;
        }
        return $iLower <= $iUpper && self::isValidDefaultValue($arrSchema);
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu PHP float.
     *
     * Các field hợp lệ: type, nullable, default_value, gt, gte, lt, lte.
     * Bắt buộc: 'type' => 'float'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'float', 'nullable' => false, 'gte' => 0.0, 'lt' => 1.0, 'default_value' => 0.5]
     *
     * Cận nhận int hoặc float hữu hạn; không nhận chuỗi số, bool, NAN, INF. So sánh cận sau khi ép sang float, theo độ chính xác PHP float. Không kiểm tra khoảng có chứa float biểu diễn được hay không.
     *
     * gt/gte: cận dưới (> / >=); lt/lte: cận trên (< / <=).
     * Mỗi phía chỉ khai báo một cận; có thể bỏ cả hai phía.
     * Nếu có hai cận, cận dưới phải nhỏ hơn cận trên; bằng nhau chỉ hợp lệ
     * khi dùng gte và lte.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidFloatSchema(array $arrSchema): bool
    {
        return self::isValidBase($arrSchema, self::TYPE_FLOAT, self::RANGE_FIELDS)
            && self::isValidRange(
                $arrSchema,
                static fn($v): bool => (is_int($v) || is_float($v)) && is_finite((float) $v),
                static fn($a, $b): int => (float) $a <=> (float) $b
            ) && self::isValidDefaultValue($arrSchema);
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu chuỗi thập phân chính xác.
     *
     * Các field hợp lệ: type, nullable, default_value, gt, gte, lt, lte, scale.
     * Bắt buộc: 'type' => 'decimal'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'decimal', 'nullable' => false, 'scale' => 2, 'gte' => '0', 'lt' => '1000', 'default_value' => '0.00']
     *
     * Cận phải là string: phần nguyên là 0 hoặc bắt đầu bằng 1..9; có thể có dấu âm và phần lẻ dùng dấu chấm. Hợp lệ: '0', '-12', '12.50', '-0.00'. Không nhận float, '+12', '01.2', '.5', '1.', '1e3' hoặc khoảng trắng. So sánh chính xác bằng chuỗi, không ép float.
     * scale: int >= 0, tùy chọn, là số chữ số phần lẻ tối đa của dữ liệu. Bỏ scale nghĩa là không giới hạn này; không yêu cầu làm tròn hoặc thêm số 0. scale không giới hạn phần lẻ của cận. Chưa kiểm tra tính khả thi của tổ hợp scale và range.
     *
     * gt/gte: cận dưới (> / >=); lt/lte: cận trên (< / <=).
     * Mỗi phía chỉ khai báo một cận; có thể bỏ cả hai phía.
     * Nếu có hai cận, cận dưới phải nhỏ hơn cận trên; bằng nhau chỉ hợp lệ
     * khi dùng gte và lte.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidDecimalSchema(array $arrSchema): bool
    {
        return self::isValidBase(
            $arrSchema, self::TYPE_DECIMAL, [...self::RANGE_FIELDS, self::FIELD_SCALE]
        ) && self::isOptionalNonNegativeInt($arrSchema, self::FIELD_SCALE)
            && self::isValidRange(
                $arrSchema,
                static fn($v): bool => is_string($v)
                    && preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/', $v) === 1,
                [self::class, 'compareDecimal']
            ) && self::isValidDefaultValue($arrSchema);
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu PHP string.
     *
     * Các field hợp lệ: type, nullable, default_value, trim, min_length, max_length, format.
     * Bắt buộc: 'type' => 'string'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'string', 'nullable' => false, 'trim' => true, 'min_length' => 1, 'max_length' => 254, 'format' => 'email', 'default_value' => 'user@example.com']
     *
     * trim: bool, mặc định false; true yêu cầu Normalizer trim trước khi kiểm tra constraint.
     * min_length, max_length: int >= 0; độ dài theo Unicode code point sau normalize. Nếu có cả hai thì min_length <= max_length. Bỏ min_length tương đương cận dưới 0; bỏ max_length nghĩa là không giới hạn cận trên.
     * format: tùy chọn; chấp nhận 'email', 'http_url' hoặc 'uuid'. Bỏ field này nếu không yêu cầu semantic format. Không chấp nhận format=null.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidStringSchema(array $arrSchema): bool
    {
        if (!self::isValidBase($arrSchema, self::TYPE_STRING, [
            self::FIELD_TRIM, self::FIELD_MIN_LENGTH,
            self::FIELD_MAX_LENGTH, self::FIELD_FORMAT,
        ])) {
            return false;
        }
        if (array_key_exists(self::FIELD_TRIM, $arrSchema)
            && !is_bool($arrSchema[self::FIELD_TRIM])) {
            return false;
        }
        if (!self::isOptionalNonNegativeInt($arrSchema, self::FIELD_MIN_LENGTH)
            || !self::isOptionalNonNegativeInt($arrSchema, self::FIELD_MAX_LENGTH)) {
            return false;
        }
        if (array_key_exists(self::FIELD_FORMAT, $arrSchema)
            && !in_array($arrSchema[self::FIELD_FORMAT], [
                self::FORMAT_EMAIL,
                self::FORMAT_HTTP_URL,
                self::FORMAT_UUID,
            ], true)) {
            return false;
        }
        return (!array_key_exists(self::FIELD_MAX_LENGTH, $arrSchema)
            || ($arrSchema[self::FIELD_MIN_LENGTH] ?? 0) <= $arrSchema[self::FIELD_MAX_LENGTH])
            && self::isValidDefaultValue($arrSchema);
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu PHP bool.
     *
     * Các field hợp lệ: type, nullable, default_value.
     * Bắt buộc: 'type' => 'bool'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'bool', 'nullable' => false, 'default_value' => false]
     *
     * Chỉ có type, nullable và default_value. Không có range hoặc constraint riêng. Quy tắc chuyển 0/1 hoặc chuỗi sang bool thuộc AtomicNormalizer.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidBoolSchema(array $arrSchema): bool
    {
        return self::isValidBase($arrSchema, self::TYPE_BOOL, [])
            && self::isValidDefaultValue($arrSchema);
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu chuỗi 'Y-m-d'.
     *
     * Các field hợp lệ: type, nullable, default_value, gt, gte, lt, lte.
     * Bắt buộc: 'type' => 'date'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'date', 'nullable' => false, 'gte' => '2026-01-01', 'lt' => '2027-01-01', 'default_value' => '2026-01-01']
     *
     * Cận phải đúng YYYY-MM-DD; năm 0001..9999, ngày lịch hợp lệ, có kiểm tra năm nhuận. Không nhận giờ, timezone hoặc ngày tương đối. So sánh chuỗi canonical theo thứ tự ngày. Chưa kiểm tra khoảng mở có chứa ít nhất một ngày hay không.
     *
     * gt/gte: cận dưới (> / >=); lt/lte: cận trên (< / <=).
     * Mỗi phía chỉ khai báo một cận; có thể bỏ cả hai phía.
     * Nếu có hai cận, cận dưới phải nhỏ hơn cận trên; bằng nhau chỉ hợp lệ
     * khi dùng gte và lte.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidDateSchema(array $arrSchema): bool
    {
        return self::isValidTemporalSchema($arrSchema, self::TYPE_DATE);
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu chuỗi 'H:i:s'.
     *
     * Các field hợp lệ: type, nullable, default_value, gt, gte, lt, lte.
     * Bắt buộc: 'type' => 'time'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'time', 'nullable' => false, 'gte' => '08:00:00', 'lt' => '17:00:00', 'default_value' => '09:00:00']
     *
     * Cận phải đúng HH:MM:SS; giờ 00..23, phút và giây 00..59. Không nhận 24:00:00, giây 60, timezone hoặc microsecond. Range tuyến tính trong một ngày, không vòng qua nửa đêm. Chưa kiểm tra khoảng mở có chứa ít nhất một giây hay không.
     *
     * gt/gte: cận dưới (> / >=); lt/lte: cận trên (< / <=).
     * Mỗi phía chỉ khai báo một cận; có thể bỏ cả hai phía.
     * Nếu có hai cận, cận dưới phải nhỏ hơn cận trên; bằng nhau chỉ hợp lệ
     * khi dùng gte và lte.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidTimeSchema(array $arrSchema): bool
    {
        return self::isValidTemporalSchema($arrSchema, self::TYPE_TIME);
    }

    /**
     * Kiểm tra schema cho giá trị canonical kiểu chuỗi 'Y-m-d H:i:s'.
     *
     * Các field hợp lệ: type, nullable, default_value, gt, gte, lt, lte.
     * Bắt buộc: 'type' => 'datetime'. Các field còn lại đều tùy chọn.
     * nullable: bool, mặc định false; true cho phép dữ liệu mang giá trị null.
     * default_value: tùy chọn, phải đúng kiểu canonical và thỏa constraint.
     * default_value=null chỉ hợp lệ khi nullable=true; default sai trả false.
     * Field lạ, sai kiểu hoặc null ở các field schema khác đều không hợp lệ.
     *
     * Ví dụ schema:
     *     ['type' => 'datetime', 'nullable' => false, 'gte' => '2026-01-01 00:00:00', 'lt' => '2027-01-01 00:00:00', 'default_value' => '2026-01-01 00:00:00']
     *
     * Cận dài đúng 19 ký tự, có một dấu cách giữa ngày và giờ. Ngày hợp lệ với năm 0001..9999; giờ 00..23, phút và giây 00..59. Không nhận dấu T, timezone hoặc microsecond; không chuyển đổi múi giờ. Chưa kiểm tra khoảng mở có chứa ít nhất một giây hay không.
     *
     * gt/gte: cận dưới (> / >=); lt/lte: cận trên (< / <=).
     * Mỗi phía chỉ khai báo một cận; có thể bỏ cả hai phía.
     * Nếu có hai cận, cận dưới phải nhỏ hơn cận trên; bằng nhau chỉ hợp lệ
     * khi dùng gte và lte.
     *
     * Hàm kiểm tra contract, bao gồm default_value; không chuyển đổi giá trị.
     * Không hỗ trợ required, enum, regex hoặc format ngày giờ tùy chỉnh.
     *
     * @param array $arrSchema Array mô tả schema cần kiểm tra.
     * @return bool true nếu schema hợp lệ theo các quy tắc trên.
     */
    public static function isValidDatetimeSchema(array $arrSchema): bool
    {
        return self::isValidTemporalSchema($arrSchema, self::TYPE_DATETIME);
    }

    /**
     * Kiểm tra default SAU khi type, nullable và các constraint đã hợp lệ.
     * Dùng array_key_exists để phân biệt thiếu default với default=null.
     * Không normalize, không thay default sai bằng giá trị khác.
     * Float default phải là PHP float; ví dụ 1.0 hợp lệ, 1 không hợp lệ.
     * Decimal default phải là chuỗi; số 0 cuối phần lẻ vẫn tính vào scale.
     */
    private static function isValidDefaultValue(array $arrSchema): bool
    {
        if (!array_key_exists(self::FIELD_DEFAULT_VALUE, $arrSchema)) {
            return true;
        }
        $value = $arrSchema[self::FIELD_DEFAULT_VALUE];
        if ($value === null) {
            return $arrSchema[self::FIELD_NULLABLE] ?? false;
        }

        $strType = $arrSchema[self::FIELD_TYPE];
        $isCanonical = match ($strType) {
            self::TYPE_INT => is_int($value),
            self::TYPE_FLOAT => is_float($value) && is_finite($value),
            self::TYPE_DECIMAL => is_string($value)
                && preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/', $value) === 1,
            self::TYPE_STRING => is_string($value) && preg_match('//u', $value) === 1,
            self::TYPE_BOOL => is_bool($value),
            self::TYPE_DATE, self::TYPE_TIME, self::TYPE_DATETIME
                => self::isCanonicalTemporal($value, $strType),
            default => false,
        };
        if (!$isCanonical) {
            return false;
        }

        if ($strType === self::TYPE_STRING) {
            // Cùng quy tắc trim ASCII của Normalizer: không bỏ byte NUL.
            if (($arrSchema[self::FIELD_TRIM] ?? false)
                && $value !== trim($value, " \t\r\n\v\f")) {
                return false;
            }
            if (isset($arrSchema[self::FIELD_MIN_LENGTH])
                || isset($arrSchema[self::FIELD_MAX_LENGTH])) {
                // Unicode code point, không phải byte hoặc grapheme cluster.
                $iLength = preg_match_all('/./us', $value);
                if ($iLength === false
                    || (isset($arrSchema[self::FIELD_MIN_LENGTH])
                        && $iLength < $arrSchema[self::FIELD_MIN_LENGTH])
                    || (isset($arrSchema[self::FIELD_MAX_LENGTH])
                        && $iLength > $arrSchema[self::FIELD_MAX_LENGTH])) {
                    return false;
                }
            }
            if (isset($arrSchema[self::FIELD_FORMAT])
                && !StringFormatValidator::isValidFormat(
                    $value,
                    $arrSchema[self::FIELD_FORMAT]
                )) {
                return false;
            }
        }

        if ($strType === self::TYPE_DECIMAL && isset($arrSchema[self::FIELD_SCALE])) {
            $iDot = strpos($value, '.');
            $iScale = $iDot === false ? 0 : strlen($value) - $iDot - 1;
            if ($iScale > $arrSchema[self::FIELD_SCALE]) {
                return false;
            }
        }

        foreach (self::RANGE_FIELDS as $strField) {
            if (!array_key_exists($strField, $arrSchema)) {
                continue;
            }
            $bound = $arrSchema[$strField];
            $iCmp = match ($strType) {
                self::TYPE_INT => $value <=> $bound,
                self::TYPE_FLOAT => $value <=> (float) $bound,
                self::TYPE_DECIMAL => self::compareDecimal($value, $bound),
                default => strcmp($value, $bound) <=> 0,
            };
            $isWithinBound = match ($strField) {
                self::FIELD_GT => $iCmp > 0,
                self::FIELD_GTE => $iCmp >= 0,
                self::FIELD_LT => $iCmp < 0,
                self::FIELD_LTE => $iCmp <= 0,
            };
            if (!$isWithinBound) {
                return false;
            }
        }
        return true;
    }

    private static function isValidBase(array $arrSchema, string $strType, array $arrField): bool
    {
        //phải có field type
        if (($arrSchema[self::FIELD_TYPE] ?? null) !== $strType) {
            return false;
        }
        //$arrAllowed gồm danh sách các field type, nullable,...các field khác
        $arrAllowed = [self::FIELD_TYPE, self::FIELD_NULLABLE, self::FIELD_DEFAULT_VALUE,
            self::FIELD_AUTO_VALUE, ...$arrField];
        //đảm bảo $arrSchema không có field lạ ra ngoài $arrAllowed
        foreach ($arrSchema as $key => $value) {
            if (!in_array($key, $arrAllowed, true)) {
                return false;
            }
        }
        // nullable và auto_value nếu khai báo phải là bool.
        return (!array_key_exists(self::FIELD_NULLABLE, $arrSchema)
                || is_bool($arrSchema[self::FIELD_NULLABLE]))
            && (!array_key_exists(self::FIELD_AUTO_VALUE, $arrSchema)
                || is_bool($arrSchema[self::FIELD_AUTO_VALUE]));
    }

    private static function isOptionalNonNegativeInt(array $arrSchema, string $strField): bool
    {
        return !array_key_exists($strField, $arrSchema)
            || (is_int($arrSchema[$strField]) && $arrSchema[$strField] >= 0);
    }

    private static function isValidRange(array $arrSchema, callable $validFn, callable $compareFn): bool
    {
        //kiểm tra trong $arrSchema nếu có bất kỳ field nào như là lt,lte,gt,gte thì giá trị của nó
        //phải là int, float,.. tùy theo type của dữ liệu
        foreach (self::RANGE_FIELDS as $strField) {
            if (array_key_exists($strField, $arrSchema) && !$validFn($arrSchema[$strField])) {
                return false;
            }
        }
        //đảm bảo không xảy ra những tổ hợp field sai như là [lt, lte], [gt, gte] 
        if ((array_key_exists(self::FIELD_GT, $arrSchema) && array_key_exists(self::FIELD_GTE, $arrSchema))
            || (array_key_exists(self::FIELD_LT, $arrSchema) && array_key_exists(self::FIELD_LTE, $arrSchema))) {
            return false;
        }
        //nếu không tồn tại bất kỳ field nào như là gt,gte,lt,lte thì return true. Đã kiểm tra xong
        $strLower = array_key_exists(self::FIELD_GT, $arrSchema) ? self::FIELD_GT : self::FIELD_GTE;
        $strUpper = array_key_exists(self::FIELD_LT, $arrSchema) ? self::FIELD_LT : self::FIELD_LTE;
        if (!array_key_exists($strLower, $arrSchema) || !array_key_exists($strUpper, $arrSchema)) {
            return true;
        }
        $iCmp = $compareFn($arrSchema[$strLower], $arrSchema[$strUpper]);
        return $iCmp < 0 || ($iCmp === 0 && $strLower === self::FIELD_GTE && $strUpper === self::FIELD_LTE);
    }

    /** So sanh chinh xac bound decimal da kiem tra cu phap; khong can BCMath. */
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
   
    private static function isValidTemporalSchema(array $arrSchema, string $strType): bool
    {
        return self::isValidBase($arrSchema, $strType, self::RANGE_FIELDS)
            && self::isValidRange(
                $arrSchema,
                static fn($v): bool => self::isCanonicalTemporal($v, $strType),
                static fn($a, $b): int => strcmp($a, $b) <=> 0
            ) && self::isValidDefaultValue($arrSchema);
    }

    private static function isCanonicalTemporal(mixed $value, string $strType): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $strDate = $strType === self::TYPE_DATETIME ? substr($value, 0, 10) : $value;
        $strTime = $strType === self::TYPE_DATETIME ? substr($value, 11) : $value;
        if ($strType === self::TYPE_DATETIME && (strlen($value) !== 19 || $value[10] !== ' ')) {
            return false;
        }
        if ($strType !== self::TYPE_TIME) {
            if (preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $strDate, $arrMatch) !== 1
                || !checkdate((int) $arrMatch[2], (int) $arrMatch[3], (int) $arrMatch[1])) {
                return false;
            }
        }
        return $strType === self::TYPE_DATE
            || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\z/', $strTime) === 1;
    }
}
