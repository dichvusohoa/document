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