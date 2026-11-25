DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spUpdateAllFields`(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonKeyMeta JSON,                    -- schema định nghĩa key field, jsonKeyMeta dùng khi whereConditionType là enum
    IN jsonUpdateMeta JSON,                 -- schema định nghĩa các field cập nhật
    IN jsonUpdatedRecords JSON,             -- bản ghi thực tế cần update, dạng bản ghi đơn duy nhất
    IN whereConditionType VARCHAR(25),      -- enum | logic | none
    IN whereCondition JSON,                 -- điều kiện WHERE nếu cần
    OUT totalUpdated INT
)
    MODIFIES SQL DATA
proc_end: BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE nFields INT DEFAULT 0;
    DECLARE fieldName VARCHAR(64);
    DECLARE fieldType VARCHAR(50);
    DECLARE val TEXT;
    DECLARE fullTableName VARCHAR(255);
    DECLARE setClause TEXT DEFAULT '';
    DECLARE whereClause TEXT DEFAULT '';
    DECLARE validationMsg VARCHAR(255);
    DECLARE finalSQL TEXT;
    -- Validate dbName và tableName
    SET fullTableName = lib_fnGetFullTableName(dbName, tableName);
    -- Chuẩn hóa jsonUpdateMeta thành dạng mảng nếu là object đơn
    IF JSON_EXTRACT(jsonUpdateMeta, '$.field_name') IS NOT NULL THEN
        SET jsonUpdateMeta = CONCAT('[', jsonUpdateMeta, ']');
    END IF;
    
    -- Duyệt từng field cần update
    SET nFields = JSON_LENGTH(jsonUpdateMeta);
    IF nFields = 0  THEN
        SET totalUpdated = 0;
        LEAVE proc_end;
    END IF;
    WHILE i < nFields DO
        SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonUpdateMeta, CONCAT('$[', i, '].field_name')));
        SET fieldType = JSON_UNQUOTE(JSON_EXTRACT(jsonUpdateMeta, CONCAT('$[', i, '].field_type')));
        SET val = JSON_UNQUOTE(JSON_EXTRACT(jsonUpdatedRecords, CONCAT('$.', fieldName))); -- jsonUpdatedRecords chỉ là 1 record đơn

        -- Validate kiểu dữ liệu
        SET validationMsg = lib_fnValidateDataTypeForSP(fieldName, val, fieldType); 
        IF validationMsg != '' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
        END IF;

        -- Build SET clause
        SET setClause = CONCAT(setClause, IF(i > 0, ', ', ''), '`', fieldName, '` = ', IF(val IS NULL, 'NULL', lib_fnToSQLLiteral(val,fieldType)));
        SET i = i + 1;
    END WHILE;
    -- WHERE clause
    IF whereConditionType = 'enum' THEN
        -- kiểu: [{"food_id": 10}, {"food_id": 11}]
        SET whereClause = lib_fnBuildWhereClauseFromEnum(jsonKeyMeta, whereCondition);
    ELSEIF whereConditionType = 'logic' THEN
        SET whereClause = CONCAT(' WHERE ', JSON_UNQUOTE(whereCondition));
    ELSEIF whereConditionType = 'none' THEN
        SET whereClause = '';
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị whereConditionType không hợp lệ';
    END IF;
    -- Ghép câu lệnh
    SET finalSQL = CONCAT('UPDATE ', fullTableName, ' SET ', setClause, whereClause);
    -- DEBUG
    -- INSERT INTO debug_log(message) VALUES (finalSQL);
    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    SET totalUpdated = ROW_COUNT();
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;