DROP PROCEDURE IF EXISTS lib_spHPBuildPolicyInput;

DELIMITER ;;

CREATE PROCEDURE lib_spHPBuildPolicyInput(
    IN jsonPolicyId JSON
)
MODIFIES SQL DATA
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE nPolicy INT DEFAULT 0;

    DECLARE strPolicyId TEXT;
    DECLARE validationMsg VARCHAR(255);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS
            tmp_lib_hp_policy_input;

        RESIGNAL;
    END;

    /*--------------------------------------------------------------------------------------------------------------
     * Validate input.
     *-------------------------------------------------------------------------------------------------------------*/
    IF
        jsonPolicyId IS NULL
        OR JSON_TYPE(jsonPolicyId) <> 'ARRAY'
        OR JSON_LENGTH(jsonPolicyId) = 0
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'jsonPolicyId phải là JSON array không rỗng';
    END IF;

    /*--------------------------------------------------------------------------------------------------------------
     * Temp table chuẩn hóa danh sách policy_id.
     *
     * PRIMARY KEY đồng thời:
     *
     *     - bảo đảm không trùng;
     *     - tối ưu JOIN với các policy binding.
     *-------------------------------------------------------------------------------------------------------------*/
    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_policy_input;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_policy_input
    (
        policy_id BIGINT NOT NULL,

        PRIMARY KEY (policy_id)
    );

    SET nPolicy =
        JSON_LENGTH(jsonPolicyId);

    SET i = 0;

    WHILE i < nPolicy DO

        SET strPolicyId =
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    jsonPolicyId,
                    CONCAT(
                        '$[',
                        i,
                        ']'
                    )
                )
            );

        IF strPolicyId IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'policy_id không được là null';
        END IF;

        /*
         * Tận dụng validator generic có sẵn.
         */
        SET validationMsg =
            lib_fnValidateDataTypeForSP(
                'policy_id',
                strPolicyId,
                'BIGINT'
            );

        IF validationMsg <> '' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = validationMsg;
        END IF;

        IF CAST(strPolicyId AS SIGNED) <= 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT =
                    'policy_id phải là số nguyên dương';
        END IF;

        /*
         * INSERT IGNORE:
         *
         *     [7, 8, 7, 12]
         *
         * =>
         *
         *     7
         *     8
         *     12
         */
        INSERT IGNORE INTO
            tmp_lib_hp_policy_input(
                policy_id
            )
        VALUES (
            CAST(strPolicyId AS SIGNED)
        );

        SET i = i + 1;
    END WHILE;
END ;;

DELIMITER ;