DROP PROCEDURE IF EXISTS lib_spHPGetNodePolicies;

DELIMITER ;;

CREATE PROCEDURE lib_spHPGetNodePolicies(
    IN jsonHPSchema JSON,
    IN jsonOption   JSON,
    IN nodeId       BIGINT,
    IN jsonPolicyId JSON
)
MODIFIES SQL DATA
proc: BEGIN
    /*--------------------------------------------------------------------------------------------------------------
     * OPTION_POLICY_VALUE.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE isDirect BOOLEAN DEFAULT FALSE;

    DECLARE policyAttributeOnScopeField VARCHAR(64);

    /*
     * LINK-only option.
     */
    DECLARE optionPolicyValueSource       VARCHAR(64);
    DECLARE optionFunctionPrototypeField  VARCHAR(64);

    /*--------------------------------------------------------------------------------------------------------------
     * Common schema.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE dbName VARCHAR(64);

    /*
     * NODE.
     */
    DECLARE nodeSource      VARCHAR(64);
    DECLARE nodeIdField     VARCHAR(64);
    DECLARE nodeParentField VARCHAR(64);

    /*
     * NODE_POLICY.
     */
    DECLARE nodePolicySource VARCHAR(64);

    DECLARE npNodeIdField VARCHAR(64);
    DECLARE npPolicyIdField VARCHAR(64);

    DECLARE npPolicyAttributeOnScopeField
        VARCHAR(64);

    /*
     * POLICY.
     */
    DECLARE policySource VARCHAR(64);
    DECLARE policyIdField VARCHAR(64);

    DECLARE functionPrototypeField
        VARCHAR(64);

    /*
     * POLICY_FUNCTION_COST.
     *
     * Chỉ dùng với LINK.
     *
     * Contract của HPNodeSchema:
     *
     *     source
     *     node_id_field
     *     policy_id_field
     *
     * Các column còn lại là parameter mà
     * function_prototype có thể sử dụng.
     */
    DECLARE policyFunctionCostSource
        VARCHAR(64);

    DECLARE pfcNodeIdField
        VARCHAR(64);

    DECLARE pfcPolicyIdField
        VARCHAR(64);

    /*--------------------------------------------------------------------------------------------------------------
     * Full source names.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE fullNodePolicySource
        VARCHAR(255);

    DECLARE fullPolicySource
        VARCHAR(255);

    DECLARE fullPFCSource
        VARCHAR(255);

    /*--------------------------------------------------------------------------------------------------------------
     * Quoted identifiers.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE qNPNodeIdField
        VARCHAR(70);

    DECLARE qNPPolicyIdField
        VARCHAR(70);

    DECLARE qNPPolicyAttributeOnScopeField
        VARCHAR(70);

    DECLARE qPolicyIdField
        VARCHAR(70);

    DECLARE qFunctionPrototypeField
        VARCHAR(70);

    DECLARE qPFCNodeIdField
        VARCHAR(70);

    DECLARE qPFCPolicyIdField
        VARCHAR(70);

    DECLARE qPolicyAttributeOnScopeField
        VARCHAR(70);

    /*--------------------------------------------------------------------------------------------------------------
     * Counters.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE ancestorCount INT DEFAULT 0;

    DECLARE nEffectivePolicy INT DEFAULT 0;
    DECLARE nPolicyEval      INT DEFAULT 0;

    /*--------------------------------------------------------------------------------------------------------------
     * LINK evaluation loop.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE i INT DEFAULT 0;

    DECLARE currentPolicyId BIGINT;

    DECLARE currentBindingNodeId BIGINT;

    DECLARE currentFunctionPrototype
        LONGTEXT;

    DECLARE currentPolicyAttributeOnScope
        LONGTEXT;

    /*--------------------------------------------------------------------------------------------------------------
     * Dynamic SQL.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE finalSQL LONGTEXT;

    DECLARE errorMsg VARCHAR(255);

    /*--------------------------------------------------------------------------------------------------------------
     * Cleanup khi có exception.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_policy_input;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_ancestor;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_node_policy_candidate;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_effective_node_policy;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_node_policy_eval;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_node_policy_result;

        SET @lib_hp_value_context_count = NULL;

        SET @lib_hp_policy_attribute_on_scope =
            NULL;

        RESIGNAL;
    END;

    /*==============================================================================================================
     * BƯỚC 1
     *
     * Basic validation + đọc option.
     *=============================================================================================================*/

    IF
        nodeId IS NULL
        OR nodeId <= 0
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'nodeId phải là số nguyên dương';
    END IF;

    IF
        jsonHPSchema IS NULL
        OR JSON_TYPE(jsonHPSchema) <> 'OBJECT'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'jsonHPSchema phải là JSON object';
    END IF;

    IF
        jsonOption IS NULL
        OR JSON_TYPE(jsonOption) <> 'OBJECT'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'jsonOption phải là JSON object';
    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * policy_value.
     *-------------------------------------------------------------------------------------------------------------*/
    IF
        JSON_CONTAINS_PATH(
            jsonOption,
            'one',
            '$.policy_value'
        ) = 0
        OR JSON_TYPE(
            JSON_EXTRACT(
                jsonOption,
                '$.policy_value'
            )
        ) <> 'OBJECT'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'policy_value phải là JSON object';
    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * policy_value.is_direct.
     *-------------------------------------------------------------------------------------------------------------*/
    IF
        JSON_CONTAINS_PATH(
            jsonOption,
            'one',
            '$.policy_value.is_direct'
        ) = 0
        OR JSON_TYPE(
            JSON_EXTRACT(
                jsonOption,
                '$.policy_value.is_direct'
            )
        ) <> 'BOOLEAN'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'policy_value.is_direct phải là boolean';
    END IF;

    SET isDirect =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonOption,
                '$.policy_value.is_direct'
            )
        ) = 'true';

    /*--------------------------------------------------------------------------------------------------------------
     * policy_value.field_policy_attribute_on_scope.
     *
     * THIẾT KẾ MỚI:
     *
     *     luôn bắt buộc.
     *
     * Không còn case field này không tồn tại.
     *-------------------------------------------------------------------------------------------------------------*/
    IF
        JSON_CONTAINS_PATH(
            jsonOption,
            'one',
            '$.policy_value.field_policy_attribute_on_scope'
        ) = 0
        OR JSON_TYPE(
            JSON_EXTRACT(
                jsonOption,
                '$.policy_value.field_policy_attribute_on_scope'
            )
        ) <> 'STRING'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'policy_value.field_policy_attribute_on_scope '
                'phải là string';
    END IF;

    SET policyAttributeOnScopeField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonOption,
                '$.policy_value.field_policy_attribute_on_scope'
            )
        );

    /*
     * Validate + quote ngay.
     *
     * lib_fnQuoteIdentifier() chịu trách nhiệm
     * reject identifier không hợp lệ.
     */
    SET qPolicyAttributeOnScopeField =
        lib_fnQuoteIdentifier(
            policyAttributeOnScopeField
        );

    /*--------------------------------------------------------------------------------------------------------------
     * LINK-only option.
     *-------------------------------------------------------------------------------------------------------------*/
    IF NOT isDirect THEN

        IF
            JSON_CONTAINS_PATH(
                jsonOption,
                'one',
                '$.policy_value.source'
            ) = 0
            OR JSON_TYPE(
                JSON_EXTRACT(
                    jsonOption,
                    '$.policy_value.source'
                )
            ) <> 'STRING'
        THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'LINK policy_value.source '
                    'phải là string';
        END IF;

        SET optionPolicyValueSource =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonOption,
                    '$.policy_value.source'
                )
            );

        IF
            JSON_CONTAINS_PATH(
                jsonOption,
                'one',
                '$.policy_value.function_prototype_field'
            ) = 0
            OR JSON_TYPE(
                JSON_EXTRACT(
                    jsonOption,
                    '$.policy_value.function_prototype_field'
                )
            ) <> 'STRING'
        THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'LINK policy_value.function_prototype_field '
                    'phải là string';
        END IF;

        SET optionFunctionPrototypeField =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonOption,
                    '$.policy_value.function_prototype_field'
                )
            );

    END IF;

    /*==============================================================================================================
     * BƯỚC 2
     *
     * Đọc schema chung.
     *=============================================================================================================*/

    /*
     * db_name.
     */
    IF
        JSON_EXTRACT(
            jsonHPSchema,
            '$.db_name'
        ) IS NULL
        OR JSON_TYPE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.db_name'
            )
        ) = 'NULL'
    THEN
        SET dbName = NULL;
    ELSE
        SET dbName =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.db_name'
                )
            );
    END IF;

    /*
     * NODE.
     */
    SET nodeSource =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.node.source'
            )
        );

    SET nodeIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.node.id_field'
            )
        );

    SET nodeParentField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.node.parent_field'
            )
        );

    /*
     * NODE_POLICY.
     */
    SET nodePolicySource =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.node_policy.source'
            )
        );

    SET npNodeIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.node_policy.node_id_field'
            )
        );

    SET npPolicyIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.node_policy.policy_id_field'
            )
        );

    SET npPolicyAttributeOnScopeField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.node_policy.field_policy_attribute_on_scope'
            )
        );

    /*
     * Option và schema phải mô tả cùng một field.
     */
    IF
        npPolicyAttributeOnScopeField
        <>
        policyAttributeOnScopeField
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'field_policy_attribute_on_scope '
                'không đồng nhất giữa schema và option';
    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Full source.
     *
     * Tận dụng lib_fnGetFullTableName().
     *-------------------------------------------------------------------------------------------------------------*/
    SET fullNodePolicySource =
        lib_fnGetFullTableName(
            dbName,
            nodePolicySource
        );

    /*--------------------------------------------------------------------------------------------------------------
     * Quote identifiers.
     *-------------------------------------------------------------------------------------------------------------*/
    SET qNPNodeIdField =
        lib_fnQuoteIdentifier(
            npNodeIdField
        );

    SET qNPPolicyIdField =
        lib_fnQuoteIdentifier(
            npPolicyIdField
        );

    SET qNPPolicyAttributeOnScopeField =
        lib_fnQuoteIdentifier(
            npPolicyAttributeOnScopeField
        );

    /*==============================================================================================================
     * BƯỚC 3
     *
     * Normalize policy input.
     *
     * Helper tạo:
     *
     *     tmp_lib_hp_policy_input
     *
     *         policy_id
     *=============================================================================================================*/
    CALL lib_spHPBuildPolicyInput(
        jsonPolicyId
    );

    /*==============================================================================================================
     * BƯỚC 4
     *
     * Build ancestor path.
     *
     * Helper tạo:
     *
     *     tmp_lib_hp_ancestor
     *
     *         node_id
     *         depth
     *
     * current node:
     *     depth = 0
     *
     * parent:
     *     depth = 1
     *
     * ...
     *=============================================================================================================*/
    CALL lib_spHPBuildAncestorPath(
        dbName,
        nodeSource,
        nodeIdField,
        nodeParentField,
        nodeId
    );

    SELECT COUNT(*)
    INTO ancestorCount
    FROM tmp_lib_hp_ancestor;

    IF ancestorCount = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Node không tồn tại';
    END IF;

    /*==============================================================================================================
     * BƯỚC 5
     *
     * Build NODE_POLICY candidates.
     *
     * precedence chính là ancestor.depth:
     *
     *     current node = 0
     *     parent       = 1
     *     ...
     *
     * Số nhỏ nhất thắng.
     *=============================================================================================================*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_node_policy_candidate;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_node_policy_candidate
    (
        policy_id BIGINT NOT NULL,

        binding_node_id BIGINT NOT NULL,

        precedence INT NOT NULL,

        /*
         * Field này luôn tồn tại trong thiết kế mới.
         *
         * Giá trị của row có thể NULL nếu nghiệp vụ
         * Application cho phép, nhưng schema field
         * không bao giờ absent.
         */
        policy_attribute_on_scope
            LONGTEXT NULL,

        KEY idx__tmp_hp_node_candidate__policy_precedence
        (
            policy_id,
            precedence
        )
    );

    SET finalSQL = CONCAT(
        'INSERT INTO tmp_lib_hp_node_policy_candidate (',
        '    policy_id, ',
        '    binding_node_id, ',
        '    precedence, ',
        '    policy_attribute_on_scope',
        ') ',

        'SELECT ',
        '    np.',
        qNPPolicyIdField,
        ', ',

        '    a.node_id, ',
        '    a.depth, ',

        '    np.',
        qNPPolicyAttributeOnScopeField,
        ' ',

        'FROM ',
        fullNodePolicySource,
        ' np ',

        'JOIN tmp_lib_hp_ancestor a ',
        '  ON a.node_id = np.',
        qNPNodeIdField,
        ' ',

        'JOIN tmp_lib_hp_policy_input pi ',
        '  ON pi.policy_id = np.',
        qNPPolicyIdField
    );

    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    /*==============================================================================================================
     * BƯỚC 6
     *
     * Chọn effective NODE_POLICY.
     *
     * nearest/deepest scope wins.
     *=============================================================================================================*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_effective_node_policy;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_effective_node_policy
    (
        policy_id BIGINT NOT NULL,

        binding_node_id BIGINT NOT NULL,

        policy_attribute_on_scope
            LONGTEXT NULL,

        PRIMARY KEY (policy_id)
    );

    /*
     * Nếu DB có nhiều NODE_POLICY row cùng:
     *
     *     policy_id
     *     node_id
     *
     * ở precedence thắng, PRIMARY KEY(policy_id)
     * sẽ làm lộ lỗi integrity.
     *
     * Không âm thầm chọn record ngẫu nhiên.
     */
    INSERT INTO
        tmp_lib_hp_effective_node_policy
        (
            policy_id,
            binding_node_id,
            policy_attribute_on_scope
        )
    SELECT
        c.policy_id,
        c.binding_node_id,
        c.policy_attribute_on_scope

    FROM tmp_lib_hp_node_policy_candidate c

    JOIN (
        SELECT
            policy_id,
            MIN(precedence) AS min_precedence

        FROM tmp_lib_hp_node_policy_candidate

        GROUP BY policy_id
    ) m
      ON m.policy_id = c.policy_id
     AND m.min_precedence = c.precedence;

    /*
     * Các intermediate table này không cần nữa.
     */
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_policy_input;

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_node_policy_candidate;

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_ancestor;

    /*==============================================================================================================
     * BƯỚC 7
     *
     * DIRECT.
     *
     * field_policy_attribute_on_scope chính là final value.
     *=============================================================================================================*/
    IF isDirect THEN

        SELECT
            policy_id,
            policy_attribute_on_scope AS value

        FROM tmp_lib_hp_effective_node_policy

        ORDER BY policy_id;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_effective_node_policy;

        LEAVE proc;
    END IF;

    /*==============================================================================================================
     *
     * LINK
     *
     *=============================================================================================================*/

    /*==============================================================================================================
     * BƯỚC 8
     *
     * Đọc POLICY + POLICY_FUNCTION_COST schema.
     *=============================================================================================================*/

    /*
     * POLICY.
     */
    SET policySource =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy.source'
            )
        );

    SET policyIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy.id_field'
            )
        );

    SET functionPrototypeField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy.function_prototype_field'
            )
        );

    /*
     * Schema và option phải thống nhất.
     */
    IF
        functionPrototypeField
        <>
        optionFunctionPrototypeField
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'function_prototype_field '
                'không đồng nhất giữa schema và option';
    END IF;

    /*
     * POLICY_FUNCTION_COST.
     */
    SET policyFunctionCostSource =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy_function_cost.source'
            )
        );

    SET pfcNodeIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy_function_cost.node_id_field'
            )
        );

    SET pfcPolicyIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy_function_cost.policy_id_field'
            )
        );

    IF
        policyFunctionCostSource
        <>
        optionPolicyValueSource
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'policy_function_cost.source '
                'không đồng nhất với policy_value.source';
    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Full sources.
     *-------------------------------------------------------------------------------------------------------------*/
    SET fullPolicySource =
        lib_fnGetFullTableName(
            dbName,
            policySource
        );

    SET fullPFCSource =
        lib_fnGetFullTableName(
            dbName,
            policyFunctionCostSource
        );

    /*--------------------------------------------------------------------------------------------------------------
     * Quote identifiers.
     *-------------------------------------------------------------------------------------------------------------*/
    SET qPolicyIdField =
        lib_fnQuoteIdentifier(
            policyIdField
        );

    SET qFunctionPrototypeField =
        lib_fnQuoteIdentifier(
            functionPrototypeField
        );

    SET qPFCNodeIdField =
        lib_fnQuoteIdentifier(
            pfcNodeIdField
        );

    SET qPFCPolicyIdField =
        lib_fnQuoteIdentifier(
            pfcPolicyIdField
        );

    /*==============================================================================================================
     * BƯỚC 9
     *
     * Materialize function_prototype.
     *=============================================================================================================*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_node_policy_eval;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_node_policy_eval
    (
        seq INT NOT NULL AUTO_INCREMENT,

        policy_id BIGINT NOT NULL,

        /*
         * Node nơi binding thắng nằm.
         *
         * Có thể là nodeId hiện tại hoặc ancestor.
         */
        binding_node_id BIGINT NOT NULL,

        function_prototype
            LONGTEXT NULL,

        policy_attribute_on_scope
            LONGTEXT NULL,

        PRIMARY KEY (seq),

        UNIQUE KEY uq__tmp_hp_node_policy_eval__policy_id
        (
            policy_id
        )
    );

    SET finalSQL = CONCAT(
        'INSERT INTO tmp_lib_hp_node_policy_eval (',
        '    policy_id, ',
        '    binding_node_id, ',
        '    function_prototype, ',
        '    policy_attribute_on_scope',
        ') ',

        'SELECT ',
        '    e.policy_id, ',
        '    e.binding_node_id, ',
        '    p.',
        qFunctionPrototypeField,
        ', ',
        '    e.policy_attribute_on_scope ',

        'FROM tmp_lib_hp_effective_node_policy e ',

        'JOIN ',
        fullPolicySource,
        ' p ',
        '  ON p.',
        qPolicyIdField,
        ' = e.policy_id'
    );

    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    /*
     * POLICY phải có definition tương ứng
     * cho mọi effective policy.
     */
    SELECT COUNT(*)
    INTO nEffectivePolicy
    FROM tmp_lib_hp_effective_node_policy;

    SELECT COUNT(*)
    INTO nPolicyEval
    FROM tmp_lib_hp_node_policy_eval;

    IF nEffectivePolicy <> nPolicyEval THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Không tìm thấy POLICY definition '
                'cho một effective Node policy';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_effective_node_policy;

    /*==============================================================================================================
     * BƯỚC 10
     *
     * Result table.
     *=============================================================================================================*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_node_policy_result;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_node_policy_result
    (
        policy_id BIGINT NOT NULL,

        /*
         * Generic output container.
         */
        value LONGTEXT NULL,

        PRIMARY KEY (policy_id)
    );

    /*==============================================================================================================
     * BƯỚC 11
     *
     * Evaluate từng LINK policy.
     *=============================================================================================================*/
    SET i = 1;

    WHILE i <= nPolicyEval DO

        SELECT
            policy_id,
            binding_node_id,
            function_prototype,
            policy_attribute_on_scope

        INTO
            currentPolicyId,
            currentBindingNodeId,
            currentFunctionPrototype,
            currentPolicyAttributeOnScope

        FROM tmp_lib_hp_node_policy_eval

        WHERE seq = i;

        /*----------------------------------------------------------------------------------------------------------
         * Validate function prototype.
         *
         * Prototype phải hoàn toàn tường minh.
         *
         * Ví dụ:
         *
         *     fnA(a,b,c)
         *
         *     fnB(
         *         a,
         *         b,
         *         permission_mask
         *     )
         *
         * SP KHÔNG append
         * field_policy_attribute_on_scope.
         *---------------------------------------------------------------------------------------------------------*/
        IF
            NOT lib_fnHPIsFunctionPrototypeValid(
                currentFunctionPrototype
            )
        THEN
            SET errorMsg =
                CONCAT(
                    'function_prototype không hợp lệ ',
                    'tại policy_id=',
                    currentPolicyId
                );

            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = errorMsg;
        END IF;

        /*----------------------------------------------------------------------------------------------------------
         * POLICY_FUNCTION_COST context.
         *
         * Điểm quan trọng:
         *
         *     pfc.node_id = nodeId
         *
         * tức Node ĐANG ĐƯỢC evaluate.
         *
         * Không phải:
         *
         *     currentBindingNodeId
         *
         * vì binding có thể nằm trên ancestor.
         *
         * Attribute của ancestor đã được mang riêng qua:
         *
         *     currentPolicyAttributeOnScope
         *---------------------------------------------------------------------------------------------------------*/
        SET @lib_hp_value_context_count = 0;

        SET finalSQL = CONCAT(
            'SELECT COUNT(*) ',
            'INTO @lib_hp_value_context_count ',

            'FROM ',
            fullPFCSource,
            ' pfc ',

            'WHERE pfc.',
            qPFCNodeIdField,
            ' = ',
            lib_fnToSQLLiteral(
                nodeId,
                'BIGINT'
            ),

            ' AND pfc.',
            qPFCPolicyIdField,
            ' = ',
            lib_fnToSQLLiteral(
                currentPolicyId,
                'BIGINT'
            )
        );

        PREPARE stmt FROM finalSQL;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        IF @lib_hp_value_context_count = 0 THEN

            SET errorMsg =
                CONCAT(
                    'Không tìm thấy POLICY_FUNCTION_COST ',
                    'context cho policy_id=',
                    currentPolicyId
                );

            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = errorMsg;

        END IF;

        IF @lib_hp_value_context_count > 1 THEN

            SET errorMsg =
                CONCAT(
                    'POLICY_FUNCTION_COST context ',
                    'không duy nhất cho policy_id=',
                    currentPolicyId
                );

            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = errorMsg;

        END IF;

        /*----------------------------------------------------------------------------------------------------------
         * field_policy_attribute_on_scope luôn tồn tại.
         *
         * Không còn nhánh:
         *
         *     IF field IS NULL ...
         *
         * như thiết kế field_on_binding cũ.
         *---------------------------------------------------------------------------------------------------------*/
        SET @lib_hp_policy_attribute_on_scope =
            currentPolicyAttributeOnScope;

        /*----------------------------------------------------------------------------------------------------------
         * Build evaluation context.
         *
         * hp_context gồm:
         *
         *     toàn bộ column của POLICY_FUNCTION_COST
         *
         *         +
         *
         *     field_policy_attribute_on_scope
         *
         * Ví dụ:
         *
         * policy_value:
         *
         *     field_policy_attribute_on_scope
         *         = permission_mask
         *
         * effective scope:
         *
         *     permission_mask = 7
         *
         * function prototype:
         *
         *     fnPermission(
         *         user_id,
         *         category_id,
         *         permission_mask
         *     )
         *
         * SP chỉ tạo SQL context.
         *
         * Không sửa prototype.
         * Không append argument.
         *---------------------------------------------------------------------------------------------------------*/
        SET finalSQL = CONCAT(
            'INSERT INTO tmp_lib_hp_node_policy_result (',
            '    policy_id, ',
            '    value',
            ') ',

            'SELECT ',
            lib_fnToSQLLiteral(
                currentPolicyId,
                'BIGINT'
            ),
            ', ',

            currentFunctionPrototype,
            ' ',

            'FROM (',

            '    SELECT ',
            '        pfc.*, ',
            '        @lib_hp_policy_attribute_on_scope AS ',
            qPolicyAttributeOnScopeField,
            ' ',

            '    FROM ',
            fullPFCSource,
            ' pfc ',

            '    WHERE pfc.',
            qPFCNodeIdField,
            ' = ',
            lib_fnToSQLLiteral(
                nodeId,
                'BIGINT'
            ),

            '    AND pfc.',
            qPFCPolicyIdField,
            ' = ',
            lib_fnToSQLLiteral(
                currentPolicyId,
                'BIGINT'
            ),

            ') hp_context'
        );

        PREPARE stmt FROM finalSQL;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @lib_hp_value_context_count =
            NULL;

        SET @lib_hp_policy_attribute_on_scope =
            NULL;

        SET i = i + 1;

    END WHILE;

    /*==============================================================================================================
     * BƯỚC 12
     *
     * Final output.
     *=============================================================================================================*/
    SELECT
        policy_id,
        value

    FROM tmp_lib_hp_node_policy_result

    ORDER BY policy_id;

    /*==============================================================================================================
     * Cleanup.
     *=============================================================================================================*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_node_policy_eval;

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_node_policy_result;

    SET @lib_hp_value_context_count =
        NULL;

    SET @lib_hp_policy_attribute_on_scope =
        NULL;

END ;;

DELIMITER ;