<?php

namespace Core\Routing;
use Core\Utility\ValidUtility;
use UnexpectedValueException;
final class RoleRegistry
{
    public const FIELD_DISPLAY_NAME = 'display_name';
    public const FIELD_DEFAULT_BUSINESS_URL  = 'default_business_url';
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
    protected function loadConfig(): array
    {
        $strFileName = 'config.role.php';
        $arrTmp = require CONFIG_PATH . '/' . $strFileName;

        if (!is_array($arrTmp)) {
            throw new UnexpectedValueException(
                "File {$strFileName} phải trả về một mảng."
            );
        }

        foreach ($arrTmp as $strRole => $arrRoleInfo) {
            if (!ValidUtility::isNonEmptyString($strRole)) {
                throw new UnexpectedValueException(
                    "Tên role trong {$strFileName} phải là chuỗi không rỗng "
                    . "và không có khoảng trắng ở đầu hoặc cuối."
                );
            }
            if (!is_array($arrRoleInfo)) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}' trong {$strFileName} phải là một mảng."
                );
            }

            $strContext = "Role '{$strRole}'";

            ValidUtility::validateNoUnexpectedFields(
                $arrRoleInfo,
                [
                    self::FIELD_DISPLAY_NAME,
                    self::FIELD_DEFAULT_BUSINESS_URL,
                    self::FIELD_WEIGHT
                ],
                $strContext
            );

            ValidUtility::validateRequiredField(
                $arrRoleInfo,
                self::FIELD_DISPLAY_NAME,
                $strContext,
                [
                    'type'      => 'string',
                    'non_empty' => true,
                    'trimmed'   => true
                ]
            );
            
            ValidUtility::validateRequiredField(
                $arrRoleInfo,
                self::FIELD_DEFAULT_BUSINESS_URL,
                $strContext,
                [
                    'type'      => '?string',
                    'non_empty' => true,
                    'trimmed'   => true
                ]
            );
            
            ValidUtility::validateRequiredField(
                $arrRoleInfo,
                self::FIELD_WEIGHT,
                $strContext,
                [
                    'type' => 'int',
                    'min'  => 0
                ]
            );

            if (
                !ValidUtility::isInternalAbsolutePath(
                    $arrRoleInfo[self::FIELD_DEFAULT_BUSINESS_URL]
                )
            ) {
                throw new UnexpectedValueException(
                    "{$strContext}: field '"
                    . self::FIELD_DEFAULT_BUSINESS_URL
                    . "' phải là đường dẫn tuyệt đối nội bộ hợp lệ."
                );
            }
        }

        // Framework bắt buộc phải có role guest.
        if (!isset($arrTmp['guest'])) {
            throw new UnexpectedValueException(
                "File {$strFileName} phải định nghĩa role 'guest'."
            );
        }

        if ($arrTmp['guest'][self::FIELD_WEIGHT] !== 0) {
            throw new UnexpectedValueException(
                "Role 'guest' phải có weight = 0."
            );
        }

        return $arrTmp;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function findDefaultBusinessUrlByRoles(array $arrRole): ?string
    {
        $iMaxWeight = -1;
        $strNeedleRole = null;

        foreach ($arrRole as $strRole) {
            if (!isset($this->arrR[$strRole])) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}' không được định nghĩa trong config.role.php."
                );
            }

            if ($this->arrR[$strRole][self::FIELD_WEIGHT] > $iMaxWeight) {
                $iMaxWeight = $this->arrR[$strRole][self::FIELD_WEIGHT];
                $strNeedleRole = $strRole;
            }
        }

        if ($strNeedleRole !== null) {
            return $this->arrR[$strNeedleRole][self::FIELD_DEFAULT_BUSINESS_URL];
        }

        return null;
    }
}