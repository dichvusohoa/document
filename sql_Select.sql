/*
    selectClause: phần mệnh đề select (không chứa từ khóa select)
    jsonWhere có cấu trúc {
        "type": enum | logic | none
        "keyMeta": ví dụ [{"key_name":"food_id","key_type":"INT"}, ...]. Dùng khi type là enum
        "condition":  ví dụ '[{"food_id": 10},{"food_id": 11} ]' hoặc '"food_id">100'. Dùng khi type = enum hoặc type = logic
    }
    jsonHaving có cấu trúc tương tự  jsonWhere. Chỉ dùng khi có mệnh đề GROUP BY
    groupByClause: phần mệnh đề GROUP BY (không chứa từ khóa GROUP BY)
    orderByClause: phần mệnh đề ORDER BY (không chứa từ khóa ORDER BY)
    pageIndex, pageSize: tạo phần clause LIMIT kiểu: LIMIT pageIndex*pageSize, pageSize
    
    Example 
    CALL lib_spSelect(
        "food_id, food_name FROM food",
        '{
            "type": "enum",
            "keyMeta": [{"key_name":"food_type_id","key_type":"INT"}],
            "condition": [{"food_type_id" : 1}]
        }',
        null,
        "",
        "food_name",
        0,
        20
    );

*/
DROP PROCEDURE IF EXISTS lib_spSelect;
DELIMITER $$

CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE lib_spSelect(
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
END$$

DELIMITER ;
