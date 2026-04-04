<?php
/*Connection sau này sẽ chỉ tạo môt thực thể được chứa trong App nên connection kiến trúc
bằng các hàm và biến static 
Sử dụng setCurentConnection để chuyển đổi các connection khác nhau
 */
namespace Core\Models\Connection;
use \PDO;
use \PDOException;
use \RuntimeException;
class Connection{
    protected static array $arrConfigConnect = [];
    protected static array $arrInstance      = [];//các thực thể kết nối dạng PDO
    protected static string $strCurrentConnection = "default_connect";
    /*--------------------------------------------------------------------------------------*/
    function __construct(array $arrConfigConnect, string $strCurrentConnection ='default_connect'){
        if(!isset($arrConfigConnect[strtoupper($strCurrentConnection)])){
            throw new InvalidArgumentException('arrConfigConnect không có key '.$strCurrentConnection);
        }
        foreach ($arrConfigConnect as $key => $value) {
            if(!ConnectionParam::isValid($value)){
                throw new InvalidArgumentException("Các tham số connection tại key {$key} không hợp lệ");
            }
        }
        self::$arrConfigConnect = $arrConfigConnect;
        self::$strCurrentConnection = $strCurrentConnection;
    }
    /*--------------------------------------------------------------------------------------*/
    public static function setConfigs(array $arrConfigConnect): void {
        self::$arrConfigConnect = $arrConfigConnect;
    }
    /*--------------------------------------------------------------------------------------*/
    public static function setCurentConnection(string $strCurrConnection){
        self::$strCurrentConnection = $strCurrConnection;
    }
    /*--------------------------------------------------------------------------------------*/
    public static function get(): PDO {
        $strName = strtoupper(self::$strCurrentConnection);
        if (isset(self::$arrInstance[$strName])) {
            return self::$arrInstance[$strName];
        }
        if (!isset(self::$arrConfigConnect[$strName])) {
            throw new RuntimeException("Database config '$strName' not found.");
        }

        $cfg = self::$arrConfigConnect[$strName];
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $cfg['DB_SERVER'],
            $cfg['DB_NAME'],
            $cfg['DB_CHARSET'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO($dsn, $cfg['DB_USER'], $cfg['DB_PASSWORD'], [
                //PDO::ATTR_ERRMODE => $cfg['errmode'] ?? PDO::ERRMODE_SILENT
                PDO::ATTR_ERRMODE => $cfg['errmode'] ?? PDO::ERRMODE_EXCEPTION
            ]);
        } catch (PDOException $e) {
            die("Connection error ($strName): " . $e->getMessage());
        }

        return self::$arrInstance[$strName] = $pdo;
    }
    /*--------------------------------------------------------------------------------------*/
}
