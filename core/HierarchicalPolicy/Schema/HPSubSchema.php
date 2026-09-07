<?php
namespace Core\HierarchicalPolicy\Schema;

use Core\Utility\ValidUtility;
use InvalidArgumentException;

final class HPSubSchema
{
    /*
     * Database chứa toàn bộ các source thuộc HP Schema.
     *
     * null:
     *     sử dụng current database.
     */
    public const DB_NAME = 'db_name';

    /*
     * Sections thuộc HPSubSchema.
     */
    public const SECTION_NODE =
        'node';

    public const SECTION_POLICY =
        'policy';

    /*
     * Fields chung.
     */
    public const FIELD_SOURCE =
        'source';

    public const FIELD_ID =
        'id_field';

    public const FIELD_PARENT =
        'parent_field';

    public const FIELD_NODE_ID =
        'node_id_field';

    public const FIELD_RESOURCE_ID =
        'resource_id_field';

    public const FIELD_POLICY_ID =
        'policy_id_field';

    /*
     * Fields dùng trong policy-value option.
     */
    public const FIELD_IS_DIRECT =
        'is_direct';

    /*
     * Tên field chứa đặc tính của Policy tại Scope
     * mà Policy được bind.
     *
     * Scope có thể là Node hoặc Resource.
     *
     * DIRECT:
     *     attribute này chính là final policy value.
     *
     * LINK:
     *     attribute này được đưa vào SQL context
     *     để function prototype có thể sử dụng khi
     *     tính final policy value.
     *
     * Field này luôn bắt buộc tồn tại.
     *
     * Ví dụ:
     *
     *     permission_mask
     */
    public const FIELD_POLICY_ATTRIBUTE_ON_SCOPE =
        'field_policy_attribute_on_scope';

    /*
     * Tên column trong POLICY chứa function prototype.
     *
     * Ví dụ option:
     *
     *     'function_prototype_field' => 'funct_prototype'
     *
     * Dữ liệu trong POLICY:
     *
     *     fn1(a,b,c,permission_mask)
     *     fn2(d,e,f)
     *
     * Prototype phải đầy đủ và tường minh.
     * Stored Procedure không tự thêm parameter.
     */
    public const FIELD_FUNCTION_PROTOTYPE_FIELD =
        'function_prototype_field';

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Tạo phần schema chung cho HPNodeSchema
     * và HPResourceSchema.
     *
     * arrOption là policy-value option dạng phẳng.
     *
     * DIRECT:
     *
     * [
     *     'is_direct' => true,
     *
     *     'field_policy_attribute_on_scope' =>
     *         'permission_mask'
     * ]
     *
     * LINK:
     *
     * [
     *     'is_direct' => false,
     *
     *     'source' =>
     *         'view_funct_param',
     *
     *     'function_prototype_field' =>
     *         'funct_prototype',
     *
     *     'field_policy_attribute_on_scope' =>
     *         'permission_mask'
     * ]
     */
    public static function createEmpty(
        array $arrOption
    ): array {
        if (!self::isOptionValid($arrOption)) {
            throw new InvalidArgumentException(
                'arrOption của HPSubSchema không đúng format.'
            );
        }

        $isDirect =
            $arrOption[
                self::FIELD_IS_DIRECT
            ];

        /*
         * Các section thuộc HPSubSchema luôn tồn tại.
         */
        $arrSubSchema = [
            self::DB_NAME => null,

            self::SECTION_NODE => [
                self::FIELD_SOURCE => null,
                self::FIELD_ID     => null,
                self::FIELD_PARENT => null,
            ],

            self::SECTION_POLICY => [
                self::FIELD_SOURCE => null,
                self::FIELD_ID     => null,
            ],
        ];

        /*
         * LINK:
         *
         * POLICY phải có column chứa function prototype.
         */
        if (!$isDirect) {
            $arrSubSchema[
                self::SECTION_POLICY
            ][
                self::FIELD_FUNCTION_PROTOTYPE_FIELD
            ] =
                $arrOption[
                    self::FIELD_FUNCTION_PROTOTYPE_FIELD
                ];
        }

        return $arrSubSchema;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Chỉ validate phần schema thuộc trách nhiệm
     * của HPSubSchema.
     *
     * arrHPSchema có thể chứa thêm các section riêng của
     * HPNodeSchema hoặc HPResourceSchema.
     */
    public static function isValid(
        array $arrHPSchema,
        array $arrOption
    ): bool {
        if (!self::isOptionValid($arrOption)) {
            return false;
        }

        /*
         * Các top-level field thuộc HPSubSchema
         * bắt buộc phải tồn tại.
         *
         * Không dùng hasExactFields() tại đây vì arrHPSchema
         * có thể là HPNodeSchema hoặc HPResourceSchema hoàn chỉnh.
         */
        if (
            !array_key_exists(
                self::DB_NAME,
                $arrHPSchema
            )
            || !array_key_exists(
                self::SECTION_NODE,
                $arrHPSchema
            )
            || !array_key_exists(
                self::SECTION_POLICY,
                $arrHPSchema
            )
        ) {
            return false;
        }

        /*
         * Database.
         */
        if (
            $arrHPSchema[self::DB_NAME] !== null
            && !self::isIdentifier(
                $arrHPSchema[self::DB_NAME]
            )
        ) {
            return false;
        }

        /*
         * NODE.
         */
        if (
            !self::isSectionValid(
                $arrHPSchema[
                    self::SECTION_NODE
                ],
                [
                    self::FIELD_SOURCE,
                    self::FIELD_ID,
                    self::FIELD_PARENT,
                ]
            )
        ) {
            return false;
        }

        /*
         * POLICY.
         */
        $isDirect =
            $arrOption[
                self::FIELD_IS_DIRECT
            ];

        $arrPolicyField = [
            self::FIELD_SOURCE,
            self::FIELD_ID,
        ];

        if (!$isDirect) {
            $arrPolicyField[] =
                self::FIELD_FUNCTION_PROTOTYPE_FIELD;
        }

        if (
            !self::isSectionValid(
                $arrHPSchema[
                    self::SECTION_POLICY
                ],
                $arrPolicyField
            )
        ) {
            return false;
        }

        /*
         * LINK:
         *
         * function_prototype_field đã được option xác định.
         * Schema không được thay sang column khác.
         */
        if (
            !$isDirect
            && $arrHPSchema[
                self::SECTION_POLICY
            ][
                self::FIELD_FUNCTION_PROTOTYPE_FIELD
            ]
            !==
            $arrOption[
                self::FIELD_FUNCTION_PROTOTYPE_FIELD
            ]
        ) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * isSectionValid()
     * validate:
     *
     * 1. section là array
     * 2. đúng chính xác các key
     * 3. value của tất cả key là identifier hợp lệ
     *
     * Helper dùng chung cho HPSubSchema,
     * HPNodeSchema và HPResourceSchema.
     */
    public static function isSectionValid(
        mixed $mixSection,
        array $arrField
    ): bool {
        if (!is_array($mixSection)) {
            return false;
        }

        if (
            !ValidUtility::hasExactFields(
                $mixSection,
                $arrField
            )
        ) {
            return false;
        }

        foreach ($arrField as $strField) {
            if (
                !self::isIdentifier(
                    $mixSection[$strField]
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * isIdentifier() là validator cho tên database object /
     * field dùng trong dynamic SQL, trong đó tên column là
     * trường hợp sử dụng nhiều nhất.
     *
     * Identifier được sử dụng để dựng dynamic SQL.
     *
     * Helper dùng chung cho HPSubSchema,
     * HPNodeSchema và HPResourceSchema.
     */
    public static function isIdentifier(
        mixed $mixValue
    ): bool {
        return
            ValidUtility::isNonEmptyString($mixValue)
            && preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $mixValue
            ) === 1;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * arrOption chính là policy-value option dạng phẳng.
     */
    public static function isOptionValid(
        array $arrOption
    ): bool {
        if (
            !array_key_exists(
                self::FIELD_IS_DIRECT,
                $arrOption
            )
            || !is_bool(
                $arrOption[
                    self::FIELD_IS_DIRECT
                ]
            )
        ) {
            return false;
        }

        $isDirect =
            $arrOption[
                self::FIELD_IS_DIRECT
            ];

        /*
         * DIRECT:
         *
         * [
         *     'is_direct' => true,
         *
         *     'field_policy_attribute_on_scope' =>
         *         'permission_mask'
         * ]
         */
        if ($isDirect) {
            if (
                !ValidUtility::hasExactFields(
                    $arrOption,
                    [
                        self::FIELD_IS_DIRECT,
                        self::FIELD_POLICY_ATTRIBUTE_ON_SCOPE,
                    ]
                )
            ) {
                return false;
            }

            return self::isIdentifier(
                $arrOption[
                    self::FIELD_POLICY_ATTRIBUTE_ON_SCOPE
                ]
            );
        }

        /*
         * LINK:
         *
         * [
         *     'is_direct' => false,
         *
         *     'source' =>
         *         'view_funct_param',
         *
         *     'function_prototype_field' =>
         *         'funct_prototype',
         *
         *     'field_policy_attribute_on_scope' =>
         *         'permission_mask'
         * ]
         */
        if (
            !ValidUtility::hasExactFields(
                $arrOption,
                [
                    self::FIELD_IS_DIRECT,
                    self::FIELD_SOURCE,
                    self::FIELD_FUNCTION_PROTOTYPE_FIELD,
                    self::FIELD_POLICY_ATTRIBUTE_ON_SCOPE,
                ]
            )
        ) {
            return false;
        }

        if (
            !self::isIdentifier(
                $arrOption[
                    self::FIELD_SOURCE
                ]
            )
            || !self::isIdentifier(
                $arrOption[
                    self::FIELD_FUNCTION_PROTOTYPE_FIELD
                ]
            )
            || !self::isIdentifier(
                $arrOption[
                    self::FIELD_POLICY_ATTRIBUTE_ON_SCOPE
                ]
            )
        ) {
            return false;
        }

        return true;
    }
}