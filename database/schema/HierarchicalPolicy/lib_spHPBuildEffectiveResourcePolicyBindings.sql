DROP PROCEDURE IF EXISTS
    lib_spHPBuildEffectiveResourcePolicyBindings;

DELIMITER ;;

CREATE PROCEDURE lib_spHPBuildEffectiveResourcePolicyBindings(
    IN  jsonHPSchema  JSON,
    IN  jsonOption    JSON,
    IN  nodeId        BIGINT,
    IN  resourceId    BIGINT,
    IN  jsonPolicyId  JSON,

    OUT resolvedNodeId BIGINT
)
MODIFIES SQL DATA
proc: BEGIN
    DECLARE dbName VARCHAR(64);

    /*
     * RESOURCE.
     */
    DECLARE resourceSource      VARCHAR(64);
    DECLARE resourceIdField     VARCHAR(64);
    DECLARE resourceNodeIdField VARCHAR(64);

    /*
     * NODE.
     */
    DECLARE nodeSource      VARCHAR(64);
    DECLARE nodeIdField     VARCHAR(64);
    DECLARE nodeParentField VARCHAR(64);

    /*
     * NODE_RESOURCE.
     *
     * Chỉ sử dụng trong N-N.
     */
    DECLARE nodeResourceSource          VARCHAR(64);
    DECLARE nodeResourceNodeIdField     VARCHAR(64);
    DECLARE nodeResourceResourceIdField VARCHAR(64);

    /*
     * RESOURCE_NODE_POLICY.
     *
     * RESOURCE_NODE_POLICY là policy áp lên Resource
     * thông qua Node.
     *
     * Section này gồm:
     *
     *     source
     *     node_id_field
     *     policy_id_field
     *
     * Field chứa policy attribute trên policy scope
     * được xác định bởi:
     *
     *     OPTION_POLICY_VALUE
     *         .field_policy_attribute_on_scope
     *
     * Không có resource_id_field.
     */
    DECLARE resourceNodePolicySource VARCHAR(64);
    DECLARE rnpNodeIdField           VARCHAR(64);
    DECLARE rnpPolicyIdField         VARCHAR(64);

    /*
     * RESOURCE_POLICY.
     *
     * Chỉ tồn tại khi:
     *
     *     has_direct_resource_policy = true
     */
    DECLARE resourcePolicySource VARCHAR(64);
    DECLARE rpResourceIdField    VARCHAR(64);
    DECLARE rpPolicyIdField      VARCHAR(64);

    /*
     * OPTION_POLICY_VALUE
     *     .field_policy_attribute_on_scope.
     *
     * Field này luôn bắt buộc.
     *
     * Nó là tên field trên policy scope chứa
     * policy attribute.
     */
    DECLARE fieldPolicyAttributeOnScope VARCHAR(64);

    /*
     * Full source names.
     */
    DECLARE fullResourceSource VARCHAR(255);
    DECLARE fullRNPSource      VARCHAR(255);
    DECLARE fullRPSource       VARCHAR(255);
    DECLARE fullNRSource       VARCHAR(255);

    /*
     * Quoted identifiers.
     */
    DECLARE qResourceIdField     VARCHAR(70);
    DECLARE qResourceNodeIdField VARCHAR(70);

    DECLARE qRNPNodeIdField   VARCHAR(70);
    DECLARE qRNPPolicyIdField VARCHAR(70);

    DECLARE qRPResourceIdField VARCHAR(70);
    DECLARE qRPPolicyIdField   VARCHAR(70);

    DECLARE qNRNodeIdField     VARCHAR(70);
    DECLARE qNRResourceIdField VARCHAR(70);

    DECLARE qPolicyAttributeOnScopeField VARCHAR(70);

    /*
     * Options.
     */
    DECLARE isOneToMany BOOLEAN DEFAULT FALSE;

    DECLARE hasDirectResourcePolicy
        BOOLEAN DEFAULT FALSE;

    DECLARE ancestorCount INT DEFAULT 0;

    DECLARE finalSQL LONGTEXT;

    /*--------------------------------------------------------------------------------------------------------------
     * Cleanup khi có exception.
     *-------------------------------------------------------------------------------------------------------------*/
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_policy_input;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_policy_candidate;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_ancestor;

        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_effective_resource_policy;

        SET @lib_hp_resolved_node_id = NULL;
        SET @lib_hp_relation_count   = NULL;

        RESIGNAL;
    END;

    SET resolvedNodeId = NULL;

    /*--------------------------------------------------------------------------------------------------------------
     * Basic input validation.
     *-------------------------------------------------------------------------------------------------------------*/
    IF resourceId IS NULL OR resourceId <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'resourceId phải là số nguyên dương';
    END IF;

    IF
        nodeId IS NOT NULL
        AND nodeId <= 0
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'nodeId phải là null hoặc số nguyên dương';
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
     * Read HPResourceSchema options.
     *
     * Không suy luận option từ shape của jsonHPSchema.
     *-------------------------------------------------------------------------------------------------------------*/

    /*
     * resource_node_one_to_many.
     */
    IF
        JSON_CONTAINS_PATH(
            jsonOption,
            'one',
            '$.resource_node_one_to_many'
        ) = 0
        OR JSON_TYPE(
            JSON_EXTRACT(
                jsonOption,
                '$.resource_node_one_to_many'
            )
        ) <> 'BOOLEAN'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'resource_node_one_to_many phải là boolean';
    END IF;

    SET isOneToMany =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonOption,
                '$.resource_node_one_to_many'
            )
        ) = 'true';

    /*
     * has_direct_resource_policy.
     */
    IF
        JSON_CONTAINS_PATH(
            jsonOption,
            'one',
            '$.has_direct_resource_policy'
        ) = 0
        OR JSON_TYPE(
            JSON_EXTRACT(
                jsonOption,
                '$.has_direct_resource_policy'
            )
        ) <> 'BOOLEAN'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'has_direct_resource_policy phải là boolean';
    END IF;

    SET hasDirectResourcePolicy =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonOption,
                '$.has_direct_resource_policy'
            )
        ) = 'true';

    /*
     * policy_value.
     */
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

    /*
     * policy_value.field_policy_attribute_on_scope.
     *
     * Theo contract mới, field này luôn bắt buộc,
     * không phụ thuộc policy_value.is_direct.
     */
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
                'policy_value.field_policy_attribute_on_scope phải là string';
    END IF;

    SET fieldPolicyAttributeOnScope =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonOption,
                '$.policy_value.field_policy_attribute_on_scope'
            )
        );

    IF
        TRIM(fieldPolicyAttributeOnScope) = ''
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'policy_value.field_policy_attribute_on_scope không được rỗng';
    END IF;

    /*
     * Validate + quote identifier.
     */
    SET qPolicyAttributeOnScopeField =
        lib_fnQuoteIdentifier(
            fieldPolicyAttributeOnScope
        );

    /*--------------------------------------------------------------------------------------------------------------
     * Read common schema.
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
     * RESOURCE.
     */
    SET resourceSource =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.resource.source'
            )
        );

    SET resourceIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.resource.id_field'
            )
        );

    /*
     * RESOURCE_NODE_POLICY.
     *
     * Section này luôn tồn tại trong HPResourceSchema.
     */
    SET resourceNodePolicySource =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.resource_node_policy.source'
            )
        );

    SET rnpNodeIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.resource_node_policy.node_id_field'
            )
        );

    SET rnpPolicyIdField =
        JSON_UNQUOTE(
            JSON_EXTRACT(
                jsonHPSchema,
                '$.resource_node_policy.policy_id_field'
            )
        );

    /*--------------------------------------------------------------------------------------------------------------
     * Full source names.
     *
     * lib_fnGetFullTableName() đồng thời validate source/dbName.
     *-------------------------------------------------------------------------------------------------------------*/
    SET fullResourceSource =
        lib_fnGetFullTableName(
            dbName,
            resourceSource
        );

    SET fullRNPSource =
        lib_fnGetFullTableName(
            dbName,
            resourceNodePolicySource
        );

    /*--------------------------------------------------------------------------------------------------------------
     * Quote identifiers.
     *-------------------------------------------------------------------------------------------------------------*/
    SET qResourceIdField =
        lib_fnQuoteIdentifier(
            resourceIdField
        );

    SET qRNPNodeIdField =
        lib_fnQuoteIdentifier(
            rnpNodeIdField
        );

    SET qRNPPolicyIdField =
        lib_fnQuoteIdentifier(
            rnpPolicyIdField
        );

    /*--------------------------------------------------------------------------------------------------------------
     * Normalize input policy_id.
     *
     * Tạo:
     *
     *     tmp_lib_hp_policy_input
     *-------------------------------------------------------------------------------------------------------------*/
    CALL lib_spHPBuildPolicyInput(
        jsonPolicyId
    );

    /*--------------------------------------------------------------------------------------------------------------
     * Resolve Resource -> Node context.
     *-------------------------------------------------------------------------------------------------------------*/

    /*
     * 1-N:
     *
     *     RESOURCE.node_id
     *
     * là nguồn xác định Node context.
     */
    IF isOneToMany THEN

        SET resourceNodeIdField =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.resource.node_id_field'
                )
            );

        SET qResourceNodeIdField =
            lib_fnQuoteIdentifier(
                resourceNodeIdField
            );

        SET @lib_hp_resolved_node_id = NULL;

        SET finalSQL = CONCAT(
            'SELECT r.',
            qResourceNodeIdField,
            ' INTO @lib_hp_resolved_node_id ',

            'FROM ',
            fullResourceSource,
            ' r ',

            'WHERE r.',
            qResourceIdField,
            ' = ',
            lib_fnToSQLLiteral(
                resourceId,
                'BIGINT'
            ),

            ' LIMIT 1'
        );

        PREPARE stmt FROM finalSQL;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        IF @lib_hp_resolved_node_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'Resource không tồn tại hoặc chưa gắn Node';
        END IF;

        SET resolvedNodeId =
            @lib_hp_resolved_node_id;

        SET @lib_hp_resolved_node_id = NULL;

        /*
         * Nếu caller cung cấp nodeId thì phải khớp
         * với Node thực tế của Resource.
         */
        IF
            nodeId IS NOT NULL
            AND nodeId <> resolvedNodeId
        THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'Resource không thuộc nodeId được cung cấp';
        END IF;

    /*
     * N-N:
     *
     * nodeId là một phần bắt buộc của Resource context.
     */
    ELSE

        IF nodeId IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'nodeId là bắt buộc với quan hệ N-N';
        END IF;

        SET resolvedNodeId =
            nodeId;

        /*
         * NODE_RESOURCE.
         */
        SET nodeResourceSource =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.node_resource.source'
                )
            );

        SET nodeResourceNodeIdField =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.node_resource.node_id_field'
                )
            );

        SET nodeResourceResourceIdField =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.node_resource.resource_id_field'
                )
            );

        SET fullNRSource =
            lib_fnGetFullTableName(
                dbName,
                nodeResourceSource
            );

        SET qNRNodeIdField =
            lib_fnQuoteIdentifier(
                nodeResourceNodeIdField
            );

        SET qNRResourceIdField =
            lib_fnQuoteIdentifier(
                nodeResourceResourceIdField
            );

        /*
         * Kiểm tra cặp:
         *
         *     (node_id, resource_id)
         *
         * thực sự tồn tại.
         */
        SET @lib_hp_relation_count = 0;

        SET finalSQL = CONCAT(
            'SELECT COUNT(*) ',
            'INTO @lib_hp_relation_count ',

            'FROM ',
            fullNRSource,
            ' nr ',

            'WHERE nr.',
            qNRNodeIdField,
            ' = ',
            lib_fnToSQLLiteral(
                resolvedNodeId,
                'BIGINT'
            ),

            ' AND nr.',
            qNRResourceIdField,
            ' = ',
            lib_fnToSQLLiteral(
                resourceId,
                'BIGINT'
            )
        );

        PREPARE stmt FROM finalSQL;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        IF @lib_hp_relation_count = 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'Resource không thuộc Node được cung cấp';
        END IF;

        SET @lib_hp_relation_count = NULL;

    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Build ancestor path.
     *
     * tmp_lib_hp_ancestor:
     *
     *     node_id     depth
     *     -------     -----
     *     current       0
     *     parent        1
     *     ...
     *     root          n
     *-------------------------------------------------------------------------------------------------------------*/
    CALL lib_spHPBuildAncestorPath(
        dbName,
        nodeSource,
        nodeIdField,
        nodeParentField,
        resolvedNodeId
    );

    SELECT COUNT(*)
    INTO ancestorCount
    FROM tmp_lib_hp_ancestor;

    IF ancestorCount = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Node không tồn tại';
    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Candidate bindings.
     *
     * Precedence:
     *
     *     0
     *         RESOURCE_POLICY
     *
     *     1
     *         RESOURCE_NODE_POLICY tại current Node
     *
     *     2
     *         RESOURCE_NODE_POLICY tại parent
     *
     *     3...
     *         ancestor tiếp theo
     *
     * Số càng nhỏ => precedence càng cao.
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_policy_candidate;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_policy_candidate
    (
        policy_id       BIGINT NOT NULL,

        binding_node_id BIGINT NULL,

        precedence      INT NOT NULL,

        /*
         * 1 = RESOURCE_POLICY
         * 2 = RESOURCE_NODE_POLICY
         */
        binding_type    TINYINT NOT NULL,

        /*
         * Giá trị policy attribute nằm trên policy scope.
         *
         * Field chứa giá trị được xác định bởi:
         *
         *     policy_value
         *         .field_policy_attribute_on_scope
         *
         * DIRECT:
         *     policy attribute này chính là policy value.
         *
         * LINK:
         *     policy attribute này có thể được sử dụng
         *     như một argument trong function_prototype.
         *
         * Việc evaluate DIRECT/LINK không thuộc trách nhiệm
         * của procedure này.
         */
        policy_attribute_value LONGTEXT NULL,

        KEY idx__tmp_hp_candidate__policy_precedence (
            policy_id,
            precedence
        )
    );

    /*--------------------------------------------------------------------------------------------------------------
     * Direct RESOURCE_POLICY.
     *-------------------------------------------------------------------------------------------------------------*/
    IF hasDirectResourcePolicy THEN

        SET resourcePolicySource =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.resource_policy.source'
                )
            );

        SET rpResourceIdField =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.resource_policy.resource_id_field'
                )
            );

        SET rpPolicyIdField =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonHPSchema,
                    '$.resource_policy.policy_id_field'
                )
            );

        SET fullRPSource =
            lib_fnGetFullTableName(
                dbName,
                resourcePolicySource
            );

        SET qRPResourceIdField =
            lib_fnQuoteIdentifier(
                rpResourceIdField
            );

        SET qRPPolicyIdField =
            lib_fnQuoteIdentifier(
                rpPolicyIdField
            );

        SET finalSQL = CONCAT(
            'INSERT INTO tmp_lib_hp_policy_candidate (',
            '    policy_id, ',
            '    binding_node_id, ',
            '    precedence, ',
            '    binding_type, ',
            '    policy_attribute_value',
            ') ',

            'SELECT ',
            '    rp.',
            qRPPolicyIdField,
            ', ',
            '    NULL, ',
            '    0, ',
            '    1, ',
            '    rp.',
            qPolicyAttributeOnScopeField,

            ' FROM ',
            fullRPSource,
            ' rp ',

            ' JOIN tmp_lib_hp_policy_input pi ',
            '   ON pi.policy_id = rp.',
            qRPPolicyIdField,

            ' WHERE rp.',
            qRPResourceIdField,
            ' = ',
            lib_fnToSQLLiteral(
                resourceId,
                'BIGINT'
            )
        );

        PREPARE stmt FROM finalSQL;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * RESOURCE_NODE_POLICY:
     *
     *     current Node -> parent -> ... -> root
     *
     * RESOURCE_NODE_POLICY không có resource_id_field theo
     * HPResourceSchema hiện tại.
     *
     * Nó biểu diễn Resource-policy áp qua Node.
     * Resource context đã được xác định ở bước phía trên.
     *-------------------------------------------------------------------------------------------------------------*/
    SET finalSQL = CONCAT(
        'INSERT INTO tmp_lib_hp_policy_candidate (',
        '    policy_id, ',
        '    binding_node_id, ',
        '    precedence, ',
        '    binding_type, ',
        '    policy_attribute_value',
        ') ',

        'SELECT ',
        '    rnp.',
        qRNPPolicyIdField,
        ', ',
        '    a.node_id, ',
        '    a.depth + 1, ',
        '    2, ',
        '    rnp.',
        qPolicyAttributeOnScopeField,

        ' FROM ',
        fullRNPSource,
        ' rnp ',

        ' JOIN tmp_lib_hp_ancestor a ',
        '   ON a.node_id = rnp.',
        qRNPNodeIdField,

        ' JOIN tmp_lib_hp_policy_input pi ',
        '   ON pi.policy_id = rnp.',
        qRNPPolicyIdField
    );

    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    /*--------------------------------------------------------------------------------------------------------------
     * Pick effective binding.
     *
     * Mỗi policy_id chỉ được giữ binding có precedence nhỏ nhất.
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_effective_resource_policy;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_effective_resource_policy
    (
        policy_id BIGINT NOT NULL,

        binding_node_id BIGINT NULL,

        binding_type TINYINT NOT NULL,

        /*
         * Policy attribute lấy từ effective policy scope.
         */
        policy_attribute_value LONGTEXT NULL,

        PRIMARY KEY (policy_id)
    );

    /*
     * Nếu data có nhiều binding cùng:
     *
     *     policy_id
     *     precedence
     *
     * INSERT sẽ vi phạm PRIMARY KEY(policy_id).
     *
     * Đây là chủ ý:
     *
     *     không âm thầm chọn một record ngẫu nhiên,
     *     mà làm lộ lỗi integrity của DB data.
     */
    INSERT INTO
        tmp_lib_hp_effective_resource_policy
        (
            policy_id,
            binding_node_id,
            binding_type,
            policy_attribute_value
        )
    SELECT
        c.policy_id,
        c.binding_node_id,
        c.binding_type,
        c.policy_attribute_value

    FROM tmp_lib_hp_policy_candidate c

    JOIN (
        SELECT
            policy_id,
            MIN(precedence) AS min_precedence

        FROM tmp_lib_hp_policy_candidate

        GROUP BY policy_id
    ) m
      ON m.policy_id = c.policy_id
     AND m.min_precedence = c.precedence;

    /*--------------------------------------------------------------------------------------------------------------
     * Intermediate cleanup.
     *
     * Giữ lại:
     *
     *     tmp_lib_hp_effective_resource_policy
     *
     * cho lib_spHPGetResourcePolicies xử lý bước tiếp theo.
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_policy_input;

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_policy_candidate;

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_ancestor;

END ;;

DELIMITER ;