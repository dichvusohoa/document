-- MariaDB dump 10.19  Distrib 10.4.22-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: dichvuqu_document
-- ------------------------------------------------------
-- Server version	10.4.22-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping routines for database 'dichvuqu_document'
--
/*!50003 DROP FUNCTION IF EXISTS `lib_fnBuildANDClause` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `lib_fnBuildCaseWhenClauseForUpdateSP` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `lib_fnBuildWhereClauseFromEnum` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `lib_fnGetFullTableName` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION `lib_fnGetFullTableName`(dbName VARCHAR(64),
    tableName VARCHAR(64)
) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
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
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `lib_fnToSQLLiteral` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION `lib_fnToSQLLiteral`(val TEXT, fieldType VARCHAR(50)) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    DETERMINISTIC
BEGIN
    IF fieldType IN ('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT') 
        OR fieldType LIKE 'FLOAT%' OR fieldType LIKE 'DOUBLE%' THEN
    RETURN val;
    ELSEIF fieldType = 'BOOLEAN' THEN
        RETURN IF(val IN ('true', 'TRUE', '1'), '1', '0');
    ELSE
        RETURN QUOTE(val); -- chống sql injection
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `lib_fnValidateDataTypeForSP` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION `lib_fnValidateDataTypeForSP`(fieldName VARCHAR(100),val TEXT, expectedType VARCHAR(100)) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    DETERMINISTIC
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
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spAdd` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spCount` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spCount`(
    IN selectClause TEXT,
    IN jsonWhere JSON,  
    IN jsonHaving JSON,                     -- điều kiện cho HAVING
    IN groupByClause VARCHAR(255),          -- phần sau GROUP BY
    OUT `total` INT
)
    READS SQL DATA
BEGIN
    DECLARE whereType VARCHAR(25);
    DECLARE whereCondition TEXT;
    DECLARE whereKeyMeta JSON;

    DECLARE havingType VARCHAR(25);
    DECLARE havingCondition TEXT;
    DECLARE havingKeyMeta JSON;

    DECLARE whereClause TEXT DEFAULT '';
    DECLARE havingClause TEXT DEFAULT '';
    DECLARE groupClause TEXT DEFAULT '';
    DECLARE finalSQL TEXT;

    -- ====== WHERE ======
    SET whereType = JSON_UNQUOTE(JSON_EXTRACT(jsonWhere, '$.type'));
    SET whereCondition = JSON_EXTRACT(jsonWhere, '$.condition');
    SET whereKeyMeta = JSON_EXTRACT(jsonWhere, '$.keyMeta');

    IF whereType = 'enum' THEN
        SET whereClause = lib_fnBuildWhereClauseFromEnum(whereKeyMeta, whereCondition);
    ELSEIF whereType = 'logic' THEN
        SET whereClause = CONCAT(' WHERE ', JSON_UNQUOTE(whereCondition));
    ELSEIF whereType IS NULL OR whereType = 'none' THEN
        SET whereClause = '';
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị jsonWhere.type không hợp lệ';
    END IF;

    -- ====== GROUP BY ======
    IF groupByClause IS NOT NULL AND TRIM(groupByClause) != '' THEN
        SET groupClause = CONCAT(' GROUP BY ', groupByClause);
    END IF;

    -- ====== HAVING ======
    IF groupClause != '' THEN
        SET havingType = JSON_UNQUOTE(JSON_EXTRACT(jsonHaving, '$.type'));
        SET havingCondition = JSON_EXTRACT(jsonHaving, '$.condition');
        SET havingKeyMeta = JSON_EXTRACT(jsonHaving, '$.keyMeta');

        IF havingType = 'enum' THEN
            SET havingClause = lib_fnBuildWhereClauseFromEnum(havingKeyMeta, havingCondition);
            SET havingClause = REPLACE(havingClause, 'WHERE', 'HAVING');
        ELSEIF havingType = 'logic' THEN
            SET havingClause = CONCAT(' HAVING ', JSON_UNQUOTE(havingCondition));
        ELSEIF havingType = 'none' OR havingType IS NULL THEN
            SET havingClause = '';
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị jsonHaving.type không hợp lệ';
        END IF;
    END IF;
   
    -- ====== FINAL SQL ======
    SET @num := 0;
    SET finalSQL = CONCAT(
        'SELECT @num:=COUNT(*) FROM ( SELECT ', selectClause,
        whereClause,
        groupClause,
        havingClause,
        ' ) AS tmp'
    );

    -- DEBUG
    -- INSERT INTO debug_log(message) VALUES (finalSQL);

    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SET total = @num;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spDelete` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spExists` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spExists`(
    IN selectClause TEXT,
    IN jsonWhere JSON,  
    IN jsonHaving JSON,                     -- điều kiện cho HAVING
    IN groupByClause VARCHAR(255),          -- phần sau GROUP BY
    OUT `total` BOOLEAN
)
    READS SQL DATA
BEGIN
    DECLARE whereType VARCHAR(25);
    DECLARE whereCondition TEXT;
    DECLARE whereKeyMeta JSON;

    DECLARE havingType VARCHAR(25);
    DECLARE havingCondition TEXT;
    DECLARE havingKeyMeta JSON;

    DECLARE whereClause TEXT DEFAULT '';
    DECLARE havingClause TEXT DEFAULT '';
    DECLARE groupClause TEXT DEFAULT '';
    DECLARE finalSQL TEXT;

    -- ====== WHERE ======
    SET whereType = JSON_UNQUOTE(JSON_EXTRACT(jsonWhere, '$.type'));
    SET whereCondition = JSON_EXTRACT(jsonWhere, '$.condition');
    SET whereKeyMeta = JSON_EXTRACT(jsonWhere, '$.keyMeta');

    IF whereType = 'enum' THEN
        SET whereClause = lib_fnBuildWhereClauseFromEnum(whereKeyMeta, whereCondition);
    ELSEIF whereType = 'logic' THEN
        SET whereClause = CONCAT(' WHERE ', JSON_UNQUOTE(whereCondition));
    ELSEIF whereType IS NULL OR whereType = 'none' THEN
        SET whereClause = '';
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị jsonWhere.type không hợp lệ';
    END IF;

    -- ====== GROUP BY ======
    IF groupByClause IS NOT NULL AND TRIM(groupByClause) != '' THEN
        SET groupClause = CONCAT(' GROUP BY ', groupByClause);
    END IF;

    -- ====== HAVING ======
    IF groupClause != '' THEN
        SET havingType = JSON_UNQUOTE(JSON_EXTRACT(jsonHaving, '$.type'));
        SET havingCondition = JSON_EXTRACT(jsonHaving, '$.condition');
        SET havingKeyMeta = JSON_EXTRACT(jsonHaving, '$.keyMeta');

        IF havingType = 'enum' THEN
            SET havingClause = lib_fnBuildWhereClauseFromEnum(havingKeyMeta, havingCondition);
            SET havingClause = REPLACE(havingClause, 'WHERE', 'HAVING');
        ELSEIF havingType = 'logic' THEN
            SET havingClause = CONCAT(' HAVING ', JSON_UNQUOTE(havingCondition));
        ELSEIF havingType = 'none' OR havingType IS NULL THEN
            SET havingClause = '';
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị jsonHaving.type không hợp lệ';
        END IF;
    END IF;
   
    -- ====== FINAL SQL ======
    SET @num := 0;
    SET finalSQL = CONCAT(
        'SELECT @num := EXISTS(SELECT ', 
        selectClause,
        whereClause,
        groupClause,
        havingClause,
        ' )'
    );

    -- DEBUG
    -- INSERT INTO debug_log(message) VALUES (finalSQL);

    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SET total = @num;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spGetUserByName` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `lib_spGetUserByName`(IN name VARCHAR(125))
BEGIN
    WITH
    user_roles AS (
        SELECT
            ur.user_id,
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', r.code, '":',
                        JSON_QUOTE(IFNULL(r.display_name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS roles
        FROM user_role ur
        JOIN role r ON ur.role_id = r.id
        GROUP BY ur.user_id
    ),
    subscriber_modules AS (
        SELECT
            sm.subscriber_id,
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', m.id, '":',
                        JSON_QUOTE(IFNULL(m.name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS registered_modules
        FROM subscriber_module sm
        JOIN module m ON sm.module_id = m.id
        GROUP BY sm.subscriber_id
    ),

    all_modules AS (
        SELECT
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', id, '":',
                        JSON_QUOTE(IFNULL(name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS registered_modules
        FROM module
    )

    SELECT
        u.id,
        u.name,
        u.password,
        u.subscriber_id,
        ur.roles,
        CASE
            WHEN u.subscriber_id IS NULL THEN am.registered_modules
            ELSE sm.registered_modules
        END AS registered_modules
    FROM user u   
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN subscriber_modules sm ON u.subscriber_id = sm.subscriber_id
    LEFT JOIN all_modules am ON u.subscriber_id IS NULL;
    

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spGetUserByNameAndRole` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `lib_spGetUserByNameAndRole`(IN pName VARCHAR(25), IN pRole VARCHAR(25))
BEGIN
    WITH
    user_roles AS (
        SELECT
            ur.user_id,
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', r.code, '":',
                        JSON_QUOTE(IFNULL(r.display_name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS roles
        FROM user_role ur
        JOIN role r ON ur.role_id = r.id
        GROUP BY ur.user_id
    ),
    subscriber_modules AS (
        SELECT
            sm.subscriber_id,
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', m.id, '":',
                        JSON_QUOTE(IFNULL(m.name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS registered_modules
        FROM subscriber_module sm
        JOIN module m ON sm.module_id = m.id
        GROUP BY sm.subscriber_id
    ),

    all_modules AS (
        SELECT
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', id, '":',
                        JSON_QUOTE(IFNULL(name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS registered_modules
        FROM module
    )

    SELECT
        u.id,
        u.name,
        u.password,
        u.subscriber_id,
        ur.roles,
        CASE
            WHEN u.subscriber_id IS NULL THEN am.registered_modules
            ELSE sm.registered_modules
        END AS registered_modules
    FROM user u   
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN subscriber_modules sm ON u.subscriber_id = sm.subscriber_id
    LEFT JOIN all_modules am ON u.subscriber_id IS NULL
    WHERE
        u.name = pName
        AND (
            pRole IS NULL
            OR pRole = ''
            OR EXISTS (
                SELECT 1
                FROM user_role ur2
                JOIN role r2 ON ur2.role_id = r2.id
                WHERE ur2.user_id = u.id  AND r2.code = pRole
            )
        );

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spGetUserByToken` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `lib_spGetUserByToken`(IN leftToken VARCHAR(125))
BEGIN
    WITH
    user_roles AS (
        SELECT
            ur.user_id,
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', r.code, '":',
                        JSON_QUOTE(IFNULL(r.display_name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS roles
        FROM user_role ur
        JOIN role r ON ur.role_id = r.id
        GROUP BY ur.user_id
    ),
    subscriber_modules AS (
        SELECT
            sm.subscriber_id,
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', m.id, '":',
                        JSON_QUOTE(IFNULL(m.name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS registered_modules
        FROM subscriber_module sm
        JOIN module m ON sm.module_id = m.id
        GROUP BY sm.subscriber_id
    ),

    all_modules AS (
        SELECT
            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        '"', id, '":',
                        JSON_QUOTE(IFNULL(name, ''))
                    )
                    SEPARATOR ','
                ),
                '}'
            ) AS registered_modules
        FROM module
    )

    SELECT
        at.user_id AS id,
        u.name,
        u.subscriber_id,
        ur.roles,
        CASE
            WHEN u.subscriber_id IS NULL THEN am.registered_modules
            ELSE sm.registered_modules
        END AS registered_modules,
        at.hashed_validator
    FROM auth_token at
    JOIN user u ON at.user_id = u.id
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN subscriber_modules sm ON u.subscriber_id = sm.subscriber_id
    LEFT JOIN all_modules am ON u.subscriber_id IS NULL
    WHERE at.selector = leftToken
      AND at.exp_date > NOW();

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spSelect` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spSelect`(
    IN selectClause TEXT,
    IN jsonWhere JSON,  
    IN jsonHaving JSON,
    IN groupByClause VARCHAR(255),          -- phần sau GROUP BY
    IN orderByClause  VARCHAR(255),         -- phần sau ORDER BY
    IN pageIndex INT,                       -- bắt đầu từ 0
    IN pageSize INT
)
    READS SQL DATA
BEGIN
    DECLARE whereType VARCHAR(25);
    DECLARE whereCondition TEXT;
    DECLARE whereKeyMeta JSON;

    DECLARE havingType VARCHAR(25);
    DECLARE havingCondition TEXT;
    DECLARE havingKeyMeta JSON;

    DECLARE whereClause TEXT DEFAULT '';
    DECLARE havingClause TEXT DEFAULT '';
    DECLARE groupClause TEXT DEFAULT '';
    DECLARE orderClause TEXT DEFAULT '';
    DECLARE limitClause TEXT DEFAULT '';
    DECLARE finalSQL TEXT;

    -- ====== WHERE ======
    SET whereType = JSON_UNQUOTE(JSON_EXTRACT(jsonWhere, '$.type'));
    SET whereCondition = JSON_EXTRACT(jsonWhere, '$.condition');
    SET whereKeyMeta = JSON_EXTRACT(jsonWhere, '$.keyMeta');

    IF whereType = 'enum' THEN
        SET whereClause = lib_fnBuildWhereClauseFromEnum(whereKeyMeta, whereCondition);
    ELSEIF whereType = 'logic' THEN
        SET whereClause = CONCAT(' WHERE ', JSON_UNQUOTE(whereCondition));
    ELSEIF whereType IS NULL OR whereType = 'none' THEN
        SET whereClause = '';
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị jsonWhere.type không hợp lệ';
    END IF;

    -- ====== GROUP BY ======
    IF groupByClause IS NOT NULL AND TRIM(groupByClause) != '' THEN
        SET groupClause = CONCAT(' GROUP BY ', groupByClause);
    END IF;

    -- ====== HAVING ======
    IF groupClause != '' THEN
        SET havingType = JSON_UNQUOTE(JSON_EXTRACT(jsonHaving, '$.type'));
        SET havingCondition = JSON_EXTRACT(jsonHaving, '$.condition');
        SET havingKeyMeta = JSON_EXTRACT(jsonHaving, '$.keyMeta');

        IF havingType = 'enum' THEN
            SET havingClause = lib_fnBuildWhereClauseFromEnum(havingKeyMeta, havingCondition);
            SET havingClause = REPLACE(havingClause, 'WHERE', 'HAVING');
        ELSEIF havingType = 'logic' THEN
            SET havingClause = CONCAT(' HAVING ', JSON_UNQUOTE(havingCondition));
        ELSEIF havingType = 'none' OR havingType IS NULL THEN
            SET havingClause = '';
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá trị jsonHaving.type không hợp lệ';
        END IF;
    END IF;

    -- ====== ORDER BY ======
    IF orderByClause IS NOT NULL AND TRIM(orderByClause) != '' THEN
        SET orderClause = CONCAT(' ORDER BY ', orderByClause);
    END IF;

    -- ====== LIMIT ======
    IF pageSize > 0 THEN
        SET limitClause = CONCAT(' LIMIT ', pageIndex * pageSize, ', ', pageSize);
    END IF;

    -- ====== FINAL SQL ======
    SET finalSQL = CONCAT(
        'SELECT ', selectClause,
        whereClause,
        groupClause,
        havingClause,
        orderClause,
        limitClause
    );

    -- DEBUG
    INSERT INTO debug_log(message) VALUES (finalSQL);

    -- EXECUTE
    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spUpdate` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spUpdate`(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonKeyMeta JSON,                    -- schema định nghĩa key field
    IN jsonUpdateMeta JSON,                 -- schema định nghĩa các field cập nhật
    IN jsonUpdatedRecords JSON,             -- bản ghi thực tế cần update
    IN isUpdateAllFields BOOLEAN,           -- true: update toàn bộ, false: theo từng field
    IN whereConditionType VARCHAR(25),      -- enum | logic | none
    IN whereCondition JSON,                 -- điều kiện WHERE nếu cần
    OUT totalUpdate INT
)
    MODIFIES SQL DATA
BEGIN
    IF isUpdateAllFields THEN
        CALL lib_spUpdateAllFields(
            dbName,
            tableName,
            jsonKeyMeta,
            jsonUpdateMeta,
            jsonUpdatedRecords,
            whereConditionType,
            whereCondition,
            totalUpdate
        );
    ELSE
        CALL lib_spUpdateByFieldCase(
            dbName,
            tableName,
            jsonKeyMeta,
            jsonUpdateMeta,
            jsonUpdatedRecords,
            whereConditionType,
            whereCondition,
            totalUpdate
        );
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spUpdateAllFields` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `lib_spUpdateByFieldCase` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-28 17:37:24
