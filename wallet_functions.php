<?php
// wallet_functions.php

// 1) Open (or create) the SQLite database
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

// 3) ECC & Base58Check helper functions (declare once)
function pointAdd($P, $Q, $p) {
    if ($P === null) return $Q;
    if ($Q === null) return $P;
    if (gmp_cmp($P['x'], $Q['x']) === 0) {
        if (gmp_cmp(gmp_mod(gmp_add($P['y'], $Q['y']), $p), 0) === 0) {
            return null;
        }
        return pointDouble($P, $p);
    }
    $s = gmp_mod(
        gmp_mul(
            gmp_sub($Q['y'], $P['y']),
            gmp_invert(gmp_sub($Q['x'], $P['x']), $p)
        ),
        $p
    );
    $xR = gmp_mod(gmp_sub(gmp_sub(gmp_pow($s, 2), $P['x']), $Q['x']), $p);
    $yR = gmp_mod(gmp_sub(gmp_mul($s, gmp_sub($P['x'], $xR)), $P['y']), $p);
    return ['x' => $xR, 'y' => $yR];
}

function pointDouble($P, $p) {
    if ($P === null) return null;
    $s = gmp_mod(
        gmp_mul(
            gmp_mul(3, gmp_pow($P['x'], 2)),
            gmp_invert(gmp_mul(2, $P['y']), $p)
        ),
        $p
    );
    $xR = gmp_mod(gmp_sub(gmp_pow($s, 2), gmp_mul(2, $P['x'])), $p);
    $yR = gmp_mod(gmp_sub(gmp_mul($s, gmp_sub($P['x'], $xR)), $P['y']), $p);
    return ['x' => $xR, 'y' => $yR];
}

function scalarMultiply($kHex, $P, $p) {
    $k     = gmp_init($kHex, 16);
    $result = null;
    $addend = $P;
    while (gmp_cmp($k, 0) > 0) {
        if (gmp_testbit($k, 0)) {
            $result = pointAdd($result, $addend, $p);
        }
        $addend = pointDouble($addend, $p);
        $k       = gmp_div_q($k, 2);
    }
    return $result;
}

function base58Check($hex) {
    $alpha = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $bin   = hex2bin($hex);
    $chk   = substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4);
    $full  = $bin . $chk;
    $num   = gmp_init(bin2hex($full), 16);
    $str   = '';
    while (gmp_cmp($num, 0) > 0) {
        list($num, $r) = gmp_div_qr($num, 58);
        $str = $alpha[gmp_intval($r)] . $str;
    }
    for ($i = 0; $i < strlen($full) && $full[$i] === "\x00"; $i++) {
        $str = '1' . $str;
    }
    return $str;
}

// 4) Main scan & log function
function scanAndLogWallet(): array {
    global $db;

    // 4.1) Generate private key
    $privHex = bin2hex(random_bytes(32));

    // 4.2) ECC params
    $p  = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
    $Gx = gmp_init('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16);
    $Gy = gmp_init('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16);

    // 4.3) Derive compressed pubkey
    $P = scalarMultiply($privHex, ['x' => $Gx, 'y' => $Gy], $p);
    if (!$P) {
        return ['error' => 'EC error'];
    }
    $xHex   = str_pad(gmp_strval($P['x'], 16), 64, '0', STR_PAD_LEFT);
    $prefix = (gmp_mod($P['y'], 2) == 0) ? '02' : '03';
    $pubHex = $prefix . $xHex;

    // 4.4) Compute address
    $rip     = hash('ripemd160', hash('sha256', hex2bin($pubHex), true), true);
    $payload = '00' . bin2hex($rip);
    $address = base58Check($payload);

    // 4.5) Fetch balance
    $balJson = @file_get_contents("https://blockchain.info/balance?active={$address}");
    if ($balJson === false) {
        return ['error' => 'rate limited'];
    }
    $jd  = json_decode($balJson, true);
    $bal = isset($jd[$address]['final_balance']) ? $jd[$address]['final_balance'] / 1e8 : 0.0;

    // 4.6) Log positive balances
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

    // 4.7) Return result
    return [
        'private_key' => $privHex,
        'btc_address' => $address,
        'balance'     => $bal,
    ];
}
