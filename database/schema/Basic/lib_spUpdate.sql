DELIMITER ;;
CREATE DEFINER=`dichvuqu_common`@`localhost` PROCEDURE `lib_spUpdate`(
    IN dbName VARCHAR(64),
    IN tableName VARCHAR(64),
    IN jsonKeyMeta JSON,                    -- schema định nghĩa key field
    IN jsonUpdateMeta JSON,                 -- schema định nghĩa các field cập nhật
    IN jsonUpdatedRecords JSON,             -- bản ghi thực tế cần update
    IN isUpdateAllFields BOOLEAN,           -- true: update toàn bộ, false: theo từng field
    IN whereConditionType VARCHAR(25),      -- enum | logic | none
    IN whereCondition JSON,                 -- điều kiện WHERE nếu cần
    OUT totalUpdate INT
)
    MODIFIES SQL DATA
BEGIN
    IF isUpdateAllFields THEN
        CALL lib_spUpdateAllFields(
            dbName,
            tableName,
            jsonKeyMeta,
            jsonUpdateMeta,
            jsonUpdatedRecords,
            whereConditionType,
            whereCondition,
            totalUpdate
        );
    ELSE
        CALL lib_spUpdateByFieldCase(
            dbName,
            tableName,
            jsonKeyMeta,
            jsonUpdateMeta,
            jsonUpdatedRecords,
            whereConditionType,
            whereCondition,
            totalUpdate
        );
    END IF;
END ;;
DELIMITER ;