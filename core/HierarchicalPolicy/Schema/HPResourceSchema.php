<?php

namespace Core\HierarchicalPolicy\Schema;

use Core\Utility\ValidUtility;
use InvalidArgumentException;

final class HPResourceSchema
{
    /*
     * Resource-specific sections.
     */
    public const SECTION_RESOURCE =
        'resource';

    public const SECTION_NODE_RESOURCE =
        'node_resource';

    public const SECTION_RESOURCE_NODE_POLICY =
        'resource_node_policy';

    public const SECTION_RESOURCE_POLICY =
        'resource_policy';

    public const SECTION_POLICY_FUNCTION_COST =
        'policy_function_cost';

    /*
     * Options.
     */
    public const OPTION_RESOURCE_NODE_ONE_TO_MANY =
        'resource_node_one_to_many';

    public const OPTION_HAS_DIRECT_RESOURCE_POLICY =
        'has_direct_resource_policy';

    public const OPTION_POLICY_VALUE =
        'policy_value';

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function createEmpty(
        array $arrOption
    ): array {
        if (!self::isOptionValid($arrOption)) {
            throw new InvalidArgumentException(
                'arrOption của HPResourceSchema không đúng format.'
            );
        }

        $isResourceNodeOneToMany =
            $arrOption[
                self::OPTION_RESOURCE_NODE_ONE_TO_MANY
            ];

        $hasDirectResourcePolicy =
            $arrOption[
                self::OPTION_HAS_DIRECT_RESOURCE_POLICY
            ];

        $arrPolicyValue =
            $arrOption[
                self::OPTION_POLICY_VALUE
            ];

        $isDirect =
            $arrPolicyValue[
                HPSubSchema::FIELD_IS_DIRECT
            ];

        $strPolicyAttributeOnScope =
            $arrPolicyValue[
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
                $arrPolicyValue
            );

        /*
         * RESOURCE luôn tồn tại.
         */
        $arrHPSchema[
            self::SECTION_RESOURCE
        ] = [
            HPSubSchema::FIELD_SOURCE => null,
            HPSubSchema::FIELD_ID     => null,
        ];

        /*
         * Quan hệ Resource - Node 1-N.
         *
         * node_id nằm trực tiếp trong RESOURCE.
         */
        if ($isResourceNodeOneToMany) {
            $arrHPSchema[
                self::SECTION_RESOURCE
            ][
                HPSubSchema::FIELD_NODE_ID
            ] = null;
        }

        /*
         * Quan hệ Resource - Node N-N.
         *
         * NODE_RESOURCE lưu quan hệ.
         */
        else {
            $arrHPSchema[
                self::SECTION_NODE_RESOURCE
            ] = [
                HPSubSchema::FIELD_SOURCE      => null,
                HPSubSchema::FIELD_NODE_ID     => null,
                HPSubSchema::FIELD_RESOURCE_ID => null,
            ];
        }

        /*
         * Policy áp lên Resource thông qua Node.
         *
         * RESOURCE_NODE_POLICY luôn tồn tại.
         *
         * Policy attribute on scope cũng luôn tồn tại.
         */
        $arrHPSchema[
            self::SECTION_RESOURCE_NODE_POLICY
        ] = self::createResourceNodePolicySection(
            $strPolicyAttributeOnScope
        );

        /*
         * Policy áp trực tiếp lên Resource.
         */
        if ($hasDirectResourcePolicy) {
            $arrHPSchema[
                self::SECTION_RESOURCE_POLICY
            ] = self::createResourcePolicySection(
                $strPolicyAttributeOnScope
            );
        }

        /*
         * LINK:
         *
         * Source cung cấp các parameter ngoài binding
         * cho Stored Function.
         *
         * 1-N:
         *
         *     resource_id
         *     policy_id
         *     param_1
         *     param_2
         *     ...
         *
         * N-N:
         *
         *     node_id
         *     resource_id
         *     policy_id
         *     param_1
         *     param_2
         *     ...
         *
         * FIELD_POLICY_ATTRIBUTE_ON_SCOPE không do source này
         * cung cấp. Giá trị luôn lấy từ effective binding.
         */
        if (!$isDirect) {
            $arrPolicyFunctionCost = [
                HPSubSchema::FIELD_SOURCE =>
                    $arrPolicyValue[
                        HPSubSchema::FIELD_SOURCE
                    ],

                HPSubSchema::FIELD_RESOURCE_ID =>
                    null,

                HPSubSchema::FIELD_POLICY_ID =>
                    null,
            ];

            if (!$isResourceNodeOneToMany) {
                $arrPolicyFunctionCost[
                    HPSubSchema::FIELD_NODE_ID
                ] = null;
            }

            $arrHPSchema[
                self::SECTION_POLICY_FUNCTION_COST
            ] = $arrPolicyFunctionCost;
        }

        return $arrHPSchema;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(
        array $arrHPSchema,
        array $arrOption
    ): bool {
        if (!self::isOptionValid($arrOption)) {
            return false;
        }

        /*
         * HPResourceSchema là schema hoàn chỉnh.
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

        $arrPolicyValue =
            $arrOption[
                self::OPTION_POLICY_VALUE
            ];

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
                $arrPolicyValue
            )
        ) {
            return false;
        }

        $isResourceNodeOneToMany =
            $arrOption[
                self::OPTION_RESOURCE_NODE_ONE_TO_MANY
            ];

        /*
         * RESOURCE.
         */
        $arrResourceField = [
            HPSubSchema::FIELD_SOURCE,
            HPSubSchema::FIELD_ID,
        ];

        if ($isResourceNodeOneToMany) {
            $arrResourceField[] =
                HPSubSchema::FIELD_NODE_ID;
        }

        if (
            !HPSubSchema::isSectionValid(
                $arrHPSchema[
                    self::SECTION_RESOURCE
                ],
                $arrResourceField
            )
        ) {
            return false;
        }

        /*
         * NODE_RESOURCE.
         */
        if (!$isResourceNodeOneToMany) {
            if (
                !HPSubSchema::isSectionValid(
                    $arrHPSchema[
                        self::SECTION_NODE_RESOURCE
                    ],
                    [
                        HPSubSchema::FIELD_SOURCE,
                        HPSubSchema::FIELD_NODE_ID,
                        HPSubSchema::FIELD_RESOURCE_ID,
                    ]
                )
            ) {
                return false;
            }
        }

        $strPolicyAttributeOnScope =
            $arrPolicyValue[
                HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE
            ];

        /*
         * RESOURCE_NODE_POLICY.
         *
         * Luôn tồn tại.
         */
        if (
            !self::isResourceNodePolicySectionValid(
                $arrHPSchema[
                    self::SECTION_RESOURCE_NODE_POLICY
                ],
                $strPolicyAttributeOnScope
            )
        ) {
            return false;
        }

        /*
         * RESOURCE_POLICY.
         */
        if (
            $arrOption[
                self::OPTION_HAS_DIRECT_RESOURCE_POLICY
            ]
        ) {
            if (
                !self::isResourcePolicySectionValid(
                    $arrHPSchema[
                        self::SECTION_RESOURCE_POLICY
                    ],
                    $strPolicyAttributeOnScope
                )
            ) {
                return false;
            }
        }

        /*
         * POLICY_FUNCTION_COST.
         */
        $isDirect =
            $arrPolicyValue[
                HPSubSchema::FIELD_IS_DIRECT
            ];

        if (!$isDirect) {
            $arrField = [
                HPSubSchema::FIELD_SOURCE,
                HPSubSchema::FIELD_RESOURCE_ID,
                HPSubSchema::FIELD_POLICY_ID,
            ];

            if (!$isResourceNodeOneToMany) {
                $arrField[] =
                    HPSubSchema::FIELD_NODE_ID;
            }

            $arrPolicyFunctionCost =
                $arrHPSchema[
                    self::SECTION_POLICY_FUNCTION_COST
                ];

            if (
                !HPSubSchema::isSectionValid(
                    $arrPolicyFunctionCost,
                    $arrField
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
                $arrPolicyValue[
                    HPSubSchema::FIELD_SOURCE
                ]
            ) {
                return false;
            }
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function createResourceNodePolicySection(
        string $strPolicyAttributeOnScope
    ): array {
        return [
            HPSubSchema::FIELD_SOURCE =>
                null,

            HPSubSchema::FIELD_NODE_ID =>
                null,

            HPSubSchema::FIELD_RESOURCE_ID =>
                null,

            HPSubSchema::FIELD_POLICY_ID =>
                null,

            HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE =>
                $strPolicyAttributeOnScope,
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function createResourcePolicySection(
        string $strPolicyAttributeOnScope
    ): array {
        return [
            HPSubSchema::FIELD_SOURCE =>
                null,

            HPSubSchema::FIELD_RESOURCE_ID =>
                null,

            HPSubSchema::FIELD_POLICY_ID =>
                null,

            HPSubSchema::FIELD_POLICY_ATTRIBUTE_ON_SCOPE =>
                $strPolicyAttributeOnScope,
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isResourceNodePolicySectionValid(
        mixed $mixSection,
        string $strPolicyAttributeOnScope
    ): bool {
        if (
            !HPSubSchema::isSectionValid(
                $mixSection,
                [
                    HPSubSchema::FIELD_SOURCE,
                    HPSubSchema::FIELD_NODE_ID,
                    HPSubSchema::FIELD_RESOURCE_ID,
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
    protected static function isResourcePolicySectionValid(
        mixed $mixSection,
        string $strPolicyAttributeOnScope
    ): bool {
        if (
            !HPSubSchema::isSectionValid(
                $mixSection,
                [
                    HPSubSchema::FIELD_SOURCE,
                    HPSubSchema::FIELD_RESOURCE_ID,
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

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isOptionValid(
        array $arrOption
    ): bool {
        /*
         * HPResourceSchema có chính xác 3 option.
         */
        if (
            !ValidUtility::hasExactFields(
                $arrOption,
                [
                    self::OPTION_RESOURCE_NODE_ONE_TO_MANY,
                    self::OPTION_HAS_DIRECT_RESOURCE_POLICY,
                    self::OPTION_POLICY_VALUE,
                ]
            )
        ) {
            return false;
        }

        /*
         * Resource-specific options.
         */
        if (
            !is_bool(
                $arrOption[
                    self::OPTION_RESOURCE_NODE_ONE_TO_MANY
                ]
            )
            || !is_bool(
                $arrOption[
                    self::OPTION_HAS_DIRECT_RESOURCE_POLICY
                ]
            )
        ) {
            return false;
        }

        /*
         * OPTION_POLICY_VALUE.
         */
        $arrPolicyValue =
            $arrOption[
                self::OPTION_POLICY_VALUE
            ];

        if (!is_array($arrPolicyValue)) {
            return false;
        }

        return HPSubSchema::isOptionValid(
            $arrPolicyValue
        );
    }
}

