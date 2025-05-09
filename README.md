# walletfinder2.0
# Bitcoin Wallet Finder – Web Scanner

A lightweight PHP + JavaScript web application that continuously generates random Bitcoin wallets (private key + compressed address), checks their on‑chain balance via the Blockchain.info API, and logs any with non‑zero funds. Designed for educational and experimental use only.


## 📖 Overview

- **Generate**: Creates a 32‑byte cryptographically secure private key (hex).  
- **Derive**: Computes the matching compressed public key and P2PKH address using pure‑PHP GMP-based secp256k1 math.  
- **Fetch**: Calls Blockchain.info’s public `/balance` endpoint to retrieve the wallet’s balance in BTC.  
- **Log**: Inserts any wallets with positive balances into `wallet_scans` and `btc_wallets` MySQL tables.  
- **Loop**: Runs in a tight loop (adjustable delay) until manually stopped or a funded wallet is found.  
- **Gated**: Protected behind WordPress authentication and subscription lookup.

---

## 🚀 Features

- **Pure PHP ECC** — no external crypto extensions beyond GMP  
- **Real‑time UI** — Start/Stop buttons, live counter, neon‑themed design  
- **Rate‑limit handling** — auto‑pause on HTTP 429, back‑off retries  
- **Persistent logging** — positive wallet hits stored in MySQL  
- **Easy deployment** — runs in browser and CLI (Railway worker)  

---

## 🛠️ Installation & Setup

1. **Clone the repo**  
   bash
   git clone https://github.com/mohdsheeraz/walletfinder2.0.git
   cd walletfinder2.0


2. **Configure MySQL**
   Create a database and run:

   ```sql
   CREATE TABLE wallet_scans (
     id INT AUTO_INCREMENT PRIMARY KEY,
     private_key VARCHAR(64),
     btc_address VARCHAR(35),
     balance DECIMAL(16,8),
     scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   CREATE TABLE btc_wallets LIKE wallet_scans;
   ```

3. **Copy & Edit `index.php`**

   * Update DB credentials.
   * Ensure `wp-load.php` path is correct (or remove WordPress gating).

4. **Deploy to Shared Hosting**

   * Upload files to your PHP‑enabled server.
   * Point your browser to `/index.php`.

---

## ⚙️ CLI Worker Mode

To run continuously without a browser:

1. Extract logic into `wallet_functions.php` + `wallet_scanner.php` (CLI).
2. Make it executable:

   ```bash
   chmod +x wallet_scanner.php
   ```
3. Run in terminal:

   ```bash
   php wallet_scanner.php
   ```
4. Or deploy as a Railway worker with start command:

   ```
   php wallet_scanner.php
   ```

---

## 📄 Usage

* Visit the web UI → click **Start**.
* Watch the **Wallets Scanned** counter climb.
* When a wallet with BTC is found, the app displays it and pauses.
* Click **Stop** at any time.

---

## 🔒 Security & Ethics

  **Educational only**: never use on real production funds.
  **Private keys** are displayed on‑screen; treat them securely.
  **No hacking**: this simply tries random keys, the chance of hitting a funded wallet is effectively zero.


## 📝 License

This project is open‑source under the [MIT License](LICENSE). You are free to use, modify, and distribute — please retain the copyright notice.


## 🤝 Contributions

Pull requests and issues are welcome! Please fork the repository and submit a PR with your improvements.


## 📞 Contact

**Mohd Sheeraz**
– Email: [mohdsajjad352@gmail.com](mailto:mohdsajjad352@gmail.com)
– GitHub: [mohdsheeraz](https://github.com/mohdsheeraz)
