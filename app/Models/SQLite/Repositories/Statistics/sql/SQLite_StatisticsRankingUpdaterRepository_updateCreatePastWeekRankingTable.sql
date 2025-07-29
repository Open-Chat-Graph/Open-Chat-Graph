SELECT
    t1.open_chat_id AS open_chat_id,
    t1.member - t2.member AS diff_member,
    (
        (
            CAST(t1.member AS REAL) - CAST(t2.member AS REAL)
        ) * 100.0 / CAST(t2.member AS REAL)
    ) AS percent_increase,
    (
        CAST(t1.member AS REAL) - CAST(t2.member AS REAL)
    ) + (
        (
            (
                CAST(t1.member AS REAL) - CAST(t2.member AS REAL)
            ) / CAST(t2.member AS REAL)
        ) * 10
    ) AS index1
FROM
    (
        SELECT
            *
        FROM
            statistics
        WHERE
            date = date(:DATE_STRING)
            AND open_chat_id IN (
                SELECT
                    open_chat_id
                FROM
                    statistics
                WHERE
                    `date` BETWEEN DATE(:DATE_STRING, '-8 days')
                    AND :DATE_STRING
                GROUP BY
                    open_chat_id
                HAVING
                    0 < (
                        CASE
                            WHEN COUNT(DISTINCT member) > 1 THEN 1
                            ELSE 0
                        END
                    )
            )
    ) t1
    JOIN (
        SELECT
            *
        FROM
            statistics
        WHERE
            date = date(:DATE_STRING, '-7 day')
            AND member >= 10
    ) t2 ON t1.open_chat_id = t2.open_chat_id
ORDER BY
    CASE
        WHEN index1 > 0 THEN 1
        WHEN index1 = 0 THEN 2
        ELSE 3
    END,
    CASE
        WHEN index1 = 0 THEN t1.member
        ELSE index1
    END DESC