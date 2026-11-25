DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spAdd`(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonFields JSON,
    IN jsonRecords JSON,
    OUT totalInserted INT
)
    MODIFIES SQL DATA
proc_end: BEGIN
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
    DECLARE path VARCHAR(255);
    DECLARE path1 VARCHAR(255);
    DECLARE finalSQL TEXT;

    -- Validate dbName và tableName
    SET fullTableName = lib_fnGetFullTableName(dbName, tableName);
    -- Chuẩn hóa jsonFields thành dạng mảng nếu là object đơn
    IF JSON_EXTRACT(jsonFields, '$.field_name') IS NOT NULL THEN
        SET jsonFields = CONCAT('[', jsonFields, ']');
    END IF;
    
    SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonFields,'$[0].field_name'));

    -- Chuẩn hóa jsonRecords nếu là object đơn
    IF JSON_EXTRACT(jsonRecords, CONCAT('$.', fieldName)) IS NOT NULL THEN
        SET jsonRecords = CONCAT('[', jsonRecords, ']');
    END IF;

    SET nRecords = JSON_LENGTH(jsonRecords);
    SET nFields = JSON_LENGTH(jsonFields);

    IF nRecords = 0 OR nFields = 0 THEN
        SET totalInserted = 0;
        LEAVE proc_end;
    END IF;

    -- Lấy danh sách field
    SET j = 0;
    WHILE j < nFields DO
        SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonFields, CONCAT('$[', j, '].field_name')));
        SET fieldList = CONCAT(fieldList, IF(j > 0, ', ', ''), '`', fieldName, '`');
        SET j = j + 1;
    END WHILE;

    -- Lấy danh sách value
    SET i = 0;
    WHILE i < nRecords DO
        SET rowVals = '';
        SET j = 0;
        WHILE j < nFields DO
            SET fieldName = JSON_UNQUOTE(JSON_EXTRACT(jsonFields, CONCAT('$[', j, '].field_name')));
            SET fieldType = JSON_UNQUOTE(JSON_EXTRACT(jsonFields, CONCAT('$[', j, '].field_type')));
            SET path = CONCAT('$[', i, '].', fieldName);
            SET path1 = CONCAT('$[', j, '].default');

            IF JSON_CONTAINS_PATH(jsonRecords, 'one', path) THEN
                SET val = JSON_UNQUOTE(JSON_EXTRACT(jsonRecords, path));
            ELSEIF JSON_CONTAINS_PATH(jsonFields, 'one', path1) THEN
                SET val = JSON_UNQUOTE(JSON_EXTRACT(jsonFields, path1));
            ELSE
                SET validationMsg = CONCAT('Thiếu giá trị bắt buộc cho field "', fieldName, '" tại record #', i);
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
            END IF;
            IF val = 'null' OR val = 'NULL' THEN
                SET val = null;
                -- INSERT INTO debug_log(message) VALUES ("chạy vào đây rồi");
            END IF;
            -- Kiểm tra kiểu dữ liệu
            SET validationMsg = lib_fnValidateDataTypeForSP(fieldName, val, fieldType);
            IF validationMsg != '' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = validationMsg;
            END IF;

            SET rowVals = CONCAT(rowVals, IF(j > 0, ', ', ''), IF(val IS NULL, 'NULL', lib_fnToSQLLiteral(val,fieldType)));
            SET j = j + 1;
        END WHILE;

        SET valueList = CONCAT(valueList, IF(i > 0, ', ', ''), '(', rowVals, ')');
        SET i = i + 1;
    END WHILE;

    -- Tạo câu INSERT
    SET finalSQL = CONCAT('INSERT INTO ', fullTableName, ' (', fieldList, ') VALUES ', valueList);
    -- INSERT INTO debug_log(message) VALUES (finalSQL);

    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    SET totalInserted = ROW_COUNT();
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;