DROP PROCEDURE IF EXISTS lib_spGetUserByNameAndRole;
DELIMITER $$

CREATE PROCEDURE lib_spGetUserByNameAndRole(IN pName VARCHAR(25), IN pRole VARCHAR(25))
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

END$$
DELIMITER ;

