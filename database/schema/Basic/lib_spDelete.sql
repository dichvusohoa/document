DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spDelete`(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonFields JSON,
    IN jsonRecords JSON,
    OUT totalDeleted INT
)
    MODIFIES SQL DATA
proc_end: BEGIN  -- Đặt nhãn ở đây
    DECLARE i, j INT DEFAULT 0;
    DECLARE nRecords, nFields INT;
    DECLARE fieldName VARCHAR(64);
    DECLARE fieldType VARCHAR(50);
    DECLARE val TEXT;
    DECLARE validationMsg VARCHAR(255);
    DECLARE fieldList TEXT DEFAULT '';
    DECLARE valueList TEXT DEFAULT '';
    DECLARE rowVals TEXT DEFAULT '';
    DECLARE fullTableName VARCHAR(255);
    DECLARE finalSQL TEXT;

    SET fullTableName = lib_fnGetFullTableName(dbName, tableName);
    -- Nếu jsonFields có dạng như là '{"field_name":"id","field_type":"INT"}' thì phải chuyển
    -- về dạng array '[{"field_name":"id","field_type":"INT"}]'
    IF JSON_EXTRACT(jsonFields,'$.field_name') IS NOT NULL THEN
        SET jsonFields = CONCAT('[', jsonFields, ']');
        -- INSERT INTO debug_log(message) VALUES ("vao day 1");
    END IF;
    SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonFields,'$[0].field_name'));
    -- Nếu jsonRecords là object đơn → chuyển thành array
    -- ví dụ {"user_id":1} -> chuyển thành  [{"user_id":1}]
    IF JSON_EXTRACT(jsonRecords,CONCAT('$.',fieldName)) IS NOT NULL THEN
        SET jsonRecords = CONCAT('[', jsonRecords, ']');
        -- INSERT INTO debug_log(message) VALUES ("vao day 2");
    END IF;
 
    SET nRecords = JSON_LENGTH(jsonRecords);
    SET nFields = JSON_LENGTH(jsonFields);
    
    IF nRecords = 0 OR nFields = 0 THEN
        SET totalDeleted = 0;
        LEAVE proc_end;  
    END IF;

    SET j = 0;
    WHILE j < nFields DO
        SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonFields, CONCAT('$[', j, '].field_name')));
        SET fieldList = CONCAT(fieldList, IF(j > 0, ', ', ''), '`', fieldName, '`');
        SET j = j + 1;
    END WHILE;
    SET i = 0;
    WHILE i < nRecords DO
        SET rowVals = '';
        SET j = 0;

        WHILE j < nFields DO
            SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonFields, CONCAT('$[', j, '].field_name')));
            SET fieldType = JSON_UNQUOTE(JSON_EXTRACT(jsonFields, CONCAT('$[', j, '].field_type')));
            SET val = JSON_UNQUOTE(JSON_EXTRACT(jsonRecords, CONCAT('$[', i, '].', fieldName)));
            SET validationMsg = lib_fnValidateDataTypeForSP(fieldName, val, fieldType);
            IF validationMsg != '' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
            END IF;
            SET rowVals = CONCAT(rowVals, IF(j > 0, ', ', ''), IF(val IS NULL, 'NULL', QUOTE(val)));
            SET j = j + 1;
        END WHILE;

        SET valueList = CONCAT(valueList, IF(i > 0, ', ', ''), '(', rowVals, ')');
        SET i = i + 1;
    END WHILE;
    
    SET finalSQL = CONCAT('DELETE FROM ', fullTableName, ' WHERE (', fieldList, ') IN (', valueList, ')');
    -- INSERT INTO debug_log(message) VALUES (finalSQL);
    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    SET totalDeleted = ROW_COUNT();
    DEALLOCATE PREPARE stmt;

END ;;
DELIMITER ;