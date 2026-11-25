<?php
namespace Core\Utility;
use Core\Normalization\AtomicSchema;
use Core\Normalization\AtomicNormalizer;
class StringUtility{
    
    /**
     * Chuyển tất cả khoảng trắng trong chuỗi thành dấu '-' 
     * và loại bỏ khoảng trắng thừa ở đầu/cuối.
     *
     * Ví dụ: " ab  cde   fg " → "ab-cde-fg"
     *
     * @param string $str Chuỗi đầu vào
     * @return string Chuỗi đã chuẩn hóa
     */
    static public function spacesToDash(string $str): string {
        // Loại bỏ khoảng trắng đầu/cuối
        $str = trim($str);
        // Thay nhiều khoảng trắng liên tiếp bằng một dấu '-'
        $str = preg_replace('/\s+/', '-', $str);
        return $str;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra value là string không rỗng
     * và đã ở dạng trim canonical.
     */
    public static function isNonEmptyString(
        mixed $value
    ): bool {
        return AtomicNormalizer::isValid(
            $value,
            [
                AtomicSchema::FIELD_TYPE
                    => AtomicSchema::TYPE_STRING,

                AtomicSchema::FIELD_TRIM
                    => true,

                AtomicSchema::FIELD_MIN_LENGTH
                    => 1
            ]
        );
    }
    
}
