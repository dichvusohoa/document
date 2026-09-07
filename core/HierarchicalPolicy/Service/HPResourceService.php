<?php

namespace Core\HierarchicalPolicy\Service;

use Core\Database\DbService;
use InvalidArgumentException;
use Core\HierarchicalPolicy\Schema\HPSubSchema;
use Core\HierarchicalPolicy\Schema\HPResourceSchema;

class HPResourceService extends BaseHPService
{
    protected array $arrOption;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        DbService $dbService,
        array $arrHPSchema,
        array $arrOption
    ) {
        /*
         * Subclass chịu trách nhiệm validate schema hoàn chỉnh.
         *
         * BaseHPService không validate lại để tránh:
         *
         *     - phải biết HPResourceSchema / HPNodeSchema;
         *     - duplicate validation.
         *
         * HPResourceSchema::isValid() đồng thời bảo đảm:
         *
         *     OPTION_POLICY_VALUE luôn có
         *     FIELD_POLICY_ATTRIBUTE_ON_SCOPE.
         *
         * Cả DIRECT và LINK đều bắt buộc có field này.
         */
        if (
            !HPResourceSchema::isValid(
                $arrHPSchema,
                $arrOption
            )
        ) {
            throw new InvalidArgumentException(
                'arrHPSchema/arrOption có format '
                . 'HPResourceSchema không chính xác.'
            );
        }

        parent::__construct(
            $dbService,
            $arrHPSchema
        );

        $this->arrOption = $arrOption;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Xác định quan hệ Resource - Node.
     *
     * true:
     *     1-N
     *
     *     RESOURCE chứa node_id_field.
     *
     * false:
     *     N-N
     *
     *     quan hệ thông qua NODE_RESOURCE.
     *
     * Không cần suy luận lại từ shape của schema vì
     * HPResourceSchema đã có option tường minh.
     */
    protected function isResourceNodeOneToMany(): bool
    {
        return $this->arrOption[
            HPResourceSchema::OPTION_RESOURCE_NODE_ONE_TO_MANY
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Lấy danh sách Resource trực tiếp thuộc/gắn với một Node.
     *
     * Quan hệ 1-N:
     *
     *     RESOURCE.node_id = $iNodeId
     *
     * Quan hệ N-N:
     *
     *     NODE_RESOURCE.node_id = $iNodeId
     *     rồi JOIN sang RESOURCE.
     *
     * Chỉ xét direct membership tại node hiện tại,
     * không lấy Resource thuộc các descendant node.
     *
     * Resource được trả về bất kể có policy hay không.
     *
     * $strShowFields:
     *     Các field riêng của Application cần hiển thị.
     *
     *     Alias chuẩn:
     *
     *         r  = RESOURCE
     *         nr = NODE_RESOURCE   // chỉ có trong N-N
     *
     *     Ví dụ:
     *
     *         'r.name, r.slug'
     *
     * $strOrderByClause:
     *
     *     Ví dụ:
     *
     *         'r.name ASC'
     *
     * $strShowFields và $strOrderByClause phải xuất phát từ
     * code/config tin cậy của Application, không lấy trực tiếp
     * từ request của user.
     */
    public function getResourcesByNode(
        int $iNodeId,
        int $iPageIndex,
        int $iPageSize,
        string $strShowFields,
        ?string $strOrderByClause = null
    ): array {
        $arrResourceSchema =
            $this->arrHPSchema[
                HPResourceSchema::SECTION_RESOURCE
            ];

        $strDbName =
            $this->arrHPSchema[
                HPSubSchema::DB_NAME
            ];

        $strResourceSource =
            $arrResourceSchema[
                HPSubSchema::FIELD_SOURCE
            ];

        $strResourceIdField =
            $arrResourceSchema[
                HPSubSchema::FIELD_ID
            ];

        /*
         * HPResourceSchema::isValid() đã bảo đảm
         * các SQL identifier trong schema hợp lệ.
         */
        $strFullResourceSource =
            $strDbName === null
                ? "`{$strResourceSource}`"
                : "`{$strDbName}`.`{$strResourceSource}`";

        /*
         * resource_id là field chuẩn mà
         * HPResourceService luôn trả.
         */
        $strSelectClause =
            "r.`{$strResourceIdField}` AS resource_id";

        $strShowFields = trim($strShowFields);

        if ($strShowFields !== '') {
            $strSelectClause .=
                ', ' . $strShowFields;
        }

        /*
         * Quan hệ 1-N.
         */
        if ($this->isResourceNodeOneToMany()) {
            $strNodeIdField =
                $arrResourceSchema[
                    HPSubSchema::FIELD_NODE_ID
                ];

            $strSelectClause .=
                " FROM {$strFullResourceSource} r";

            $strWhereCondition =
                "r.`{$strNodeIdField}` = {$iNodeId}";
        }

        /*
         * Quan hệ N-N.
         */
        else {
            $arrNodeResourceSchema =
                $this->arrHPSchema[
                    HPResourceSchema::SECTION_NODE_RESOURCE
                ];

            $strNodeResourceSource =
                $arrNodeResourceSchema[
                    HPSubSchema::FIELD_SOURCE
                ];

            $strNodeResourceNodeIdField =
                $arrNodeResourceSchema[
                    HPSubSchema::FIELD_NODE_ID
                ];

            $strNodeResourceResourceIdField =
                $arrNodeResourceSchema[
                    HPSubSchema::FIELD_RESOURCE_ID
                ];

            $strFullNodeResourceSource =
                $strDbName === null
                    ? "`{$strNodeResourceSource}`"
                    : "`{$strDbName}`.`{$strNodeResourceSource}`";

            $strSelectClause .=
                " FROM {$strFullNodeResourceSource} nr"
                . " JOIN {$strFullResourceSource} r"
                . " ON r.`{$strResourceIdField}`"
                . " = nr.`{$strNodeResourceResourceIdField}`";

            $strWhereCondition =
                "nr.`{$strNodeResourceNodeIdField}` = {$iNodeId}";
        }

        $arrSelectSPParam = [
            'selectClause' =>
                $strSelectClause,

            'jsonWhere' =>
                json_encode(
                    [
                        'type' =>
                            'logic',

                        'condition' =>
                            $strWhereCondition,
                    ],
                    JSON_THROW_ON_ERROR
                ),

            'jsonHaving' =>
                json_encode(
                    [
                        'type' => 'none',
                    ],
                    JSON_THROW_ON_ERROR
                ),

            'groupByClause' =>
                null,

            'orderByClause' =>
                $strOrderByClause,

            'pageIndex' =>
                $iPageIndex,

            'pageSize' =>
                $iPageSize,
        ];

        return $this->dbService->fetchLibPageResult(
            $arrSelectSPParam
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Lấy các effective policy áp lên một Resource.
     *
     * Input:
     *
     *     $iNodeId
     *
     *         Quan hệ 1-N:
     *             có thể null.
     *
     *             Khi null, Stored Procedure tự lấy node_id
     *             trực tiếp từ RESOURCE.
     *
     *             Nếu khác null, Stored Procedure phải kiểm tra
     *             Resource thực sự thuộc Node này.
     *
     *         Quan hệ N-N:
     *             bắt buộc khác null.
     *
     *             Stored Procedure phải kiểm tra cặp
     *
     *                 (node_id, resource_id)
     *
     *             thực sự tồn tại trong NODE_RESOURCE.
     *
     *     $iResourceId
     *         Resource cần xét.
     *
     *     $mixPolicyId
     *         Một policy_id:
     *
     *             7
     *
     *         hoặc nhiều policy_id:
     *
     *             [7, 8, 12]
     *
     * Output:
     *
     *     [
     *         [
     *             'policy_id' => 7,
     *             'value'     => ...
     *         ],
     *         ...
     *     ]
     *
     * Chỉ những policy thực sự có effective binding mới
     * xuất hiện trong output.
     */
    public function getResourcePolicies(
        ?int $iNodeId,
        int $iResourceId,
        array|int $mixPolicyId
    ): array {
        if ($iResourceId <= 0) {
            throw new InvalidArgumentException(
                'resource_id phải là số nguyên dương.'
            );
        }

        if (
            $iNodeId !== null
            && $iNodeId <= 0
        ) {
            throw new InvalidArgumentException(
                'node_id phải là null hoặc số nguyên dương.'
            );
        }

        /*
         * Với quan hệ N-N, Node là một phần của context
         * vì cùng một Resource có thể gắn với nhiều Node.
         */
        if (
            !$this->isResourceNodeOneToMany()
            && $iNodeId === null
        ) {
            throw new InvalidArgumentException(
                'node_id là bắt buộc khi quan hệ '
                . 'Resource - Node là N-N.'
            );
        }

        $arrPolicyId =
            $this->normalizePolicyId(
                $mixPolicyId
            );

        $strHPSchemaJson =
            json_encode(
                $this->arrHPSchema,
                JSON_THROW_ON_ERROR
            );

        /*
         * arrOption được tách khỏi arrHPSchema
         * nên Stored Procedure nhận riêng.
         *
         * Theo contract hiện tại:
         *
         * OPTION_POLICY_VALUE luôn chứa
         * FIELD_POLICY_ATTRIBUTE_ON_SCOPE.
         */
        $strOptionJson =
            json_encode(
                $this->arrOption,
                JSON_THROW_ON_ERROR
            );

        $strPolicyIdJson =
            json_encode(
                $arrPolicyId,
                JSON_THROW_ON_ERROR
            );

        /*
         * lib_spHPGetResourcePolicies chịu trách nhiệm:
         *
         * 1. Xác định Node context.
         *
         * 2. Kiểm tra Resource - Node relation.
         *
         * 3. Resolve RESOURCE_POLICY nếu:
         *
         *        OPTION_HAS_DIRECT_RESOURCE_POLICY = true.
         *
         * 4. Resolve RESOURCE_NODE_POLICY theo ancestor path,
         *    nearest/deepest binding wins.
         *
         * 5. Chỉ xét các policy_id trong jsonPolicyId.
         *
         * 6. Tính final policy value theo OPTION_POLICY_VALUE.
         *
         *      DIRECT:
         *
         *          effective
         *          FIELD_POLICY_ATTRIBUTE_ON_SCOPE
         *
         *          chính là final policy value.
         *
         *      LINK:
         *
         *          effective
         *          FIELD_POLICY_ATTRIBUTE_ON_SCOPE
         *
         *          là policy attribute do scope/hierarchy
         *          đóng góp vào phép tính.
         *
         *          Stored Procedure kết hợp attribute này
         *          với các parameter lấy từ
         *          POLICY_FUNCTION_COST source,
         *          rồi thực thi function prototype.
         *
         * Output:
         *
         *      policy_id
         *      value
         */
        return $this->dbService->fetchAll(
            'lib_spHPGetResourcePolicies',
            [
                'jsonHPSchema' =>
                    $strHPSchemaJson,

                'jsonOption' =>
                    $strOptionJson,

                'nodeId' =>
                    $iNodeId,

                'resourceId' =>
                    $iResourceId,

                'jsonPolicyId' =>
                    $strPolicyIdJson,
            ]
        );
    }
}