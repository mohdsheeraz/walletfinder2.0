#!/usr/bin/env php
<?php
// wallet_scanner.php
// CLI entry point for continuous scanning

require_once __DIR__ . '/wallet_functions.php';

echo "Starting Bitcoin Wallet Scanner...\n";

while (true) {
    $result = scanAndLogWallet();

    if (isset($result['error'])) {
        // If rate limited or other error, wait longer
        echo "[Error] {$result['error']}. Retrying in 5s...\n";
        sleep(5);
        continue;
    }

    // Print out each scan result
    echo sprintf(
        "Address: %s | Key: %s | Balance: %s BTC\n",
        $result['btc_address'],
        $result['private_key'],
        $result['balance']
    );

    // If a positive balance was found, you might alert or break:
    if ($result['balance'] > 0) {
        echo "!!! Found non-zero wallet. Exiting.\n";
        break;
    }

    // Throttle to avoid API limits (adjust as needed)
    usleep(250000); // 250 ms
}
