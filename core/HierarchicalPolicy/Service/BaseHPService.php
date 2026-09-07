<?php

namespace Core\HierarchicalPolicy\Service;

use InvalidArgumentException;
use Core\Database\DbService;
use Core\HierarchicalPolicy\Schema\HPSubSchema;
/*
 * Sử dụng abstract class BaseHPService để không cho tạo
 * instance trực tiếp từ BaseHPService mà phải thông qua
 * HPResourceService hoặc HPNodeService.
 */
abstract class BaseHPService
{
    protected DbService $dbService;

    /*
     * Complete schema của service cụ thể:
     *
     *     HPResourceService
     *         => HPResourceSchema
     *
     *     HPNodeService
     *         => HPNodeSchema
     *
     * HPService không tự validate complete schema,
     * vì complete schema của hai service là khác nhau.
     *
     * Service con phải validate schema trước khi gọi
     * parent::__construct().
     */
    protected array $arrHPSchema;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        DbService $dbService,
        array $arrHPSchema
    ) {
        $this->dbService = $dbService;
        $this->arrHPSchema = $arrHPSchema;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Tạo tên source đầy đủ theo DB_NAME chung của HPSubSchema.
     *
     * Ví dụ:
     *
     *     category
     *
     * =>
     *
     *     `category`
     *
     * hoặc:
     *
     *     document_db.category
     *
     * =>
     *
     *     `document_db`.`category`
     *
     * HPResourceSchema và HPNodeSchema đều chứa phần common
     * được tạo/validate bởi HPSubSchema nên DB_NAME luôn có
     * cùng semantic.
     */
    protected function fullSource(string $strSource): string
    {
        $strDbName =
            $this->arrHPSchema[
                HPSubSchema::DB_NAME
            ];

        return $strDbName === null
            ? "`{$strSource}`"
            : "`{$strDbName}`.`{$strSource}`";
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Lấy danh sách direct child Node của một Node.
     *
     * Đây là operation chung vì cả HPResourceSchema và
     * HPNodeSchema đều sử dụng cùng SECTION_NODE do
     * HPSubSchema định nghĩa.
     *
     * $iBaseNodeId === null:
     *
     *     lấy root node:
     *
     *         parent_id IS NULL
     *
     * $iBaseNodeId !== null:
     *
     *     lấy direct child:
     *
     *         parent_id = $iBaseNodeId
     *
     * $isShowNum === true:
     *
     *     trả thêm child_count là số direct child
     *     của từng Node.
     *
     * $strShowFields:
     *
     *     các field riêng của Application cần hiển thị.
     *
     * Ví dụ:
     *
     *     'n.name, n.slug'
     *
     * $strOrderByClause:
     *
     * Ví dụ:
     *
     *     'n.sort_order ASC, n.name ASC'
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
                HPSubSchema::SECTION_NODE
            ];

        $strNodeSource =
            $arrNodeSchema[
                HPSubSchema::FIELD_SOURCE
            ];

        $strNodeIdField =
            $arrNodeSchema[
                HPSubSchema::FIELD_ID
            ];

        $strParentField =
            $arrNodeSchema[
                HPSubSchema::FIELD_PARENT
            ];

        /*
         * Complete schema đã được HPResourceSchema::isValid()
         * hoặc HPNodeSchema::isValid() kiểm tra trước khi
         * truyền vào HPService.
         *
         * Vì vậy các identifier trên đã hợp lệ.
         */
        $strFullNodeSource =
            $this->fullSource(
                $strNodeSource
            );

        /*
         * node_id là output field chuẩn của HP.
         */
        $strSelectClause =
            "n.`{$strNodeIdField}` AS node_id";

        $strShowFields = trim(
            $strShowFields
        );

        if ($strShowFields !== '') {
            $strSelectClause .=
                ', ' . $strShowFields;
        }

        /*
         * Đếm direct child của từng Node.
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
             * $iBaseNodeId được PHP type-check là int.
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
                        'type' =>
                            'none',
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
     * Chuẩn hóa input policy_id thành list int dương,
     * không trùng nhau.
     *
     * Đây là logic chung cho cả:
     *
     *     HPResourceService::getResourcePolicies()
     *
     * và:
     *
     *     HPNodeService::getNodePolicies()
     *
     * Ví dụ:
     *
     *     7
     *
     * =>
     *
     *     [7]
     *
     *
     *     [7, 8, 7, 12]
     *
     * =>
     *
     *     [7, 8, 12]
     */
    protected function normalizePolicyId(
        array|int $mixPolicyId
    ): array {
        $arrPolicyId =
            is_int($mixPolicyId)
                ? [$mixPolicyId]
                : array_values(
                    $mixPolicyId
                );

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
}