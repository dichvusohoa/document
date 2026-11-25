DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spUpdateByFieldCase`(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonKeyMeta JSON,
    IN jsonUpdateMeta JSON,
    IN jsonUpdatedRecords JSON,
    IN whereConditionType VARCHAR(25),
    IN whereCondition JSON,
    OUT totalUpdated INT
)
    MODIFIES SQL DATA
proc_end: BEGIN
    -- ===== Khai báo biến =====
    DECLARE fullTableName VARCHAR(255);
    DECLARE nFields INT DEFAULT 0;
    DECLARE i INT DEFAULT 0;
    DECLARE fieldName VARCHAR(64);
    DECLARE fieldType VARCHAR(50);
    DECLARE jsonUpdatedEachField JSON;
    DECLARE updateClause TEXT DEFAULT '';
    DECLARE whereClause TEXT DEFAULT '';
    DECLARE finalSQL TEXT;
    -- Chuẩn hóa tên table
    SET fullTableName = lib_fnGetFullTableName(dbName, tableName);
    IF JSON_EXTRACT(jsonUpdateMeta, '$.field_name') IS NOT NULL THEN
        SET jsonUpdateMeta = CONCAT('[', jsonUpdateMeta, ']');
    END IF;
    SET nFields = JSON_LENGTH(jsonUpdateMeta);
    IF nFields = 0  THEN
        SET totalUpdated = 0;
        LEAVE proc_end;
    END IF;
    SET i = 0;
    WHILE i < nFields DO
        SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonUpdateMeta, CONCAT('$[', i, '].field_name')));
        SET fieldType = JSON_UNQUOTE(JSON_EXTRACT(jsonUpdateMeta, CONCAT('$[', i, '].field_type')));
        SET jsonUpdatedEachField = JSON_EXTRACT(jsonUpdatedRecords, CONCAT('$.', fieldName));
        -- Gọi hàm tạo biểu thức CASE WHEN cho field này
        SET updateClause = CONCAT(
            updateClause,
            IF(i > 0, ', ', ''),
            lib_fnBuildCaseWhenClauseForUpdateSP(fieldName, fieldType,jsonKeyMeta,jsonUpdatedEachField)
        );
        SET i = i + 1;
    END WHILE;
    -- Tạo WHERE
    IF whereConditionType = 'enum' THEN
        SET whereClause = lib_fnBuildWhereClauseFromEnum(jsonKeyMeta, whereCondition);
    ELSEIF whereConditionType = 'logic' THEN
        SET whereClause = CONCAT(' WHERE ', JSON_UNQUOTE(whereCondition));
    ELSEIF whereConditionType = 'none' THEN
        SET whereClause = '';
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị whereConditionType không hợp lệ';
    END IF;

    -- Ghép câu lệnh SQL
    SET finalSQL = CONCAT('UPDATE ', fullTableName, ' SET ', updateClause, whereClause);
    
    -- DEBUG
    -- INSERT INTO debug_log(message) VALUES (finalSQL);
    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    SET totalUpdated = ROW_COUNT();
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;