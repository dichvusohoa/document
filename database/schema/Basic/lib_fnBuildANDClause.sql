DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION `lib_fnBuildANDClause`(keySchema JSON,         -- ví dụ: [{"key_name":"user_id", "key_type":"INT"}, ...]
    keyValues JSON          -- ví dụ: {"user_id":1, "role_id":10}
) RETURNS text CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    DETERMINISTIC
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE numKeys INT DEFAULT 0;
    DECLARE keyName VARCHAR(64);
    DECLARE keyType VARCHAR(64);
    DECLARE keyVal TEXT;
    DECLARE validationMsg TEXT;
    DECLARE andClause TEXT DEFAULT '';

    SET numKeys = JSON_LENGTH(keySchema);
    IF numKeys = 0 THEN
        RETURN '';
    END IF;

    WHILE i < numKeys DO
        SET keyName = JSON_UNQUOTE(JSON_EXTRACT(keySchema, CONCAT('$[', i, '].key_name')));
        SET keyType = JSON_UNQUOTE(JSON_EXTRACT(keySchema, CONCAT('$[', i, '].key_type')));
        SET keyVal  = JSON_UNQUOTE(JSON_EXTRACT(keyValues, CONCAT('$.', keyName)));

        -- (chấp nhận NULL)
        IF keyVal IS NULL THEN
            SET andClause = CONCAT(andClause, IF(i > 0, ' AND ', ''),'`', keyName, '` IS NULL');
        ELSE
            -- Kiểm tra kiểu dữ liệu nếu không phải NULL
            SET validationMsg = lib_fnValidateDataTypeForSP(keyName, keyVal, keyType);
            IF validationMsg != '' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
            END IF;
            SET andClause = CONCAT(andClause, IF(i > 0, ' AND ', ''),'`', keyName, '` = ', lib_fnToSQLLiteral(keyVal,keyType));
        END IF;
        SET i = i + 1;
    END WHILE;

    RETURN andClause;
END ;;
DELIMITER ;