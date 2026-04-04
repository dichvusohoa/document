/*
  Hàm lib_fnValidateDataTypeForSP:
  - Mục đích: Kiểm tra kiểu dữ liệu giá trị truyền vào stored procedure có hợp lệ hay không
  - Tham số:
      + fieldName: tên trường cần kiểm tra
      + val: giá trị cần kiểm tra
      + expectedType: kiểu dữ liệu mong muốn (ví dụ: INT, VARCHAR(100), DECIMAL(10,2))
  - Trả về: Chuỗi lỗi nếu không hợp lệ, chuỗi rỗng nếu hợp lệ
  - Lưu ý: Hàm không kiểm tra ràng buộc nghiệp vụ như bắt buộc nhập
*/
DROP FUNCTION IF EXISTS lib_fnValidateDataTypeForSP;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION lib_fnValidateDataTypeForSP(fieldName VARCHAR(100),val TEXT, expectedType VARCHAR(100))
RETURNS VARCHAR(255)
CONTAINS SQL -- có chứa câu lệnh SQL (SET, IF,...) nhưng không truy cập dữ liệu bảng,
DETERMINISTIC -- tận dụng cache
BEGIN
    DECLARE varchar_len INT DEFAULT 0;
    DECLARE decimal_len_total INT DEFAULT 0;
    DECLARE decimal_len_frac INT DEFAULT 0;
    DECLARE errMsg VARCHAR(255) DEFAULT '';
	
	-- Chuẩn hóa expectedType về chữ in hoa
    SET expectedType = UPPER(expectedType);
    -- NULL => không lỗi
    IF val IS NULL THEN
        RETURN '';
    END IF;

    -- INT, TINYINT, SMALLINT, MEDIUMINT, BIGINT
    IF expectedType IN ('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT') THEN
        IF val = '' OR val NOT REGEXP '^-?[0-9]+$' THEN
            SET errMsg = CONCAT('Field ', fieldName, ' phải là số nguyên kiểu ', expectedType);
        END IF;
    -- FLOAT
    ELSEIF expectedType IN ('FLOAT', 'DOUBLE') THEN
        IF val = '' OR val NOT REGEXP '^[-+]?[0-9]*\\.?[0-9]+$' THEN
            SET errMsg = CONCAT('Field ', fieldName, ' phải là số thực kiểu ', expectedType);
        END IF;
    -- DECIMAL(p,s)
    ELSEIF expectedType LIKE 'DECIMAL%' THEN
        SET decimal_len_total = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(expectedType, '(', -1), ',', 1) AS UNSIGNED);
        SET decimal_len_frac = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(expectedType, ',', -1), ')', 1) AS UNSIGNED);
        IF val = '' OR val NOT REGEXP '^[-+]?[0-9]+(\\.[0-9]+)?$' THEN
            SET errMsg = CONCAT('Field ', fieldName, ' phải là số thập phân');
        ELSEIF LENGTH(REPLACE(val, '.', '')) > decimal_len_total THEN
            SET errMsg = CONCAT('Field ', fieldName, ' vượt quá tổng độ dài ', decimal_len_total);
        ELSEIF LOCATE('.', val) > 0 AND LENGTH(SUBSTRING_INDEX(val, '.', -1)) > decimal_len_frac THEN
            SET errMsg = CONCAT('Field ', fieldName, ' vượt quá phần thập phân ', decimal_len_frac);
        END IF;

    -- VARCHAR(n)
    ELSEIF expectedType LIKE 'VARCHAR%' THEN
        SET varchar_len = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(expectedType, '(', -1), ')', 1) AS UNSIGNED);
        IF CHAR_LENGTH(val) > varchar_len THEN
            SET errMsg = CONCAT('Field ', fieldName, ' vượt quá độ dài ', varchar_len);
        END IF;

    -- CHAR(n)
    ELSEIF expectedType LIKE 'CHAR%' THEN
        SET varchar_len = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(expectedType, '(', -1), ')', 1) AS UNSIGNED);
        IF CHAR_LENGTH(val) != varchar_len THEN
            SET errMsg = CONCAT('Field ', fieldName, ' phải có đúng ', varchar_len, ' ký tự');
        END IF;

    -- TEXT
    ELSEIF expectedType = 'TEXT' THEN
        IF val = '' THEN
            SET errMsg = CONCAT('Field ', fieldName, ' không được để trống');
        END IF;

    -- DATE
    ELSEIF expectedType = 'DATE' THEN
        IF STR_TO_DATE(val, '%Y-%m-%d') IS NULL THEN
            SET errMsg = CONCAT('Field ', fieldName, ' không phải là ngày hợp lệ');
        END IF;

    -- DATETIME
    ELSEIF expectedType = 'DATETIME' THEN
        IF STR_TO_DATE(val, '%Y-%m-%d %H:%i:%s') IS NULL THEN
            SET errMsg = CONCAT('Field ', fieldName, ' không phải là DATETIME hợp lệ');
        END IF;

    -- TIME
    ELSEIF expectedType = 'TIME' THEN
        IF STR_TO_DATE(val, '%H:%i:%s') IS NULL THEN
            SET errMsg = CONCAT('Field ', fieldName, ' không phải là thời gian hợp lệ');
        END IF;

    -- YEAR
    ELSEIF expectedType = 'YEAR' THEN
        IF val = '' OR val NOT REGEXP '^[0-9]{4}$' THEN
            SET errMsg = CONCAT('Field ', fieldName, ' phải là năm 4 chữ số');
        END IF;

    -- BOOLEAN (TINYINT(1))
    ELSEIF expectedType = 'BOOLEAN' THEN
        IF val NOT IN ('0', '1', 'true', 'false', 'TRUE', 'FALSE') THEN
            SET errMsg = CONCAT('Field ', fieldName, ' phải là Boolean (0/1 hoặc true/false)');
        END IF;

    ELSE
        SET errMsg = CONCAT('Không hỗ trợ kiểu dữ liệu: ', expectedType);
    END IF;

    RETURN errMsg;
END$$

DELIMITER ;
/*===================*/
/*
    lib_fnToSQLLiteral nhằm để chuyển một giá trị sang định dạng phù hợp để đưa vào câu lệnh SQL
    lib_fnToSQLLiteral thường được gọi sau khi đã gọi lib_fnValidateDataTypeForSP để đảm bảo là value
    được format đã hợp lệ
*/
DROP FUNCTION IF EXISTS lib_fnToSQLLiteral;
DELIMITER $$
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION lib_fnToSQLLiteral(val TEXT, fieldType VARCHAR(50))
RETURNS VARCHAR(255)
CONTAINS SQL -- có chứa câu lệnh SQL (SET, IF,...) nhưng không truy cập dữ liệu bảng,
DETERMINISTIC -- tận dụng cache
BEGIN
    IF fieldType IN ('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT') 
        OR fieldType LIKE 'FLOAT%' OR fieldType LIKE 'DOUBLE%' THEN
    RETURN val;
    ELSEIF fieldType = 'BOOLEAN' THEN
        RETURN IF(val IN ('true', 'TRUE', '1'), '1', '0');
    ELSE
        RETURN QUOTE(val); -- chống sql injection
    END IF;
END$$

DELIMITER ;
/*===================*/
-- Hàm xây dựng tên bảng đầy đủ có bao dấu ``, kèm kiểm tra định dạng hợp lệ.
DROP FUNCTION IF EXISTS lib_fnGetFullTableName;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION lib_fnGetFullTableName(
    dbName VARCHAR(64),
    tableName VARCHAR(64)
)
RETURNS VARCHAR(255)
DETERMINISTIC
BEGIN
    DECLARE result VARCHAR(255);

    -- Kiểm tra tableName hợp lệ
    IF tableName IS NULL OR tableName = '' OR tableName NOT REGEXP '^[a-zA-Z0-9_]+$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên bảng không hợp lệ';
    END IF;

    -- Kiểm tra dbName (nếu có)
    IF dbName IS NOT NULL AND dbName != '' AND dbName NOT REGEXP '^[a-zA-Z0-9_]+$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên database không hợp lệ';
    END IF;

    -- Tạo kết quả
    IF dbName IS NULL OR dbName = '' THEN
        SET result = CONCAT('`', tableName, '`');
    ELSE
        SET result = CONCAT('`', dbName, '`.`', tableName, '`');
    END IF;

    RETURN result;
END$$

DELIMITER ;
/*===================*/
/*Câu lệnh lib_spDelete tổng quát
dbName: tên Db. dbName IS NULL hoặc '' tức là db hiện tại
tableName: table name

Ví dụ
CALL lib_spDelete(
  NULL, 
  'user_role', 
  '[{"field_name":"user_id","field_type":"INT"},{"field_name":"role_id","field_type":"INT"}]',
  '[{"user_id":1,"role_id":10},{"user_id":1,"role_id":11}]',
  @deletedCount
);
*/
DROP PROCEDURE IF EXISTS lib_spDelete;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE lib_spDelete(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonFields JSON,
    IN jsonRecords JSON,
    OUT total INT
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
        SET total = 0;
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
    SET total = ROW_COUNT();
    DEALLOCATE PREPARE stmt;

END$$
DELIMITER ;
/*==========================*/
/* Câu lệnh lib_spAdd tổng quát
dbName: tên Db. dbName IS NULL hoặc '' tức là db hiện tại
tableName: tên bảng cần thêm dữ liệu
jsonFields: JSON dạng mảng chứa field_name và field_type
jsonFields có format dạng array
[{"field_name":xx, "field_type":yy, "default": zz},{...}] 
, trong đó "default" thì có thể có hoặc không
jsonRecords: JSON dạng mảng hoặc object chứa bản ghi
Ví dụ:
CALL lib_spAdd(
  NULL,
  'user_role',
  '[{"field_name":"user_id","field_type":"INT"},{"field_name":"role_id","field_type":"INT"}]',
  '[{"user_id":1,"role_id":10},{"user_id":1,"role_id":11}]',
  @insertedCount
);

CALL lib_spAdd(
  NULL,
  'user_role',
  '[{"field_name":"user_id","field_type":"INT"},{"field_name":"role_id","field_type":"INT","default":100}]',
  '[{"user_id":1},{"user_id":1,"role_id":11}]',
  @insertedCount
);
*/

DROP PROCEDURE IF EXISTS lib_spAdd;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE lib_spAdd(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonFields JSON,
    IN jsonRecords JSON,
    OUT total INT
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
        SET total = 0;
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
    SET total = ROW_COUNT();
    DEALLOCATE PREPARE stmt;
END$$

DELIMITER ;

/*==========================*/
