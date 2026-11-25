DROP PROCEDURE IF EXISTS lib_spHPGetResourcePolicies;

DELIMITER ;;

CREATE PROCEDURE lib_spHPGetResourcePolicies(
    IN jsonHPSchema JSON,
    IN jsonOption   JSON,
    IN nodeId       BIGINT,
    IN resourceId   BIGINT,
    IN jsonPolicyId JSON
)
MODIFIES SQL DATA
proc: BEGIN
    /*
     * Node context cuối cùng.
     *
     * 1-N:
     *     được resolve từ RESOURCE.node_id.
     *
     * N-N:
     *     chính là nodeId do caller cung cấp,
     *     sau khi relation được kiểm tra.
     */
    DECLARE resolvedNodeId BIGINT;

    /*
     * OPTION_POLICY_VALUE.
     */
    DECLARE isDirect    BOOLEAN DEFAULT FALSE;
    DECLARE isOneToMany BOOLEAN DEFAULT FALSE;

    DECLARE fieldPolicyAttributeOnScope VARCHAR(64);

    /*
     * Common schema.
     */
    DECLARE dbName VARCHAR(64);

    /*
     * POLICY.
     */
    DECLARE policySource           VARCHAR(64);
    DECLARE policyIdField          VARCHAR(64);
    DECLARE functionPrototypeField VARCHAR(64);

    /*
     * POLICY_FUNCTION_COST.
     *
     * LINK:
     *
     * 1-N:
     *     resource_id
     *     policy_id
     *     params...
     *
     * N-N:
     *     node_id
     *     resource_id
     *     policy_id
     *     params...
     */
    DECLARE policyFunctionCostSource VARCHAR(64);

    DECLARE pfcNodeIdField     VARCHAR(64);
    DECLARE pfcResourceIdField VARCHAR(64);
    DECLARE pfcPolicyIdField   VARCHAR(64);

    /*
     * Full source names.
     */
    DECLARE fullPolicySource VARCHAR(255);
    DECLARE fullPFCSource    VARCHAR(255);

    /*
     * Quoted identifiers.
     */
    DECLARE qPolicyIdField          VARCHAR(70);
    DECLARE qFunctionPrototypeField VARCHAR(70);

    DECLARE qPFCNodeIdField     VARCHAR(70);
    DECLARE qPFCResourceIdField VARCHAR(70);
    DECLARE qPFCPolicyIdField   VARCHAR(70);

    DECLARE qPolicyAttributeOnScopeField VARCHAR(70);

    /*
     * Số effective policy.
     */
    DECLARE nEffectivePolicy INT DEFAULT 0;
    DECLARE nPolicyEval      INT DEFAULT 0;

    /*
     * Loop LINK policy.
     */
    DECLARE i INT DEFAULT 0;

    DECLARE currentPolicyId BIGINT;

    DECLARE currentFunctionPrototype    LONGTEXT;
    DECLARE currentPolicyAttributeValue LONGTEXT;

    /*
     * Dynamic SQL.
     */
    DECLARE finalSQL LONGTEXT;

    DECLARE errorMsg VARCHAR(255);

    /*--------------------------------------------------------------------------------------------------------------
     * Cleanup.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        /*
         * Temp table có thể do BuildEffective...
         * hoặc chính procedure này tạo.
         */
        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_policy_input;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_policy_candidate;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_ancestor;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_effective_resource_policy;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_resource_policy_eval;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_resource_policy_result;

        /*
         * Session variables dùng bởi dynamic SQL.
         */
        SET @lib_hp_value_context_count    = NULL;
        SET @lib_hp_policy_attribute_value = NULL;

        RESIGNAL;
    END;

    /*--------------------------------------------------------------------------------------------------------------
     * Bước 1.
     *
     * Resolve effective policy scope/binding.
     *
     * Helper chịu trách nhiệm:
     *
     *     - validate resourceId/nodeId/jsonPolicyId;
     *     - validate các option phục vụ việc resolve scope;
     *     - xác định Resource - Node context;
     *     - build ancestor path;
     *     - RESOURCE_POLICY precedence = 0;
     *     - RESOURCE_NODE_POLICY precedence = depth + 1;
     *     - chọn binding thắng cho từng policy_id.
     *
     * Output:
     *
     *     tmp_lib_hp_effective_resource_policy
     *
     *         policy_id
     *         binding_node_id
     *         binding_type
     *         policy_attribute_value
     *-------------------------------------------------------------------------------------------------------------*/
    CALL lib_spHPBuildEffectiveResourcePolicyBindings(
        jsonHPSchema,
        jsonOption,
        nodeId,
        resourceId,
        jsonPolicyId,
        resolvedNodeId
    );

    /*--------------------------------------------------------------------------------------------------------------
     * Đọc OPTION_POLICY_VALUE.
     *
     * BuildEffective... không cần biết DIRECT/LINK,
     * vì vậy policy_value.is_direct được validate tại đây.
     *-------------------------------------------------------------------------------------------------------------*/

    /*
     * policy_value.is_direct.
     */
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

    /*
     * resource_node_one_to_many đã được
     * BuildEffective... validate.
     */
    SET isOneToMany =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonOption,
                '$.resource_node_one_to_many'
            )
        ) = 'true';

    /*--------------------------------------------------------------------------------------------------------------
     * DIRECT.
     *
     * Với DIRECT:
     *
     *     policy_attribute_value
     *
     * chính là final policy value.
     *
     * Không cần:
     *
     *     POLICY
     *     POLICY_FUNCTION_COST
     *     function_prototype
     *
     * nữa.
     *-------------------------------------------------------------------------------------------------------------*/
    IF isDirect THEN

        SELECT
            policy_id,
            policy_attribute_value AS value

        FROM tmp_lib_hp_effective_resource_policy

        ORDER BY policy_id;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_effective_resource_policy;

        LEAVE proc;
    END IF;

    /*==============================================================================================================
     * LINK
     *=============================================================================================================*/

    /*
     * field_policy_attribute_on_scope đã được
     * BuildEffective... validate là string không rỗng
     * và là SQL identifier hợp lệ.
     *
     * LINK luôn có field này theo contract hiện tại.
     */
    SET fieldPolicyAttributeOnScope =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonOption,
                '$.policy_value.field_policy_attribute_on_scope'
            )
        );

    SET qPolicyAttributeOnScopeField =
        lib_fnQuoteIdentifier(
            fieldPolicyAttributeOnScope
        );

    /*--------------------------------------------------------------------------------------------------------------
     * Bước 2.
     *
     * Đọc schema phục vụ LINK.
     *-------------------------------------------------------------------------------------------------------------*/

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
     * POLICY_FUNCTION_COST.
     */
    SET policyFunctionCostSource =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy_function_cost.source'
            )
        );

    SET pfcResourceIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy_function_cost.resource_id_field'
            )
        );

    SET pfcPolicyIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.policy_function_cost.policy_id_field'
            )
        );

    /*
     * N-N cần thêm node_id trong function-cost context.
     */
    IF NOT isOneToMany THEN

        SET pfcNodeIdField =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.policy_function_cost.node_id_field'
                )
            );

    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Full source names.
     *
     * Tận dụng lib_fnGetFullTableName().
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
     *
     * Tận dụng lib_fnQuoteIdentifier().
     *-------------------------------------------------------------------------------------------------------------*/
    SET qPolicyIdField =
        lib_fnQuoteIdentifier(
            policyIdField
        );

    SET qFunctionPrototypeField =
        lib_fnQuoteIdentifier(
            functionPrototypeField
        );

    SET qPFCResourceIdField =
        lib_fnQuoteIdentifier(
            pfcResourceIdField
        );

    SET qPFCPolicyIdField =
        lib_fnQuoteIdentifier(
            pfcPolicyIdField
        );

    IF NOT isOneToMany THEN

        SET qPFCNodeIdField =
            lib_fnQuoteIdentifier(
                pfcNodeIdField
            );

    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Bước 3.
     *
     * Gắn function_prototype vào các effective policy.
     *
     * Tạo:
     *
     *     tmp_lib_hp_resource_policy_eval
     *
     * Không đưa POLICY vào dynamic evaluation query sau này.
     *
     * Ta materialize trước:
     *
     *     policy_id
     *     function_prototype
     *     policy_attribute_value
     *
     * Sau đó evaluation context chỉ còn:
     *
     *     POLICY_FUNCTION_COST params
     *     +
     *     field_policy_attribute_on_scope
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_resource_policy_eval;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_resource_policy_eval
    (
        seq INT NOT NULL AUTO_INCREMENT,

        policy_id BIGINT NOT NULL,

        function_prototype LONGTEXT NULL,

        /*
         * Giá trị policy attribute lấy từ
         * effective policy scope.
         */
        policy_attribute_value LONGTEXT NULL,

        PRIMARY KEY (seq),

        UNIQUE KEY uq__tmp_hp_resource_policy_eval__policy_id (
            policy_id
        )
    );

    SET finalSQL = CONCAT(
        'INSERT INTO tmp_lib_hp_resource_policy_eval (',
        '    policy_id, ',
        '    function_prototype, ',
        '    policy_attribute_value',
        ') ',

        'SELECT ',
        '    e.policy_id, ',
        '    p.',
        qFunctionPrototypeField,
        ', ',
        '    e.policy_attribute_value ',

        'FROM tmp_lib_hp_effective_resource_policy e ',

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

    /*--------------------------------------------------------------------------------------------------------------
     * POLICY source phải có đúng definition cho mỗi
     * effective policy.
     *
     * Nếu thiếu policy record, JOIN ở trên sẽ làm mất row.
     *-------------------------------------------------------------------------------------------------------------*/
    SELECT COUNT(*)
    INTO nEffectivePolicy
    FROM tmp_lib_hp_effective_resource_policy;

    SELECT COUNT(*)
    INTO nPolicyEval
    FROM tmp_lib_hp_resource_policy_eval;

    IF nEffectivePolicy <> nPolicyEval THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Không tìm thấy POLICY definition cho một effective policy';
    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Effective policy scope/binding không cần nữa.
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_effective_resource_policy;

    /*--------------------------------------------------------------------------------------------------------------
     * Bảng kết quả cuối.
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_resource_policy_result;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_resource_policy_result
    (
        policy_id BIGINT NOT NULL,

        /*
         * Generic policy value.
         *
         * Stored Function có thể trả số/string...
         * LONGTEXT là container trung gian generic.
         */
        value LONGTEXT NULL,

        PRIMARY KEY (policy_id)
    );

    /*--------------------------------------------------------------------------------------------------------------
     * Bước 4.
     *
     * Evaluate từng LINK policy.
     *
     * Lý do phải loop:
     *
     *     mỗi policy có thể dùng function_prototype khác nhau:
     *
     *         fnA(a,b,c)
     *         fnB(x,y)
     *         fnC(d,e,permission_mask)
     *
     * SQL function name/expression vì vậy phải được
     * build động cho từng policy.
     *-------------------------------------------------------------------------------------------------------------*/
    SET i = 1;

    WHILE i <= nPolicyEval DO

        SELECT
            policy_id,
            function_prototype,
            policy_attribute_value

        INTO
            currentPolicyId,
            currentFunctionPrototype,
            currentPolicyAttributeValue

        FROM tmp_lib_hp_resource_policy_eval

        WHERE seq = i;

        /*----------------------------------------------------------------------------------------------------------
         * function_prototype là SQL fragment duy nhất được
         * lấy từ DB data rồi đưa trực tiếp vào dynamic SQL.
         *
         * Vì vậy bắt buộc validate trước.
         *
         * Chỉ chấp nhận dạng:
         *
         *     fn()
         *     fn(a)
         *     fn(a,b,c)
         *     fn(a,b,c,permission_mask)
         *
         * Stored Procedure KHÔNG tự append
         * policy attribute parameter.
         *
         * Nếu function cần policy attribute thì
         * function_prototype phải khai báo explicit field đó.
         *---------------------------------------------------------------------------------------------------------*/
        IF
            NOT lib_fnHPIsFunctionPrototypeValid(
                currentFunctionPrototype
            )
        THEN
            SET errorMsg =
                CONCAT(
                    'function_prototype không hợp lệ tại policy_id=',
                    currentPolicyId
                );

            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = errorMsg;
        END IF;

        /*----------------------------------------------------------------------------------------------------------
         * Bước 4a.
         *
         * Kiểm tra POLICY_FUNCTION_COST context.
         *
         * Contract:
         *
         * 1-N:
         *
         *     resource_id
         *     policy_id
         *
         * phải xác định đúng một row.
         *
         * N-N:
         *
         *     node_id
         *     resource_id
         *     policy_id
         *
         * phải xác định đúng một row.
         *---------------------------------------------------------------------------------------------------------*/
        SET @lib_hp_value_context_count = 0;

        SET finalSQL = CONCAT(
            'SELECT COUNT(*) ',
            'INTO @lib_hp_value_context_count ',

            'FROM ',
            fullPFCSource,
            ' pvc ',

            'WHERE pvc.',
            qPFCResourceIdField,
            ' = ',
            lib_fnToSQLLiteral(
                resourceId,
                'BIGINT'
            ),

            ' AND pvc.',
            qPFCPolicyIdField,
            ' = ',
            lib_fnToSQLLiteral(
                currentPolicyId,
                'BIGINT'
            ),

            IF(
                isOneToMany,
                '',
                CONCAT(
                    ' AND pvc.',
                    qPFCNodeIdField,
                    ' = ',
                    lib_fnToSQLLiteral(
                        resolvedNodeId,
                        'BIGINT'
                    )
                )
            )
        );

        PREPARE stmt FROM finalSQL;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        IF @lib_hp_value_context_count = 0 THEN

            SET errorMsg =
                CONCAT(
                    'Không tìm thấy POLICY_FUNCTION_COST context cho policy_id=',
                    currentPolicyId
                );

            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = errorMsg;

        END IF;

        IF @lib_hp_value_context_count > 1 THEN

            SET errorMsg =
                CONCAT(
                    'POLICY_FUNCTION_COST context không duy nhất cho policy_id=',
                    currentPolicyId
                );

            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = errorMsg;

        END IF;

        /*----------------------------------------------------------------------------------------------------------
         * Bước 4b.
         *
         * Đưa policy attribute của effective policy scope
         * vào SQL context.
         *
         * Ví dụ:
         *
         *     OPTION_POLICY_VALUE:
         *
         *         field_policy_attribute_on_scope
         *             = permission_mask
         *
         *     effective scope:
         *
         *         policy_attribute_value = 7
         *
         * Ta tạo context:
         *
         *     permission_mask = 7
         *
         * để prototype:
         *
         *     fn(a,b,c,permission_mask)
         *
         * chạy nguyên bản.
         *
         * Field policy attribute luôn được đưa vào context.
         *
         * Tuy nhiên function_prototype có sử dụng field đó
         * hay không hoàn toàn do prototype quyết định.
         *
         * Không parse prototype.
         * Không append parameter.
         *---------------------------------------------------------------------------------------------------------*/
        SET @lib_hp_policy_attribute_value =
            currentPolicyAttributeValue;

        /*----------------------------------------------------------------------------------------------------------
         * Bước 4c.
         *
         * Dynamic SQL evaluation.
         *
         * Ta tạo đúng một derived context:
         *
         *     hp_context
         *
         * chứa:
         *
         *     toàn bộ column của POLICY_FUNCTION_COST
         *
         *     +
         *
         *     field_policy_attribute_on_scope
         *
         * Do đó các identifier nằm trong prototype có thể
         * được resolve trực tiếp:
         *
         *     fn(a,b,c)
         *
         *     fn(a,b,c,permission_mask)
         *---------------------------------------------------------------------------------------------------------*/
        SET finalSQL = CONCAT(
            'INSERT INTO tmp_lib_hp_resource_policy_result (',
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
            '        pvc.*, ',
            '        @lib_hp_policy_attribute_value AS ',
            qPolicyAttributeOnScopeField,
            ' ',

            '    FROM ',
            fullPFCSource,
            ' pvc ',

            '    WHERE pvc.',
            qPFCResourceIdField,
            ' = ',
            lib_fnToSQLLiteral(
                resourceId,
                'BIGINT'
            ),

            '    AND pvc.',
            qPFCPolicyIdField,
            ' = ',
            lib_fnToSQLLiteral(
                currentPolicyId,
                'BIGINT'
            ),

            IF(
                isOneToMany,
                '',
                CONCAT(
                    ' AND pvc.',
                    qPFCNodeIdField,
                    ' = ',
                    lib_fnToSQLLiteral(
                        resolvedNodeId,
                        'BIGINT'
                    )
                )
            ),

            ') hp_context'
        );

        PREPARE stmt FROM finalSQL;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @lib_hp_value_context_count     = NULL;
        SET @lib_hp_policy_attribute_value = NULL;

        SET i = i + 1;
    END WHILE;

    /*--------------------------------------------------------------------------------------------------------------
     * Bước 5.
     *
     * Final output của lib_spHPGetResourcePolicies.
     *-------------------------------------------------------------------------------------------------------------*/
    SELECT
        policy_id,
        value

    FROM tmp_lib_hp_resource_policy_result

    ORDER BY policy_id;

    /*--------------------------------------------------------------------------------------------------------------
     * Cleanup.
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_resource_policy_eval;

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_resource_policy_result;

    SET @lib_hp_value_context_count     = NULL;
    SET @lib_hp_policy_attribute_value = NULL;

END ;;

DELIMITER ;