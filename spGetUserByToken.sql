/*Token lưu trong database chỉ là phần left của token. Toàn bộ token lưu trong cookie với format
leftToken:rightToken. Làm như vậy đẻ nếu hacker có lấy được database về token thì cũng không có tokent thật sự 

Có hai loại hệ thống. 
- Một loại hệ thống có chức năng thuê bao, khi đó user.subscriber sẽ khác null. Như hệ thống quản lý mầm non.
- Một loại hệ thống không có chức năng thuê bao. Như là hệ thống quản lý document hoặc một website tin tức bình thường 
chính vì có hai loai hệ thống này nên chức năng liệt kê các module mà user sở hữu sẽ có hai khả năng

- Trường hợp 1 thì căn cứ vào table subscriber_module
- Trường hợp 2 thì sẽ mặc định là tất cả các module hiện hữu

Chú ý rằng nếu user không có role nào thì do đặc điểm của hàm GROUP_CONCAT trả về NULL
và hàm CONCAT khi ghép chuỗi với giá trị NULL sẽ trả về NULL nên kết quả trả về field roles sẽ là NULL


*/
DROP PROCEDURE IF EXISTS lib_spGetUserByToken;
DELIMITER $$

CREATE PROCEDURE lib_spGetUserByToken(IN leftToken VARCHAR(125))
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
        at.user_id AS id,
        u.name,
        u.password,
        u.subscriber_id,
        ur.roles,
        CASE
            WHEN u.subscriber_id IS NULL THEN am.registered_modules
            ELSE sm.registered_modules
        END AS registered_modules
    FROM auth_token at
    JOIN user u ON at.user_id = u.id
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN subscriber_modules sm ON u.subscriber_id = sm.subscriber_id
    LEFT JOIN all_modules am ON u.subscriber_id IS NULL
    WHERE at.selector = leftToken
      AND at.exp_date > NOW();

END$$
DELIMITER ;


