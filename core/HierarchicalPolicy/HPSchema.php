<?php

namespace Core\HierarchicalPolicy;

use Core\Utility\ValidUtility;
use InvalidArgumentException;

final class HPSchema
{
    /*
     * Database chứa toàn bộ các source thuộc HPSchema.
     *
     * null:
     *     sử dụng current database.
     */
    public const DB_NAME = 'db_name';

    /*
     * Sections.
     */
    public const SECTION_NODE =
        'node';

    public const SECTION_RESOURCE =
        'resource';

    public const SECTION_NODE_RESOURCE =
        'node_resource';

    public const SECTION_POLICY =
        'policy';

    public const SECTION_NODE_POLICY =
        'node_policy';

    public const SECTION_RESOURCE_NODE_POLICY =
        'resource_node_policy';

    public const SECTION_RESOURCE_POLICY =
        'resource_policy';

    public const SECTION_POLICY_FUNCTION_COST =
        'policy_function_cost';

    /*
     * Fields.
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
     * Fields dùng trong OPTION_POLICY_VALUE.
     */
    public const FIELD_IS_DIRECT =
        'is_direct';

    /*
     * Tên field nằm trên policy binding.
     *
     * DIRECT:
     *     field này chứa final policy value.
     *
     * LINK:
     *     nếu có, field này được đưa vào SQL context
     *     để function prototype có thể sử dụng.
     *
     * Ví dụ:
     *
     *     permission_mask
     */
    public const FIELD_ON_BINDING =
        'field_on_binding';

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

    /*
     * Options.
     */
    public const OPTION_RESOURCE_NODE_ONE_TO_MANY =
        'resource_node_one_to_many';

    public const OPTION_HAS_NODE_POLICY =
        'has_node_policy';

    public const OPTION_HAS_DIRECT_RESOURCE_POLICY =
        'has_direct_resource_policy';

    public const OPTION_POLICY_VALUE =
        'policy_value';

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Tạo skeleton HPSchema theo variant được mô tả
     * bởi arrOption.
     */
    public static function createEmpty(
        array $arrOption
    ): array {
        if (!self::isOptionValid($arrOption)) {
            throw new InvalidArgumentException(
                'arrOption của HPSchema không đúng format.'
            );
        }

        $isResourceNodeOneToMany =
            $arrOption[
                self::OPTION_RESOURCE_NODE_ONE_TO_MANY
            ];

        $hasNodePolicy =
            $arrOption[
                self::OPTION_HAS_NODE_POLICY
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
                self::FIELD_IS_DIRECT
            ];

        $strFieldOnBinding =
            $arrPolicyValue[
                self::FIELD_ON_BINDING
            ] ?? null;

        /*
         * Các section luôn tồn tại.
         */
        $arrHPSchema = [
            self::DB_NAME => null,

            self::SECTION_NODE => [
                self::FIELD_SOURCE => null,
                self::FIELD_ID     => null,
                self::FIELD_PARENT => null,
            ],

            self::SECTION_RESOURCE => [
                self::FIELD_SOURCE => null,
                self::FIELD_ID     => null,
            ],

            self::SECTION_POLICY => [
                self::FIELD_SOURCE => null,
                self::FIELD_ID     => null,
            ],
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
                self::FIELD_NODE_ID
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
                self::FIELD_SOURCE      => null,
                self::FIELD_NODE_ID     => null,
                self::FIELD_RESOURCE_ID => null,
            ];
        }

        /*
         * LINK:
         *
         * POLICY phải có column chứa function prototype.
         */
        if (!$isDirect) {
            $arrHPSchema[
                self::SECTION_POLICY
            ][
                self::FIELD_FUNCTION_PROTOTYPE_FIELD
            ] =
                $arrPolicyValue[
                    self::FIELD_FUNCTION_PROTOTYPE_FIELD
                ];
        }

        /*
         * Policy áp lên chính Node.
         */
        if ($hasNodePolicy) {
            $arrHPSchema[
                self::SECTION_NODE_POLICY
            ] = self::createNodePolicySection(
                $strFieldOnBinding
            );
        }

        /*
         * Policy áp lên Resource thông qua Node
         * luôn tồn tại.
         */
        $arrHPSchema[
            self::SECTION_RESOURCE_NODE_POLICY
        ] = self::createNodePolicySection(
            $strFieldOnBinding
        );

        /*
         * Policy áp trực tiếp lên Resource.
         */
        if ($hasDirectResourcePolicy) {
            $arrHPSchema[
                self::SECTION_RESOURCE_POLICY
            ] = self::createResourcePolicySection(
                $strFieldOnBinding
            );
        }

        /*
         * LINK:
         *
         * Source cung cấp các parameter khác cho
         * Stored Function.
         *
         * Contract source:
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
         * Stored Procedure phân tích metadata của source.
         * Các column không phải key column là các parameter
         * có thể được function prototype sử dụng.
         */
        if (!$isDirect) {
            $arrPolicyFunctionCost = [
                self::FIELD_SOURCE =>
                    $arrPolicyValue[
                        self::FIELD_SOURCE
                    ],

                self::FIELD_RESOURCE_ID =>
                    null,

                self::FIELD_POLICY_ID =>
                    null,
            ];

            if (!$isResourceNodeOneToMany) {
                $arrPolicyFunctionCost[
                    self::FIELD_NODE_ID
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
         * createEmpty() xác định chính xác các section
         * được phép tồn tại với variant này.
         */
        $arrExpected =
            self::createEmpty($arrOption);

        if (
            !ValidUtility::hasExactFields(
                $arrHPSchema,
                array_keys($arrExpected)
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

        $isResourceNodeOneToMany =
            $arrOption[
                self::OPTION_RESOURCE_NODE_ONE_TO_MANY
            ];

        /*
         * RESOURCE.
         */
        $arrResourceField = [
            self::FIELD_SOURCE,
            self::FIELD_ID,
        ];

        if ($isResourceNodeOneToMany) {
            $arrResourceField[] =
                self::FIELD_NODE_ID;
        }

        if (
            !self::isSectionValid(
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
                !self::isSectionValid(
                    $arrHPSchema[
                        self::SECTION_NODE_RESOURCE
                    ],
                    [
                        self::FIELD_SOURCE,
                        self::FIELD_NODE_ID,
                        self::FIELD_RESOURCE_ID,
                    ]
                )
            ) {
                return false;
            }
        }

        /*
         * POLICY.
         */
        $arrPolicyValue =
            $arrOption[
                self::OPTION_POLICY_VALUE
            ];

        $isDirect =
            $arrPolicyValue[
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
         * function_prototype_field đã được option xác định,
         * schema không được thay sang column khác.
         */
        if (
            !$isDirect
            && $arrHPSchema[
                self::SECTION_POLICY
            ][
                self::FIELD_FUNCTION_PROTOTYPE_FIELD
            ]
            !==
            $arrPolicyValue[
                self::FIELD_FUNCTION_PROTOTYPE_FIELD
            ]
        ) {
            return false;
        }

        $strFieldOnBinding =
            $arrPolicyValue[
                self::FIELD_ON_BINDING
            ] ?? null;

        /*
         * NODE_POLICY.
         */
        if (
            $arrOption[
                self::OPTION_HAS_NODE_POLICY
            ]
        ) {
            if (
                !self::isNodePolicySectionValid(
                    $arrHPSchema[
                        self::SECTION_NODE_POLICY
                    ],
                    $strFieldOnBinding
                )
            ) {
                return false;
            }
        }

        /*
         * RESOURCE_NODE_POLICY.
         */
        if (
            !self::isNodePolicySectionValid(
                $arrHPSchema[
                    self::SECTION_RESOURCE_NODE_POLICY
                ],
                $strFieldOnBinding
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
                    $strFieldOnBinding
                )
            ) {
                return false;
            }
        }

        /*
         * POLICY_FUNCTION_COST.
         */
        if (!$isDirect) {
            $arrField = [
                self::FIELD_SOURCE,
                self::FIELD_RESOURCE_ID,
                self::FIELD_POLICY_ID,
            ];

            if (!$isResourceNodeOneToMany) {
                $arrField[] =
                    self::FIELD_NODE_ID;
            }

            $arrPolicyFunctionCost =
                $arrHPSchema[
                    self::SECTION_POLICY_FUNCTION_COST
                ];

            if (
                !self::isSectionValid(
                    $arrPolicyFunctionCost,
                    $arrField
                )
            ) {
                return false;
            }

            /*
             * Source đã được option xác định.
             */
            if (
                $arrPolicyFunctionCost[
                    self::FIELD_SOURCE
                ]
                !==
                $arrPolicyValue[
                    self::FIELD_SOURCE
                ]
            ) {
                return false;
            }
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function createNodePolicySection(
        ?string $strFieldOnBinding
    ): array {
        $arrSection = [
            self::FIELD_SOURCE    => null,
            self::FIELD_NODE_ID   => null,
            self::FIELD_POLICY_ID => null,
        ];

        if ($strFieldOnBinding !== null) {
            $arrSection[
                self::FIELD_ON_BINDING
            ] = $strFieldOnBinding;
        }

        return $arrSection;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function createResourcePolicySection(
        ?string $strFieldOnBinding
    ): array {
        $arrSection = [
            self::FIELD_SOURCE      => null,
            self::FIELD_RESOURCE_ID => null,
            self::FIELD_POLICY_ID   => null,
        ];

        if ($strFieldOnBinding !== null) {
            $arrSection[
                self::FIELD_ON_BINDING
            ] = $strFieldOnBinding;
        }

        return $arrSection;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isNodePolicySectionValid(
        mixed $mixSection,
        ?string $strFieldOnBinding
    ): bool {
        $arrField = [
            self::FIELD_SOURCE,
            self::FIELD_NODE_ID,
            self::FIELD_POLICY_ID,
        ];

        if ($strFieldOnBinding !== null) {
            $arrField[] =
                self::FIELD_ON_BINDING;
        }

        if (
            !self::isSectionValid(
                $mixSection,
                $arrField
            )
        ) {
            return false;
        }

        return self::isFieldOnBindingValid(
            $mixSection,
            $strFieldOnBinding
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isResourcePolicySectionValid(
        mixed $mixSection,
        ?string $strFieldOnBinding
    ): bool {
        $arrField = [
            self::FIELD_SOURCE,
            self::FIELD_RESOURCE_ID,
            self::FIELD_POLICY_ID,
        ];

        if ($strFieldOnBinding !== null) {
            $arrField[] =
                self::FIELD_ON_BINDING;
        }

        if (
            !self::isSectionValid(
                $mixSection,
                $arrField
            )
        ) {
            return false;
        }

        return self::isFieldOnBindingValid(
            $mixSection,
            $strFieldOnBinding
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isFieldOnBindingValid(
        array $arrSection,
        ?string $strFieldOnBinding
    ): bool {
        if ($strFieldOnBinding === null) {
            return true;
        }

        return
            $arrSection[
                self::FIELD_ON_BINDING
            ] === $strFieldOnBinding;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isSectionValid(
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
    protected static function isOptionValid(
        array $arrOption
    ): bool {
        /*
         * Top-level options.
         */
        if (
            !ValidUtility::hasExactFields(
                $arrOption,
                [
                    self::OPTION_RESOURCE_NODE_ONE_TO_MANY,
                    self::OPTION_HAS_NODE_POLICY,
                    self::OPTION_HAS_DIRECT_RESOURCE_POLICY,
                    self::OPTION_POLICY_VALUE,
                ]
            )
        ) {
            return false;
        }

        /*
         * Boolean capabilities.
         */
        if (
            !is_bool(
                $arrOption[
                    self::OPTION_RESOURCE_NODE_ONE_TO_MANY
                ]
            )
            || !is_bool(
                $arrOption[
                    self::OPTION_HAS_NODE_POLICY
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

        if (
            !is_array($arrPolicyValue)
            || !array_key_exists(
                self::FIELD_IS_DIRECT,
                $arrPolicyValue
            )
            || !is_bool(
                $arrPolicyValue[
                    self::FIELD_IS_DIRECT
                ]
            )
        ) {
            return false;
        }

        $isDirect =
            $arrPolicyValue[
                self::FIELD_IS_DIRECT
            ];

        /*
         * DIRECT:
         *
         * [
         *     'is_direct'        => true,
         *     'field_on_binding' => 'permission_mask'
         * ]
         */
        if ($isDirect) {
            if (
                !ValidUtility::hasExactFields(
                    $arrPolicyValue,
                    [
                        self::FIELD_IS_DIRECT,
                        self::FIELD_ON_BINDING,
                    ]
                )
            ) {
                return false;
            }

            if (
                !self::isIdentifier(
                    $arrPolicyValue[
                        self::FIELD_ON_BINDING
                    ]
                )
            ) {
                return false;
            }

            return true;
        }

        /*
         * LINK:
         *
         * Không dùng field trên binding:
         *
         * [
         *     'is_direct' => false,
         *
         *     'source' =>
         *         'view_funct_param',
         *
         *     'function_prototype_field' =>
         *         'funct_prototype'
         * ]
         *
         * Có dùng field trên binding:
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
         *     'field_on_binding' =>
         *         'permission_mask'
         * ]
         */
        $hasFieldOnBinding =
            array_key_exists(
                self::FIELD_ON_BINDING,
                $arrPolicyValue
            );

        $arrExpectedField = [
            self::FIELD_IS_DIRECT,
            self::FIELD_SOURCE,
            self::FIELD_FUNCTION_PROTOTYPE_FIELD,
        ];

        if ($hasFieldOnBinding) {
            $arrExpectedField[] =
                self::FIELD_ON_BINDING;
        }

        if (
            !ValidUtility::hasExactFields(
                $arrPolicyValue,
                $arrExpectedField
            )
        ) {
            return false;
        }

        if (
            !self::isIdentifier(
                $arrPolicyValue[
                    self::FIELD_SOURCE
                ]
            )
            || !self::isIdentifier(
                $arrPolicyValue[
                    self::FIELD_FUNCTION_PROTOTYPE_FIELD
                ]
            )
        ) {
            return false;
        }

        if (
            $hasFieldOnBinding
            && !self::isIdentifier(
                $arrPolicyValue[
                    self::FIELD_ON_BINDING
                ]
            )
        ) {
            return false;
        }

        /*
         * Thiết kế POLICY_FUNCTION_COST hiện tại là
         * resource-oriented:
         *
         *     ?node_id
         *     resource_id
         *     policy_id
         *     params...
         *
         * Do đó LINK hiện chưa hỗ trợ tính computed value
         * cho chính NODE_POLICY.
         */
        if (
            $arrOption[
                self::OPTION_HAS_NODE_POLICY
            ]
        ) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Identifier được sử dụng để dựng dynamic SQL.
     */
    protected static function isIdentifier(
        mixed $mixValue
    ): bool {
        return
            ValidUtility::isNonEmptyString($mixValue)
            && preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $mixValue
            ) === 1;
    }
}