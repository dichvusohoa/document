<?php
namespace Core\Routing;
use Core\Utility\ValidUtility;
class MCAOInfo {
    public const FIELD_MODULE                 = 'module';
    public const FIELD_CONTROLLER             = 'controller';
    public const FIELD_ACTION                 = 'action';
    public const FIELD_OTHER_PARAMS           = 'other_params';
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function createEmpty(): array
    {
        return [
            self::FIELD_MODULE       => null,
            self::FIELD_CONTROLLER   => null,
            self::FIELD_ACTION       => null,
            self::FIELD_OTHER_PARAMS => null
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(mixed $arrData): bool
    {
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
     //chuyển định dạng $arrMCAO sang định dạng [$strModule, $strController, $strAction]
    //hoặc định dạng [$strController, $strAction] khi khuyết module. Định dạng simple này thường dùng
    //thường dùng cho truy xuất StaticRouter
    public static function toMCAPath(array $arrMCAO): array
    {
        $strModule     = $arrMCAO[self::FIELD_MODULE];
        $strController = $arrMCAO[self::FIELD_CONTROLLER];
        $strAction     = $arrMCAO[self::FIELD_ACTION];

        return $strModule === null
            ? [$strController, $strAction]
            : [$strModule, $strController, $strAction];
    }
}