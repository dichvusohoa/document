<?php

namespace Core\Routing;
use Core\Utility\ValidUtility;
use UnexpectedValueException;
final class RoleRegistry
{
    public const FIELD_DISPLAY_NAME = 'display_name';
    public const FIELD_DEFAULT_URL  = 'default_url';
    public const FIELD_WEIGHT       = 'weight';

    protected array $arrR;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct()
    {        
        $this->arrR = $this->loadConfig();
        //dự kiến có các hàm sau này, hiện nay chỉ có 1 dòng loadConfig
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRoleRegistry(){
        return $this->arrR;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function validadeDefaultUrl(UrlToMCAOParser $parser, array $arrMCAR){
        foreach ($this->arrR as $strRole => $arrRoleEntry){
            $arrMCAO = $parser->parse($arrRoleEntry['default_url']);
            if ($arrMCAO === null) {
                throw new HttpException(404, '....');
            }
            $arrMCA = MCAOInfo::toMCAPath($arrMCAO);
            $arrRouteInfo  = StaticRouter::routeInfo($arrMCAR, $arrMCA);
            if ($arrRouteInfo === null) {
                throw new HttpException(404, '....');
            }
            if(!in_array($strRole, $arrRouteInfo['roles'], true)){
                throw new HttpException(500, '....');
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function fromArray(array $arrRoleRegistry): self{
        $ref = new \ReflectionClass(self::class);

        /** @var self $obj */
        $obj = $ref->newInstanceWithoutConstructor();

        $obj->arrRoleRegistry = $arrRoleRegistry;

        return $obj;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadConfig(): array{
        $strFileName = 'config.role.php';
        $arrTmp = require CONFIG_PATH . '/' . $strFileName;

        if (!is_array($arrTmp)) {
            throw new UnexpectedValueException(
                "File {$strFileName} phải trả về một mảng."
            );
        }

        foreach ($arrTmp as $strRole => $arrRoleInfo) {
            if (!is_string($strRole) || trim($strRole) === '') {
                throw new UnexpectedValueException(
                    "Tên role trong {$strFileName} phải là chuỗi không rỗng."
                );
            }

            if (!is_array($arrRoleInfo)) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}' trong {$strFileName} phải là một mảng."
                );
            }

            ValidUtility::validateNoUnexpectedFields(
                $arrRoleInfo,
                [
                    'display_name',
                    'default_url',
                    'weight'
                ],
                "Role '{$strRole}'"
            );

            ValidUtility::validateRequiredNonEmptyStringField(
                $arrRoleInfo,
                'display_name',
                "Role '{$strRole}'"
            );

            ValidUtility::validateRequiredNonEmptyStringField(
                $arrRoleInfo,
                'default_url',
                "Role '{$strRole}'"
            );

            ValidUtility::validateRequiredField(
                $arrRoleInfo,
                'weight',
                "Role '{$strRole}'"
            );

            if (
                !ValidUtility::isInternalAbsolutePath(
                    $arrRoleInfo['default_url']
                )
            ) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}': field 'default_url' "
                    . "phải là đường dẫn tuyệt đối nội bộ hợp lệ."
                );
            }

            if (
                !is_int($arrRoleInfo['weight'])
                || $arrRoleInfo['weight'] < 0
            ) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}': field 'weight' "
                    . "phải là số nguyên không âm."
                );
            }
        }

        // Framework bắt buộc phải có role guest.
        if (!isset($arrTmp['guest'])) {
            throw new UnexpectedValueException(
                "File {$strFileName} phải định nghĩa role 'guest'."
            );
        }

        if ($arrTmp['guest']['weight'] !== 0) {
            throw new UnexpectedValueException(
                "Role 'guest' phải có weight = 0."
            );
        }
        return $arrTmp;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    
}