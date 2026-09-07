DROP FUNCTION IF EXISTS lib_fnHPIsFunctionPrototypeValid;

DELIMITER ;;

CREATE FUNCTION lib_fnHPIsFunctionPrototypeValid(
    functionPrototype TEXT
)
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    IF
        functionPrototype IS NULL
        OR TRIM(functionPrototype) = ''
    THEN
        RETURN FALSE;
    END IF;

    /*
     * Chấp nhận:
     *
     * fn()
     * fn(a)
     * fn(a,b,c)
     * fn(a, b, c, permission_mask)
     *
     * Mỗi argument hiện tại phải là một SQL identifier.
     *
     * Ví dụ permission_mask có thể là policy attribute
     * nằm trên policy scope.
     */
    RETURN TRIM(functionPrototype) REGEXP
        '^[A-Za-z_][A-Za-z0-9_]*[[:space:]]*[(][[:space:]]*([A-Za-z_][A-Za-z0-9_]*[[:space:]]*(,[[:space:]]*[A-Za-z_][A-Za-z0-9_]*[[:space:]]*)*)?[)][[:space:]]*$';
END ;;

DELIMITER ;