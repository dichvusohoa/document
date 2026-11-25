DROP PROCEDURE IF EXISTS lib_spHPBuildAncestorPath;

DELIMITER ;;

CREATE PROCEDURE lib_spHPBuildAncestorPath(
    IN dbName         VARCHAR(64),
    IN nodeSource     VARCHAR(64),
    IN nodeIdField    VARCHAR(64),
    IN parentIdField  VARCHAR(64),
    IN nodeId         BIGINT
)
READS SQL DATA
BEGIN
    DECLARE fullNodeSource VARCHAR(255);

    DECLARE qNodeIdField   VARCHAR(70);
    DECLARE qParentIdField VARCHAR(70);

    DECLARE finalSQL LONGTEXT;

    IF nodeId IS NULL OR nodeId <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'nodeId phải là số nguyên dương';
    END IF;

    /*
     * Source có thể là TABLE hoặc VIEW.
     *
     * lib_fnGetFullTableName() về mặt SQL đều xử lý
     * được cả hai.
     */
    SET fullNodeSource =
        lib_fnGetFullTableName(
            dbName,
            nodeSource
        );

    SET qNodeIdField =
        lib_fnQuoteIdentifier(
            nodeIdField
        );

    SET qParentIdField =
        lib_fnQuoteIdentifier(
            parentIdField
        );

    DROP TEMPORARY TABLE IF EXISTS
        tmp_lib_hp_ancestor;

    CREATE TEMPORARY TABLE
        tmp_lib_hp_ancestor
    (
        node_id BIGINT NOT NULL,
        depth   INT    NOT NULL,

        PRIMARY KEY (node_id)
    );

    /*
     * visited dùng để tránh vòng lặp nếu tree data
     * không may bị cycle.
     */
    SET finalSQL = CONCAT(
        'INSERT INTO tmp_lib_hp_ancestor ',
        '    (node_id, depth) ',

        'WITH RECURSIVE hp_ancestor ',
        '    (node_id, parent_id, depth, visited) AS (',

        '    SELECT ',
        '        n.', qNodeIdField, ', ',
        '        n.', qParentIdField, ', ',
        '        0, ',
        '        CAST(',
        '            CONCAT('','', n.', qNodeIdField, ', '','') ',
        '            AS CHAR(10000)',
        '        ) ',

        '    FROM ', fullNodeSource, ' n ',

        '    WHERE n.', qNodeIdField, ' = ',
        lib_fnToSQLLiteral(
            nodeId,
            'BIGINT'
        ),

        '    UNION ALL ',

        '    SELECT ',
        '        p.', qNodeIdField, ', ',
        '        p.', qParentIdField, ', ',
        '        a.depth + 1, ',
        '        CONCAT(',
        '            a.visited, ',
        '            p.', qNodeIdField, ', ',
        '            '',''',
        '        ) ',

        '    FROM ', fullNodeSource, ' p ',

        '    JOIN hp_ancestor a ',
        '      ON p.', qNodeIdField,
        '       = a.parent_id ',

        '    WHERE LOCATE(',
        '        CONCAT('','', p.', qNodeIdField, ', '',''), ',
        '        a.visited',
        '    ) = 0',

        ') ',

        'SELECT node_id, depth ',
        'FROM hp_ancestor'
    );

    PREPARE stmt FROM finalSQL;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END ;;

DELIMITER ;