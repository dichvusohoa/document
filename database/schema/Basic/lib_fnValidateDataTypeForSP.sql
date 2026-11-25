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