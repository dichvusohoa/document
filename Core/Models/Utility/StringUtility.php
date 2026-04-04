<?php
namespace Core\Models\Utility;
use \InvalidArgumentException;
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
    
    
}
