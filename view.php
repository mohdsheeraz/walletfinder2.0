<?php
$db = new SQLite3(__DIR__ . '/wallets.db');

// Check if table exists
$check = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='wallet_scans'");
if (!$check) {
    die("Table 'wallet_scans' not found.");
}

$results = $db->query("SELECT * FROM wallet_scans ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Wallet Log Viewer</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
    <h1>Logged Wallets</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Private Key</th>
            <th>BTC Address</th>
            <th>Balance</th>
            <th>Scanned At</th>
        </tr>
        <?php while ($row = $results->fetchArray(SQLITE3_ASSOC)): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['private_key']) ?></td>
                <td><?= htmlspecialchars($row['btc_address']) ?></td>
                <td><?= htmlspecialchars($row['balance']) ?></td>
                <td><?= htmlspecialchars($row['scanned_at']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
