DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `lib_spGetUserByNameAndRoles`(
    IN pName  VARCHAR(25),
    IN pRoles JSON
)
    READS SQL DATA
proc_main: BEGIN

    /*---------------------------------------------------------------------------------------------------------------
     * pRoles:
     *
     *   NULL
     *       Không giới hạn role.
     *
     *   JSON_ARRAY('admin')
     *       User phải có role admin.
     *
     *   JSON_ARRAY('admin', 'editor')
     *       User phải có ít nhất một role trong tập trên.
     *
     *   JSON_ARRAY()
     *       Không user nào được chấp nhận.
     *-------------------------------------------------------------------------------------------------------------*/

    IF pName IS NULL OR TRIM(pName) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'pName không được NULL hoặc rỗng';
    END IF;

    IF pRoles IS NOT NULL
       AND JSON_TYPE(pRoles) <> 'ARRAY' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'pRoles phải là NULL hoặc JSON array';
    END IF;

    WITH

    /*---------------------------------------------------------------------------------------------------------------
     * Tổng hợp toàn bộ role của từng user.
     *
     * Format roles 
     *
     * {
     *     "admin": "Quản trị viên",
     *     "editor": "Biên tập viên"
     * }
     *
     * Key   = role.code
     * Value = role.display_name
     *-------------------------------------------------------------------------------------------------------------*/
    user_roles AS (
        SELECT
            ur.user_id,

            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        JSON_QUOTE(r.code),
                        ':',
                        JSON_QUOTE(IFNULL(r.display_name, ''))
                    )
                    ORDER BY r.code
                    SEPARATOR ','
                ),
                '}'
            ) AS roles

        FROM user_role AS ur

        INNER JOIN role AS r
            ON r.id = ur.role_id

        GROUP BY ur.user_id
    ),

    /*---------------------------------------------------------------------------------------------------------------
     * Tổng hợp module được đăng ký theo subscriber.
     *
     * Format registered_modules
     *
     * {
     *     "1": "compiled-materials",
     *     "2": "pbt-framework"
     * }
     *
     * Key   = module.id
     * Value = module.name
     *-------------------------------------------------------------------------------------------------------------*/
    subscriber_modules AS (
        SELECT
            sm.subscriber_id,

            CONCAT(
                '{',
                GROUP_CONCAT(
                    CONCAT(
                        JSON_QUOTE(CAST(m.id AS CHAR)),
                        ':',
                        JSON_QUOTE(IFNULL(m.name, ''))
                    )
                    ORDER BY m.id
                    SEPARATOR ','
                ),
                '}'
            ) AS registered_modules

        FROM subscriber_module AS sm

        INNER JOIN module AS m
            ON m.id = sm.module_id

        GROUP BY sm.subscriber_id
    ),

    /*---------------------------------------------------------------------------------------------------------------
     * Tổng hợp toàn bộ module.
     *
     * Dùng cho user có subscriber_id IS NULL.
     * Format giống subscriber_modules.
     *-------------------------------------------------------------------------------------------------------------*/
    all_modules AS (
        SELECT
            CONCAT(
                '{',
                COALESCE(
                    GROUP_CONCAT(
                        CONCAT(
                            JSON_QUOTE(CAST(m.id AS CHAR)),
                            ':',
                            JSON_QUOTE(IFNULL(m.name, ''))
                        )
                        ORDER BY m.id
                        SEPARATOR ','
                    ),
                    ''
                ),
                '}'
            ) AS registered_modules

        FROM module AS m
    )

    SELECT
        u.id,
        u.name,
        u.password,
        u.subscriber_id,

        COALESCE(
            ur.roles,
            '{}'
        ) AS roles,

        CASE
            WHEN u.subscriber_id IS NULL
                THEN am.registered_modules
            ELSE
                COALESCE(sm.registered_modules, '{}')
        END AS registered_modules

    FROM `user` AS u

    LEFT JOIN user_roles AS ur
        ON ur.user_id = u.id

    LEFT JOIN subscriber_modules AS sm
        ON sm.subscriber_id = u.subscriber_id

    LEFT JOIN all_modules AS am
        ON u.subscriber_id IS NULL

    WHERE u.name = pName

      AND (
            pRoles IS NULL

            OR EXISTS (
                SELECT 1

                FROM user_role AS ur2

                INNER JOIN role AS r2
                    ON r2.id = ur2.role_id

                WHERE ur2.user_id = u.id

                  AND JSON_CONTAINS(
                        pRoles,
                        JSON_QUOTE(r2.code),
                        '$'
                      ) = 1
            )
          );

    

END$$
DELIMITER ;