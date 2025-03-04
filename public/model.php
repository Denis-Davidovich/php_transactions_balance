<?php

/**
 * Return list of users.
 */
function get_users($conn): array
{
    $statement = $conn->query('SELECT u.id, u.name
        FROM `users` u
        JOIN `user_accounts` a ON a.user_id = u.id
        JOIN (    SELECT account_from account_id FROM `transactions`
            UNION SELECT account_to   account_id FROM `transactions`
        ) account_has_trx ON a.id = account_has_trx.account_id
        GROUP BY user_id'
    );
    $users = array();
    while ($row = $statement->fetch()) {
        $users[$row['id']] = $row['name'];
    }
    return $users;
}

/**
 * Return transactions balances of given user.
 */
function get_user_transactions_balances($user_id, $conn)
{
    $statement = $conn->prepare('
        SELECT period, SUM(total) as amount, SUM(trxs) as count
        FROM (
          SELECT TRX_IN.user_id, TRX_IN.period, TRX_IN.total, TRX_IN.count trxs,
                 TRX_IN.trxs_in trxs_log
          FROM (SELECT u.id                      user_id,
                       a.id                      account_id,
                       STRFTIME("%Y-%m", trdate) period,
                       sum(amount)               total,
                       count(t.id)               count,
                       group_concat(t.id)        trxs_in
                FROM users u
                         JOIN user_accounts a ON a.user_id = u.id
                         JOIN transactions t ON a.id = t.account_to
                WHERE u.id = :user_id
                GROUP BY u.id, a.id, period
                ORDER BY u.id, a.id, period) TRX_IN
          UNION
          SELECT TRX_OUT.user_id, TRX_OUT.period, -1 * TRX_OUT.total, TRX_OUT.count trxs,
                 TRX_OUT.trxs_out trxs_log
          FROM (SELECT u.id                      user_id,
                       a.id                      account_id,
                       STRFTIME("%Y-%m", trdate) period,
                       sum(amount)               total,
                       -- Is this an internal transaction? if so, then we do not have to account for it twice
                       COUNT(IIF(
                                (SELECT a_from.user_id FROM user_accounts a_from WHERE a_from.id = t.account_from)
                            ==  (SELECT   a_to.user_id FROM user_accounts a_to   WHERE   a_to.id = t.account_to)
                                , null
                                , 1
                       )) count,
                       group_concat(t.id)        trxs_out
                FROM users u
                         JOIN user_accounts a ON a.user_id = u.id
                         JOIN transactions t ON a.id = t.account_from
                WHERE u.id = :user_id
                GROUP BY u.id, a.id, period
                ORDER BY u.id, a.id, period) TRX_OUT
            )
        WHERE user_id = :user_id
        GROUP BY user_id, period
    ');
    $statement->execute(['user_id' => $user_id]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $months = [
        '01' => 'January',
        '02' => 'February',
        '03' => 'March',
        '04' => 'April',
        '05' => 'May',
        '06' => 'June',
        '07' => 'July',
        '08' => 'August',
        '09' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December'
    ];

    foreach ($rows as &$row) {
        $month = substr($row['period'], -2);
        $row['month'] = $months[$month];

        $row = [
            'month' => $months[$month],
            'amount' => $row['amount'],
            'count' => $row['count'],
        ];
    }

    return $rows;
}
