<?php
namespace Core\HierarchicalPolicy;
use Core\Database\DbService;
class HPService {
    protected DbService $dbService;
    protected array $arrHPSchema;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        DbService $dbService,
        array $arrHPSchema
    ) {
        if (!HPSchema::isValid($arrHPSchema)) {
            throw new InvalidArgumentException(
                'arrHPSchema có format không chính xác.'
            );
        }

        $this->dbService = $dbService;
        $this->arrHPSchema = $arrHPSchema;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Lấy danh sách direct child node của một node.
     *
     * $iBaseNodeId === null:
     *     lấy các root node:
     *
     *         parent_id IS NULL
     *
     * $iBaseNodeId !== null:
     *     lấy direct children:
     *
     *         parent_id = $iBaseNodeId
     *
     * $isShowNum === true:
     *     trả thêm child_count là số direct child
     *     của từng node.
     *
     * $strShowFields:
     *     các field riêng của Application cần hiển thị.
     *
     *     Ví dụ:
     *
     *         'n.name, n.slug'
     *
     * $strOrderByClause:
     *     phần sau ORDER BY.
     *
     *     Ví dụ:
     *
     *         'n.sort_order ASC, n.name ASC'
     *
     * $strShowFields và $strOrderByClause phải được cung cấp
     * từ code/config tin cậy của Application, không lấy trực tiếp
     * từ request của user.
     */
    public function getChildrenNode(
        ?int $iBaseNodeId,
        int $iPageIndex,
        int $iPageSize,
        bool $isShowNum,
        string $strShowFields,
        ?string $strOrderByClause = null
    ): array {
        $arrNodeSchema =
            $this->arrHPSchema[
                HPSchema::SECTION_NODE
            ];

        $strDbName =
            $this->arrHPSchema[
                HPSchema::DB_NAME
            ];

        $strNodeSource =
            $arrNodeSchema[
                HPSchema::FIELD_SOURCE
            ];

        $strNodeIdField =
            $arrNodeSchema[
                HPSchema::FIELD_ID
            ];

        $strParentField =
            $arrNodeSchema[
                HPSchema::FIELD_PARENT
            ];

        /*
         * HPSchema::isValid() đã bảo đảm:
         *
         *     db_name
         *     source
         *     id_field
         *     parent_field
         *
         * đều là SQL identifier hợp lệ.
         */
        $strFullNodeSource =
            $strDbName === null
                ? "`{$strNodeSource}`"
                : "`{$strDbName}`.`{$strNodeSource}`";

        /*
         * node_id là field chuẩn mà HPService luôn trả.
         */
        $strSelectClause =
            "n.`{$strNodeIdField}` AS node_id";

        /*
         * Các field riêng của Application.
         */
        $strShowFields = trim($strShowFields);

        if ($strShowFields !== '') {
            $strSelectClause .=
                ', ' . $strShowFields;
        }

        /*
         * Đếm direct child của từng node.
         */
        if ($isShowNum) {
            $strSelectClause .=
                ", (
                    SELECT COUNT(*)
                    FROM {$strFullNodeSource} c
                    WHERE c.`{$strParentField}`
                        = n.`{$strNodeIdField}`
                ) AS child_count";
        }

        /*
         * lib_spSelect nhận selectClause bao gồm cả FROM.
         */
        $strSelectClause .=
            " FROM {$strFullNodeSource} n";

        /*
         * Chỉ lấy direct child.
         */
        if ($iBaseNodeId === null) {
            $strWhereCondition =
                "n.`{$strParentField}` IS NULL";
        } else {
            /*
             * $iBaseNodeId đã được PHP type-check là int,
             * nên có thể đưa trực tiếp vào SQL fragment này.
             */
            $strWhereCondition =
                "n.`{$strParentField}` = {$iBaseNodeId}";
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
     * Xác định quan hệ Resource - Node từ shape của HPSchema.
     *
     * 1-N:
     *     RESOURCE có FIELD_NODE_ID.
     *
     * N-N:
     *     RESOURCE không có FIELD_NODE_ID và
     *     HPSchema có SECTION_NODE_RESOURCE.
     */
    protected function isResourceNodeOneToMany(): bool
    {
        return array_key_exists(
            HPSchema::FIELD_NODE_ID,
            $this->arrHPSchema[
                HPSchema::SECTION_RESOURCE
            ]
        );
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
                HPSchema::SECTION_RESOURCE
            ];

        $strDbName =
            $this->arrHPSchema[
                HPSchema::DB_NAME
            ];

        $strResourceSource =
            $arrResourceSchema[
                HPSchema::FIELD_SOURCE
            ];

        $strResourceIdField =
            $arrResourceSchema[
                HPSchema::FIELD_ID
            ];

        /*
         * Quan hệ 1-N được nhận biết bởi việc RESOURCE
         * có FIELD_NODE_ID.
         *
         * Nếu không có FIELD_NODE_ID thì HPSchema hợp lệ
         * bắt buộc phải có SECTION_NODE_RESOURCE.
         */
        $isResourceNodeOneToMany =
            array_key_exists(
                HPSchema::FIELD_NODE_ID,
                $arrResourceSchema
            );

        /*
         * HPSchema::isValid() đã bảo đảm các identifier
         * trong schema là hợp lệ.
         */
        $strFullResourceSource =
            $strDbName === null
                ? "`{$strResourceSource}`"
                : "`{$strDbName}`.`{$strResourceSource}`";

        /*
         * resource_id là field chuẩn HPService luôn trả.
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
        if ($isResourceNodeOneToMany) {
            $strNodeIdField =
                $arrResourceSchema[
                    HPSchema::FIELD_NODE_ID
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
                    HPSchema::SECTION_NODE_RESOURCE
                ];

            $strNodeResourceSource =
                $arrNodeResourceSchema[
                    HPSchema::FIELD_SOURCE
                ];

            $strNodeResourceNodeIdField =
                $arrNodeResourceSchema[
                    HPSchema::FIELD_NODE_ID
                ];

            $strNodeResourceResourceIdField =
                $arrNodeResourceSchema[
                    HPSchema::FIELD_RESOURCE_ID
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
     * Chuẩn hóa input policy_id thành một list int dương,
     * không trùng nhau.
     *
     * Ví dụ:
     *
     *     7
     *         =>
     *     [7]
     *
     *     [7, 8, 7, 12]
     *         =>
     *     [7, 8, 12]
     */
    protected function normalizePolicyId(
        array|int $mixPolicyId
    ): array {
        $arrPolicyId =
            is_int($mixPolicyId)
                ? [$mixPolicyId]
                : array_values($mixPolicyId);

        if ($arrPolicyId === []) {
            throw new InvalidArgumentException(
                'Danh sách policy_id không được rỗng.'
            );
        }

        foreach (
            $arrPolicyId
            as $iIndex => $mixEachPolicyId
        ) {
            if (
                !is_int($mixEachPolicyId)
                || $mixEachPolicyId <= 0
            ) {
                throw new InvalidArgumentException(
                    "policy_id tại vị trí {$iIndex} "
                    . 'phải là số nguyên dương.'
                );
            }
        }

        return array_values(
            array_unique(
                $arrPolicyId
            )
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
     *             Khi null, Stored Procedure tự lấy node_id
     *             trực tiếp từ RESOURCE.
     *
     *             Nếu khác null, Stored Procedure phải kiểm tra
     *             Resource thực sự thuộc Node này.
     *
     *         Quan hệ N-N:
     *             bắt buộc khác null.
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
     * Output data:
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
         * 3. Resolve RESOURCE_POLICY nếu có.
         *
         * 4. Resolve RESOURCE_NODE_POLICY theo ancestor path,
         *    nearest/deepest binding wins.
         *
         * 5. Chỉ xét các policy_id trong jsonPolicyId.
         *
         * 6. Tính final value:
         *
         *      DIRECT:
         *          lấy FIELD_ON_BINDING.
         *
         *      LINK:
         *          lấy function prototype từ POLICY,
         *          JOIN POLICY_FUNCTION_COST source,
         *          đưa các params vào SQL context,
         *          chạy prototype và sinh value.
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

