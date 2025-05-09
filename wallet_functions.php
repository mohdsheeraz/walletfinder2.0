<?php
// wallet_functions.php

// 1) Open (or create) the SQLite database in the same directory
$dbPath = __DIR__ . '/wallets.db';
$db     = new SQLite3($dbPath);

// 2) Ensure the table exists
$db->exec('
  CREATE TABLE IF NOT EXISTS wallet_scans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    private_key TEXT NOT NULL,
    btc_address TEXT NOT NULL,
    balance REAL NOT NULL,
    scanned_at TEXT NOT NULL
  )
');

// 3) Your scan function
function scanAndLogWallet(): array {
    global $db;

    // Generate a random 32‑byte private key (hex)
    $privHex = bin2hex(random_bytes(32));

    // ECC parameters and helpers (same as your existing code)...
    $p  = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F',16);
    $Gx = gmp_init('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798',16);
    $Gy = gmp_init('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8',16);

    function pointAdd($P, $Q, $p) { /* ... copy your code ... */ }
    function pointDouble($P, $p) { /* ... */ }
    function scalarMultiply($kHex, $P, $p) { /* ... */ }
    function base58Check($hex)   { /* ... */ }

    // Derive compressed public key and address
    $P = scalarMultiply($privHex, ['x'=>$Gx,'y'=>$Gy], $p);
    if (!$P) {
      return ['error'=>'EC error'];
    }
    $xHex   = str_pad(gmp_strval($P['x'],16),64,'0',STR_PAD_LEFT);
    $prefix = (gmp_mod($P['y'],2)==0) ? '02' : '03';
    $pubHex = $prefix . $xHex;
    $rip    = hash('ripemd160', hash('sha256', hex2bin($pubHex), true), true);
    $payload= '00' . bin2hex($rip);
    $address= base58Check($payload);

    // Fetch balance
    $balJson = @file_get_contents("https://blockchain.info/balance?active={$address}");
    if ($balJson === FALSE) {
      return ['error'=>'rate limited'];
    }
    $jd  = json_decode($balJson, true);
    $bal = isset($jd[$address]['final_balance'])
           ? $jd[$address]['final_balance']/1e8
           : 0.0;

    // If positive, log into SQLite
    if ($bal > 0) {
      $stmt = $db->prepare('
        INSERT INTO wallet_scans 
          (private_key, btc_address, balance, scanned_at)
        VALUES 
          (:priv, :addr, :bal, :ts)
      ');
      $stmt->bindValue(':priv', $privHex, SQLITE3_TEXT);
      $stmt->bindValue(':addr', $address, SQLITE3_TEXT);
      $stmt->bindValue(':bal' , $bal,     SQLITE3_FLOAT);
      $stmt->bindValue(':ts'  , date('c'), SQLITE3_TEXT);
      $stmt->execute();
    }

    return [
      'private_key'=> $privHex,
      'btc_address'=> $address,
      'balance'    => $bal,
    ];
}
