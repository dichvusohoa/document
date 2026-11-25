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