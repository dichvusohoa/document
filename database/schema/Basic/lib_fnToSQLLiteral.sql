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