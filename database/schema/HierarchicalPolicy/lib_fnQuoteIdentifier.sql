DROP FUNCTION IF EXISTS lib_fnQuoteIdentifier;

DELIMITER ;;

CREATE FUNCTION lib_fnQuoteIdentifier(
    identifierName VARCHAR(64)
)
RETURNS VARCHAR(70)
DETERMINISTIC
BEGIN
    IF
        identifierName IS NULL
        OR identifierName = ''
        OR identifierName NOT REGEXP '^[A-Za-z_][A-Za-z0-9_]*$'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'SQL identifier không hợp lệ';
    END IF;

    RETURN CONCAT(
        '`',
        identifierName,
        '`'
    );
END ;;

DELIMITER ;