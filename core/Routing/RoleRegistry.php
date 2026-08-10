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
    public function getRoleRegistry(): array{
        return $this->arrR;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function fromArray(array $arrRoleRegistry): self{
        $ref = new \ReflectionClass(self::class);
        /** @var self $obj */
        $obj = $ref->newInstanceWithoutConstructor();
        $obj->arrR = $arrRoleRegistry;
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
                    self::FIELD_DISPLAY_NAME,
                    self::FIELD_DEFAULT_URL,
                    self::FIELD_WEIGHT
                ],
                "Role '{$strRole}'"
            );

            ValidUtility::validateRequiredNonEmptyStringField(
                $arrRoleInfo,
                self::FIELD_DISPLAY_NAME,
                "Role '{$strRole}'"
            );

            ValidUtility::validateRequiredNonEmptyStringField(
                $arrRoleInfo,
                self::FIELD_DEFAULT_URL,
                "Role '{$strRole}'"
            );

            ValidUtility::validateRequiredField(
                $arrRoleInfo,
                self::FIELD_WEIGHT,
                "Role '{$strRole}'"
            );

            if (
                !ValidUtility::isInternalAbsolutePath(
                    $arrRoleInfo[self::FIELD_DEFAULT_URL]
                )
            ) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}': field '".self::FIELD_DEFAULT_URL."' "
                    . "phải là đường dẫn tuyệt đối nội bộ hợp lệ."
                );
            }

            if (
                !is_int($arrRoleInfo[self::FIELD_WEIGHT])
                || $arrRoleInfo[self::FIELD_WEIGHT] < 0
            ) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}': field '".self::FIELD_WEIGHT."' "
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