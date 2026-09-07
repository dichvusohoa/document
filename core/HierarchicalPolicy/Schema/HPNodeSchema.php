<?php

namespace Core\HierarchicalPolicy\Schema;

use Core\Utility\ValidUtility;
use InvalidArgumentException;

final class HPNodeSchema
{
    /*
     * Node-specific sections.
     */
    public const SECTION_NODE_POLICY =
        'node_policy';

    public const SECTION_POLICY_FUNCTION_COST =
        'policy_function_cost';

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * arrOption chính là policy-value option dạng phẳng.
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
        if (!HPSubSchema::isOptionValid($arrOption)) {
            throw new InvalidArgumentException(
                'arrOption của HPNodeSchema không đúng format.'
            );
        }

        $isDirect =
            $arrOption[
                HPSubSchema::FIELD_IS_DIRECT
            ];

        $strPolicyAttributeOnScope =
            $arrOption[
                HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE
            ];

        /*
         * Phần schema chung:
         *
         * DB_NAME
         * NODE
         * POLICY
         */
        $arrHPSchema =
            HPSubSchema::createEmpty(
                $arrOption
            );

        /*
         * Policy áp lên Node.
         *
         * NODE_POLICY luôn tồn tại.
         *
         * Policy attribute on scope cũng luôn tồn tại.
         */
        $arrHPSchema[
            self::SECTION_NODE_POLICY
        ] = self::createNodePolicySection(
            $strPolicyAttributeOnScope
        );

        /*
         * LINK:
         *
         * Source cung cấp các parameter ngoài binding
         * cho Stored Function.
         *
         * Contract:
         *
         *     node_id
         *     policy_id
         *     param_1
         *     param_2
         *     ...
         *
         * FIELD_POLICY_ATTRIBUTE_ON_SCOPE không do source này
         * cung cấp. Giá trị luôn lấy từ effective NODE_POLICY
         * binding.
         */
        if (!$isDirect) {
            $arrHPSchema[
                self::SECTION_POLICY_FUNCTION_COST
            ] = [
                HPSubSchema::FIELD_SOURCE =>
                    $arrOption[
                        HPSubSchema::FIELD_SOURCE
                    ],

                HPSubSchema::FIELD_NODE_ID =>
                    null,

                HPSubSchema::FIELD_POLICY_ID =>
                    null,
            ];
        }

        return $arrHPSchema;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(
        array $arrHPSchema,
        array $arrOption
    ): bool {
        if (!HPSubSchema::isOptionValid($arrOption)) {
            return false;
        }

        /*
         * HPNodeSchema là schema hoàn chỉnh.
         *
         * createEmpty() xác định chính xác các top-level
         * section được phép tồn tại với variant hiện tại.
         */
        $arrExpected =
            self::createEmpty(
                $arrOption
            );

        if (
            !ValidUtility::hasExactFields(
                $arrHPSchema,
                array_keys($arrExpected)
            )
        ) {
            return false;
        }

        /*
         * Validate phần chung:
         *
         * DB_NAME
         * NODE
         * POLICY
         */
        if (
            !HPSubSchema::isValid(
                $arrHPSchema,
                $arrOption
            )
        ) {
            return false;
        }

        $strPolicyAttributeOnScope =
            $arrOption[
                HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE
            ];

        /*
         * NODE_POLICY.
         */
        if (
            !self::isNodePolicySectionValid(
                $arrHPSchema[
                    self::SECTION_NODE_POLICY
                ],
                $strPolicyAttributeOnScope
            )
        ) {
            return false;
        }

        /*
         * POLICY_FUNCTION_COST.
         */
        $isDirect =
            $arrOption[
                HPSubSchema::FIELD_IS_DIRECT
            ];

        if (!$isDirect) {
            $arrPolicyFunctionCost =
                $arrHPSchema[
                    self::SECTION_POLICY_FUNCTION_COST
                ];

            if (
                !HPSubSchema::isSectionValid(
                    $arrPolicyFunctionCost,
                    [
                        HPSubSchema::FIELD_SOURCE,
                        HPSubSchema::FIELD_NODE_ID,
                        HPSubSchema::FIELD_POLICY_ID,
                    ]
                )
            ) {
                return false;
            }

            /*
             * Source đã được policy-value option xác định.
             * Schema không được thay sang source khác.
             */
            if (
                $arrPolicyFunctionCost[
                    HPSubSchema::FIELD_SOURCE
                ]
                !==
                $arrOption[
                    HPSubSchema::FIELD_SOURCE
                ]
            ) {
                return false;
            }
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function createNodePolicySection(
        string $strPolicyAttributeOnScope
    ): array {
        return [
            HPSubSchema::FIELD_SOURCE =>
                null,

            HPSubSchema::FIELD_NODE_ID =>
                null,

            HPSubSchema::FIELD_POLICY_ID =>
                null,

            HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE =>
                $strPolicyAttributeOnScope,
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isNodePolicySectionValid(
        mixed $mixSection,
        string $strPolicyAttributeOnScope
    ): bool {
        if (
            !HPSubSchema::isSectionValid(
                $mixSection,
                [
                    HPSubSchema::FIELD_SOURCE,
                    HPSubSchema::FIELD_NODE_ID,
                    HPSubSchema::FIELD_POLICY_ID,
                    HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE,
                ]
            )
        ) {
            return false;
        }

        return self::isPolicyAttributeOnScopeValid(
            $mixSection,
            $strPolicyAttributeOnScope
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isPolicyAttributeOnScopeValid(
        array $arrSection,
        string $strPolicyAttributeOnScope
    ): bool {
        return
            $arrSection[
                HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE
            ] === $strPolicyAttributeOnScope;
    }
}