<?php

namespace Core\HierarchicalPolicy\Service;

use InvalidArgumentException;
use Core\Database\DbService;
use Core\HierarchicalPolicy\Schema\HPNodeSchema;

class HPNodeService extends BaseHPService
{
    protected array $arrOption;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        DbService $dbService,
        array $arrHPSchema,
        array $arrOption
    ) {
        /*
         * BaseHPService không validate schema vì nó không biết
         * concrete schema là HPNodeSchema hay HPResourceSchema.
         *
         * Việc validate thuộc trách nhiệm của concrete service.
         */
        if (
            !HPNodeSchema::isValid(
                $arrHPSchema,
                $arrOption
            )
        ) {
            throw new InvalidArgumentException(
                'arrHPSchema không đúng format của HPNodeSchema.'
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
     * Lấy effective policy áp lên một Node.
     *
     * Input:
     *
     *     $iNodeId
     *         Node cần xét.
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
     * Resolve policy theo ancestor path:
     *
     *     current node
     *         -> parent
     *         -> ...
     *         -> root
     *
     * Với cùng một policy_id:
     *
     *     binding gần Node hiện tại nhất thắng.
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
     * Chỉ những policy thực sự có effective binding
     * mới xuất hiện trong output.
     */
    public function getNodePolicies(
        int $iNodeId,
        array|int $mixPolicyId
    ): array {
        if ($iNodeId <= 0) {
            throw new InvalidArgumentException(
                'node_id phải là số nguyên dương.'
            );
        }

        /*
         * normalizePolicyId() nằm ở BaseHPService.
         */
        $arrPolicyId =
            $this->normalizePolicyId(
                $mixPolicyId
            );

        $strHPSchemaJson =
            json_encode(
                $this->arrHPSchema,
                JSON_THROW_ON_ERROR
            );
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
         * lib_spHPGetNodePolicies chịu trách nhiệm:
         *
         * 1. Xác định ancestor path:
         *
         *        current node
         *        -> parent
         *        -> ...
         *        -> root
         *
         * 2. Resolve NODE_POLICY.
         *
         * 3. Với cùng một policy_id:
         *
         *        nearest/deepest binding wins.
         *
         * 4. Chỉ xét các policy_id nằm trong jsonPolicyId.
         *
         * 5. Tính final policy value theo HPNodeSchema:
         *
         *      DIRECT:
         *          lấy policy attribute từ
         *          FIELD_POLICY_ATTRIBUTE_ON_SCOPE.
         *
         *      LINK:
         *          lấy function prototype từ POLICY,
         *          lấy các parameter từ
         *          POLICY_FUNCTION_COST,
         *          đưa chúng vào SQL context,
         *          thực thi prototype để sinh value.
         *
         * Function prototype là hoàn toàn tường minh.
         * Stored Procedure không tự động append
         * FIELD_POLICY_ATTRIBUTE_ON_SCOPE
         * hay parameter nào khác.
         *
         * Output:
         *
         *      policy_id
         *      value
         */
        
        
        return $this->dbService->fetchAll(
            'lib_spHPGetNodePolicies',
            [
                'jsonHPSchema' =>
                    $strHPSchemaJson,

                'jsonOption' =>
                    $strOptionJson,

                'nodeId' =>
                    $iNodeId,

                'jsonPolicyId' =>
                    $strPolicyIdJson,
            ]
        );
    }
}