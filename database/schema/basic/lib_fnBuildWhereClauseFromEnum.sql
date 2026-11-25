CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION `lib_fnBuildWhereClauseFromEnum`(jsonKeyMeta JSON,          -- ví dụ: [{"key_name":"food_id","key_type":"INT"}, ...]
    jsonCondition JSON -- ví dụ: [{"food_id": 10}, {"food_id": 11}]
) RETURNS text CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    DETERMINISTIC
BEGIN
    DECLARE i, j INT DEFAULT 0;
    DECLARE numKeys, numRows INT;
    DECLARE keyName VARCHAR(64);
    DECLARE keyType VARCHAR(64);
    DECLARE keyVal TEXT;
    DECLARE valGroup TEXT DEFAULT '';
    DECLARE keyList TEXT DEFAULT '';
    DECLARE valueList TEXT DEFAULT '';
    DECLARE clause TEXT DEFAULT '';
    DECLARE validationMsg VARCHAR(255);

    IF JSON_EXTRACT(jsonKeyMeta, '$.key_name') IS NOT NULL THEN
        SET jsonKeyMeta = CONCAT('[', jsonKeyMeta, ']');
    END IF;
    SET numKeys = JSON_LENGTH(jsonKeyMeta);
    IF numKeys = 0 THEN
        RETURN "";
    END IF;
    SET keyName = JSON_UNQUOTE(JSON_EXTRACT(jsonKeyMeta,'$[0].key_name'));
    IF JSON_EXTRACT(jsonCondition, CONCAT('$.', keyName)) IS NOT NULL THEN
        SET jsonCondition = CONCAT('[', jsonCondition, ']');
    END IF;
    
    SET numRows = JSON_LENGTH(jsonCondition);
    IF numRows = 0 THEN
        RETURN "";
    END IF;
    -- Tạo danh sách key
    WHILE i < numKeys DO
        SET keyName = JSON_UNQUOTE(JSON_EXTRACT(jsonKeyMeta, CONCAT('$[', i, '].key_name')));
        SET keyList = CONCAT(keyList, IF(i > 0, ',', ''), '`', keyName, '`');
        SET i = i + 1;
    END WHILE;
    -- Reset lại biến lặp cho dữ liệu điều kiện
    SET i = 0;
    WHILE i < numRows DO
        SET valGroup = '';
        SET j = 0;

        WHILE j < numKeys DO
            SET keyName = JSON_UNQUOTE(JSON_EXTRACT(jsonKeyMeta, CONCAT('$[', j, '].key_name')));
            SET keyType = JSON_UNQUOTE(JSON_EXTRACT(jsonKeyMeta, CONCAT('$[', j, '].key_type')));
            SET keyVal  = JSON_UNQUOTE(JSON_EXTRACT(jsonCondition, CONCAT('$[', i, '].', keyName)));
            IF keyVal IS NULL THEN
                SET validationMsg = CONCAT('Giá trị ',keyName,' không được là NULL');
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
            END IF;
            -- Kiểm tra kiểu dữ liệu
            SET validationMsg = lib_fnValidateDataTypeForSP(keyName, keyVal, keyType);
            IF validationMsg != '' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
            END IF;
            SET valGroup = CONCAT(valGroup, IF(j > 0, ',', ''), lib_fnToSQLLiteral(keyVal,keyType));
            SET j = j + 1;
        END WHILE;

        SET valueList = CONCAT(valueList, IF(i > 0, ',', ''), '(', valGroup, ')');
        SET i = i + 1;
    END WHILE;
    -- Gộp lại thành mệnh đề WHERE
    IF valueList != '' THEN
        SET clause = CONCAT(' WHERE (', keyList, ') IN (', valueList, ')');
    END IF;
    -- INSERT INTO debug_log(message) VALUES (clause); 
    RETURN clause;
END ;;
DELIMITER ;