DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION `lib_fnBuildCaseWhenClauseForUpdateSP`(fieldName VARCHAR(64), 
    fieldType VARCHAR(50),
    jsonKeyMeta JSON,
    jsonUpdatedEachField JSON
) RETURNS text CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    DETERMINISTIC
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE numRecords INT;
    DECLARE numKeys INT;
    DECLARE jsonEachVal JSON;
    DECLARE val TEXT;
    DECLARE jsonCondition JSON;
    DECLARE conditionClause TEXT;
    DECLARE caseWhenClause TEXT DEFAULT '';
    DECLARE validationMsg TEXT;
    SET numKeys = JSON_LENGTH(jsonKeyMeta);
    SET numRecords = JSON_LENGTH(jsonUpdatedEachField);
    IF numKeys = 0 OR numRecords = 0 THEN
        RETURN '';
    END IF;
    WHILE i < numRecords DO
        -- tới từng value của một field. trả về example {"mô tả 1": {"user_id": 1, "role_id": 10}}
        SET jsonEachVal = JSON_EXTRACT(jsonUpdatedEachField, CONCAT('$[',i,']')); 
        -- JSON_KEYS(@jsonEachVal) trả về array dạng '["mô tả 1"]'
        -- chú ý là do json không cho phép dùng null làm key nên nếu val is null thì phải viết là "null"
        SET val = JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(jsonEachVal), '$[0]')); -- lấy được giá trị, ví dụ '"mô tả 1"'
        SET val = TRIM(BOTH '"' FROM val); -- chuyển '"mô tả 1"' => 'mô tả 1'
        IF val = 'null' OR val = 'NULL' THEN
            SET val = null;
        END IF;
        SET validationMsg = lib_fnValidateDataTypeForSP(fieldName, val, fieldType); 
        IF validationMsg != '' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
        END IF;
        SET jsonCondition  = JSON_EXTRACT(jsonEachVal, CONCAT('$.',IFNULL(val, 'null'))); -- trả về ví dụ {"user_id": 1, "role_id": 10}
        -- Xây dựng biểu thức điều kiện
        SET conditionClause = lib_fnBuildANDClause(jsonKeyMeta, jsonCondition); -- trả về ví dụ `user_id` = '1' AND `role_id` = 10

        -- Gộp CASE WHEN
        -- trở thành như: WHEN `user_id` = '1' AND `role_id` = 10 THEN "mô tả 1"
        SET caseWhenClause = CONCAT(
            caseWhenClause,
            ' WHEN (', conditionClause, ') THEN ', IF(val IS NULL, 'NULL', lib_fnToSQLLiteral(val,fieldType))
        );
        SET i = i + 1;
    END WHILE;
    -- Gộp biểu thức CASE đầy đủ
    -- INSERT INTO debug_log(message) VALUES (CONCAT('`', fieldName, '` = CASE', caseWhenClause, ' ELSE `', fieldName, '` END'));
    RETURN CONCAT('`', fieldName, '` = CASE', caseWhenClause, ' ELSE `', fieldName, '` END');
END ;;
DELIMITER ;