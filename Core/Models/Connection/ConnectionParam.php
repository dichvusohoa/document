<?php
/*Connection sau này sẽ chỉ tạo môt thực thể được chứa trong App nên connection kiến trúc
bằng các hàm và biến static */
namespace Core\Models\Connection;
class ConnectionParam{
    public static function isValid(array $arrData): bool {
        return is_array($arrData)
        && isset($arrData['DB_SERVER']) && is_string($arrData['DB_SERVER'])     
        && isset($arrData['DB_NAME']) && is_string($arrData['DB_NAME'])
        && isset($arrData['DB_USER']) && is_string($arrData['DB_USER'])
        && isset($arrData['DB_PASSWORD']) && is_string($arrData['DB_PASSWORD']);
    }
    
}
