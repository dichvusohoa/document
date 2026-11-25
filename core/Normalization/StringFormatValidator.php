<?php
declare(strict_types=1);

namespace Core\Normalization;

/**
 * Các validator format bổ sung cho atomic type string.
 *
 * Lớp này chỉ kiểm tra format, không chuẩn hóa chuỗi. Chuẩn hóa chung như trim,
 * kiểm tra UTF-8 và giới hạn độ dài vẫn thuộc AtomicNormalizer.
 */
final class StringFormatValidator
{
    private function __construct()
    {
    }

    /**
     * Kiểm tra chuỗi theo format đã khai báo.
     *
     * Schema phải được kiểm tra trước bằng AtomicSchema. Format không được hỗ
     * trợ trả về false.
     */
    public static function isValidFormat(string $value, string $strFormat): bool
    {
        return match ($strFormat) {
            AtomicSchema::FORMAT_EMAIL => self::isValidEmail($value),
            AtomicSchema::FORMAT_HTTP_URL => self::isValidHttpUrl($value),
            AtomicSchema::FORMAT_UUID => self::isValidUuid($value),
            default => false,
        };
    }

    /** Kiểm tra cú pháp email theo phạm vi bộ lọc email tích hợp của PHP. */
    private static function isValidEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Kiểm tra URL tuyệt đối dùng HTTP hoặc HTTPS.
     * Không thực hiện DNS lookup hoặc kết nối mạng; chỉ hỗ trợ cú pháp URL mà
     * parse_url() và FILTER_VALIDATE_URL của PHP nhận diện được.
     */
    private static function isValidHttpUrl(string $value): bool
    {
        if ($value === '' || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            return false;
        }

        try {
            $arrParts = parse_url($value);
        } catch (\ValueError) {
            return false;
        }

        if (!is_array($arrParts)
            || !isset($arrParts['scheme'], $arrParts['host'])
            || $arrParts['host'] === ''
            || !in_array(strtolower($arrParts['scheme']), ['http', 'https'], true)
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // parse_url() vẫn có thể trả về port ngoài miền TCP hợp lệ.
        return !isset($arrParts['port'])
            || ($arrParts['port'] >= 0 && $arrParts['port'] <= 65535);
    }

    /** Kiểm tra dạng UUID canonical 8-4-4-4-12 ký tự hex. */
    private static function isValidUuid(string $value): bool
    {
        // Không nhận braces hoặc tiền tố urn:uuid:; không kiểm tra version/variant.
        return preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i',
            $value
        ) === 1;
    }
}
