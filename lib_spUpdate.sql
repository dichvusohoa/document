/*
jsonKeyMeta : '[{"key_name": giá trị,"key_type": giá trị}, {...}]' : định nghĩa các key field
jsonUpdateMeta : '[{"field_name": giá trị,"field_type": giá trị}, {...}]' định nghĩa các field giá trị bị update
isUpdateAllFields BOOLEAN: isUpdatedAllFields = true, update tất cả các field một lượt
tương đương câu lệnh: 
    UPDATE table SET field_1 = val_1,.... WHERE ....
isUpdateAllFields = false, update theo từng field 1, tương đương câu lệnh
    UPDATE bảng X
    SET 
    field1 = CASE 
        WHEN tập hợp 1 THEN giá trị 1
        WHEN tập hợp 2 THEN giá trị 2
        ELSE field1
    END,
    field2 = CASE 
        WHEN tập hợp 1 THEN giá trị 1
        WHEN tập hợp 2 THEN giá trị 2
        ELSE field2
    END,

    .....
    WHERE tập hợp các row ảnh hưởng
    
    jsonUpdatedRecords. 
    Nếu isUpdatedAllFields = true thì jsonUpdatedRecords có định dạng kiểu
    '{"user_id":1, "role_id":10}'
    Nếu isUpdatedAllFields = false  jsonUpdatedRecords có định dạng kiểu
    '{
        "food_name": [ 
            {"Cá đối": {"food_id": 10}},
            {"Cá đồng tiền": {"food_id": 11}}
        ],
        "protein": [
            {"43.7": {"food_id": 78}},
            {"21.7": {"food_id": 110}}
        ]
    }'    
    whereConditionType thì có các giá trị: 
    "enum" là liệt kê: whereCondition sẽ kiểu như '[{"food_id": 10},{"food_id": 11} ]'
    "logic"  whereCondition là biểu thức điều kiện:  'food_id >100'
    "none"  whereCondition là null, không cần điều kiện WHERE trong mệnh đề
    Examples
    CALL lib_spUpdate(
        NULL,
        'user_role_test',
        '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
        '[{"field_name":"description", "field_type":"VARCHAR(255)"},{"field_name":"active", "field_type":"BOOLEAN"}]',
        '{"description":"mô tả gì đó", "active": true}',
        true,
        'enum',
        '[{"user_id":1,"role_id":10},{"user_id":1,"role_id":11}]',
        @total
    );

    CALL lib_spUpdate(
        NULL,
        'user_role_test',
        '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
        '[{"field_name":"description", "field_type":"VARCHAR(255)"},{"field_name":"active", "field_type":"BOOLEAN"}]',
        '{
            "description":[ 
                {"mô tả 1": {"user_id": 1, "role_id": 10}},
                {"mô tả 2": {"user_id": 1, "role_id": 11}}
            ],
            "active":[ 
                {"true": {"user_id": 1, "role_id": 10}},
                {"false": {"user_id": 1, "role_id": 11}}
            ]
        }',
        false,
        'enum',
        '[{"user_id":1,"role_id":10},{"user_id":1,"role_id":11}]',
        @total
    );

*/

DROP PROCEDURE IF EXISTS lib_spUpdate;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE lib_spUpdate(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonKeyMeta JSON,                    -- schema định nghĩa key field
    IN jsonUpdateMeta JSON,                 -- schema định nghĩa các field cập nhật
    IN jsonUpdatedRecords JSON,             -- bản ghi thực tế cần update
    IN isUpdateAllFields BOOLEAN,           -- true: update toàn bộ, false: theo từng field
    IN whereConditionType VARCHAR(25),      -- enum | logic | none
    IN whereCondition JSON,                 -- điều kiện WHERE nếu cần
    OUT total INT
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
            total
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
            total
        );
    END IF;
END$$

DELIMITER ;
/*=====================================*/
/*Function xây dựng mệnh đề WHERE trong trường hợp điều kiện được xây dựng từ các
liệt kê record
Kết quả của function này là một mệnh đề WHERE kiểu như
WHERE (keyList) IN ( valueList)
Ví dụ
SELECT lib_fnBuildWhereClauseFromEnum(
  '[{"key_name":"user_id","key_type":"INT"},{"key_name":"role_id","key_type":"INT"}]',
  '[{"user_id":1,"role_id":10},{"user_id":1,"role_id":11}]'
);
SELECT lib_fnBuildWhereClauseFromEnum(
  '[{"key_name":"user_id","key_type":"INT"}]',
  '[{"user_id":1},{"user_id":11}]'
);
*/
DROP FUNCTION IF EXISTS lib_fnBuildWhereClauseFromEnum;
DELIMITER $$
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION lib_fnBuildWhereClauseFromEnum(
    jsonKeyMeta JSON,          -- ví dụ: [{"key_name":"food_id","key_type":"INT"}, ...]
    jsonCondition JSON -- ví dụ: [{"food_id": 10}, {"food_id": 11}]
)         
RETURNS TEXT
DETERMINISTIC -- tận dụng cache
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
END$$

DELIMITER ;
/*=====================================*/
/* Ví dụ: 
    CALL lib_spUpdateAllFields(
        NULL,
        'user_role',
        '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
        '[{"field_name":"description", "field_type":"VARCHAR(255)"},{"field_name":"active", "field_type":"BOOLEAN"}]',
        '{"description":"mô tả gì đó", "active": true}',
        'enum',
        '[{"user_id":1,"role_id":10},{"user_id":1,"role_id":11}]',
        @total
    );
    CALL lib_spUpdateAllFields(
        NULL,
        'user_role',
        '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
        '[{"field_name":"description", "field_type":"VARCHAR(255)"},{"field_name":"active", "field_type":"BOOLEAN"}]',
        '{"description":"mô tả gì đó", "active": true}',
        'logic',
        '`user_id` > 110',
        @total
    );
    CALL lib_spUpdateAllFields(
        NULL,
        'user_role',
        '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
        '[{"field_name":"description", "field_type":"VARCHAR(255)"},{"field_name":"active", "field_type":"BOOLEAN"}]',
        '{"description":"mô tả gì đó", "active": true}',
        'none',
        NULL,
        @total
    );
*/
DROP PROCEDURE IF EXISTS lib_spUpdateAllFields;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE lib_spUpdateAllFields(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonKeyMeta JSON,                    -- schema định nghĩa key field, jsonKeyMeta dùng khi whereConditionType là enum
    IN jsonUpdateMeta JSON,                 -- schema định nghĩa các field cập nhật
    IN jsonUpdatedRecords JSON,             -- bản ghi thực tế cần update, dạng bản ghi đơn duy nhất
    IN whereConditionType VARCHAR(25),      -- enum | logic | none
    IN whereCondition JSON,                 -- điều kiện WHERE nếu cần
    OUT total INT
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
        SET total = 0;
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
    SET total = ROW_COUNT();
    DEALLOCATE PREPARE stmt;
END$$

DELIMITER ;
/*==========================================================================*/
/* Dùng để phụ trợ cho spUpdate
Ví dụ SELECT lib_fnBuildANDClause(
        '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
        '{"user_id": 1, "role_id": 10}'
        
        );
    Trả về '`user_id` = 1 AND `role_id` = 10 '    
*/
DROP FUNCTION IF EXISTS lib_fnBuildANDClause;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION lib_fnBuildANDClause(
    keySchema JSON,         -- ví dụ: [{"key_name":"user_id", "key_type":"INT"}, ...]
    keyValues JSON          -- ví dụ: {"user_id":1, "role_id":10}
)
RETURNS TEXT
CONTAINS SQL
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
END$$

DELIMITER ;
/*==========================================================================*/
/*
Dùng để sử dụng trong lib_spUpdateByFieldCase. Mục đích là để xây dựng nên biểu thức
kiểu như `description` = CASE
        WHEN (`user_id` = 1 AND `role_id` = 10) THEN 'mô tả 1'
        WHEN (`user_id` = 1 AND `role_id` = 11) THEN 'mô tả 2'
        ELSE `description`
    END
Example
SELECT lib_fnBuildCaseWhenClauseForUpdateSP('description','VARCHAR(255)',
        '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
        '[ 
            {"mô tả 1": {"user_id": 1, "role_id": 10}},
            {"mô tả 2": {"user_id": 1, "role_id": 11}}
        ]')

*/
DROP FUNCTION IF EXISTS lib_fnBuildCaseWhenClauseForUpdateSP;
DELIMITER $$
CREATE DEFINER=`dichvuqu_common`@`localhost` FUNCTION lib_fnBuildCaseWhenClauseForUpdateSP(
    fieldName VARCHAR(64), 
    fieldType VARCHAR(50),
    jsonKeyMeta JSON,
    jsonUpdatedEachField JSON
)
RETURNS TEXT
CONTAINS SQL -- có chứa câu lệnh SQL (SET, IF,...) nhưng không truy cập dữ liệu bảng,
DETERMINISTIC -- tận dụng cache
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
END$$

DELIMITER ;
/*==========================================================================*/
/*Ví dụ: 
CALL lib_spUpdateByFieldCase(
    NULL,
    'user_role_test',
    '[{"key_name":"user_id", "key_type":"INT"},{"key_name":"role_id", "key_type":"INT"}]',
    '[{"field_name":"description", "field_type":"VARCHAR(255)"},{"field_name":"active", "field_type":"BOOLEAN"}]',
    '{
        "description":[ 
            {"mô tả 1": {"user_id": 1, "role_id": 10}},
            {"mô tả 2": {"user_id": 1, "role_id": 11}}
        ],
        "active":[ 
            {"true": {"user_id": 1, "role_id": 10}},
            {"false": {"user_id": 1, "role_id": 11}}
        ]
    }',
    'enum',
    '[{"user_id":1,"role_id":10},{"user_id":1,"role_id":11}]',
    @total
);
 Câu lệnh sinh ra
UPDATE `user_role`
SET 
    `description` = CASE
        WHEN (`user_id` = 1 AND `role_id` = 10) THEN 'mô tả 1'
        WHEN (`user_id` = 1 AND `role_id` = 11) THEN 'mô tả 2'
        ELSE `description`
    END,
    `active` = CASE
        WHEN (`user_id` = 1 AND `role_id` = 10) THEN true
        WHEN (`user_id` = 1 AND `role_id` = 11) THEN false
        ELSE `active`
    END
WHERE (`user_id`, `role_id`) IN ((1, 10), (1, 11));

    */
DROP PROCEDURE IF EXISTS lib_spUpdateByFieldCase;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE lib_spUpdateByFieldCase(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonKeyMeta JSON,
    IN jsonUpdateMeta JSON,
    IN jsonUpdatedRecords JSON,
    IN whereConditionType VARCHAR(25),
    IN whereCondition JSON,
    OUT total INT
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
        SET total = 0;
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
    SET total = ROW_COUNT();
    DEALLOCATE PREPARE stmt;
END$$
DELIMITER ;