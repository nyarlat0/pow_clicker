# PoW Clicker

PoW Clicker is a small proof-of-work clicker web app. The browser owns an
Ed25519 private key, asks the PHP backend for short-lived authentication nonces
and proof-of-work tasks, solves the task in WebAssembly, and submits the proof
to increment the wallet balance.

## What It Does

- Creates, imports, exports, and stores a 32-byte private key in browser
  `localStorage`.
- Derives the Ed25519 public key in the browser.
- Signs authenticated API requests as `Ed25519(message + nonce)`.
- Issues one-time API authentication nonces that expire after 120 seconds.
- Issues one active proof-of-work challenge per public key.
- Accepts a work nonce when `sha256(challenge + work_nonce)` starts with five
  zero hex characters.
- Adds `1` to the balance after each accepted proof.

## Project Layout

```text
public/
  index.php              Browser UI shell
  assets/app.js          Main browser application
  api/                   JSON API endpoints
  pkg/                   Generated wasm-pack output, ignored by git
src/
  Database.php           MySQL connection helper
  NonceStore.php         Auth nonce creation and consumption
  SignatureVerifier.php  Ed25519 request verification
  TaskStore.php          Proof-of-work task storage
  BalanceStore.php       Balance lookup and updates
wasm/
  src/lib.rs             Browser-facing Rust/WASM functions
Makefile.toml            cargo-make task for building WASM
```

## Requirements

- PHP 8.1 or newer
- Composer
- MySQL or MariaDB
- PHP extensions: `pdo_mysql`, `sodium`, `json`
- Rust toolchain with Rust 2024 edition support
- `wasm-pack`
- `cargo-make` if you want to use the included `Makefile.toml`

## Setup

Install PHP dependencies:

```sh
composer install
```

Create a `.env` file in the project root:

```dotenv
DB_HOST=127.0.0.1
DB_NAME=pow_clicker
DB_USER=pow_clicker
DB_PASSWORD=change-me
```

Create the database schema:

```sh
mariadb pow_clicker < database/schema.sql
```

The schema includes MariaDB events that periodically delete expired auth
nonces and proof-of-work tasks. Make sure the server event scheduler is enabled
if you want that cleanup to run automatically.

Build the browser WASM package:

```sh
cargo make wasm-build
```

If `cargo-make` is not installed, run the equivalent command directly:

```sh
cd wasm
wasm-pack build --release --target web --out-dir ../public/pkg
```

Start a local PHP server from the project root:

```sh
php -S 127.0.0.1:8000 -t public
```

Then open `http://127.0.0.1:8000`.

## API Protocol

All API responses are JSON. Authenticated endpoints require:

- `public_key`: 64 hex characters
- `nonce`: server-issued auth nonce from `/api/nonce.php`
- `message`: endpoint-specific signed payload
- `signature`: 128 hex characters, Ed25519 signature of `message + nonce`

The nonce is consumed after successful signature verification, so each signed
request must fetch a fresh nonce first.

### `POST /api/nonce.php`

Request:

```json
{
  "public_key": "64-hex-character-public-key"
}
```

Response:

```json
{
  "ok": true,
  "nonce": "64-hex-character-nonce"
}
```

### `POST /api/balance.php`

Authenticated request with message:

```text
Get Balance
```

Response:

```json
{
  "ok": true,
  "balance": 0
}
```

### `POST /api/get_task.php`

Authenticated request with message:

```text
Get Challenge
```

Response:

```json
{
  "ok": true,
  "challenge": "64-hex-character-challenge"
}
```

### `POST /api/submit_work.php`

Authenticated request where `message` is the work nonce found by the client.
The proof is valid when:

```text
sha256(challenge + work_nonce)
```

starts with `00000`.

Response:

```json
{
  "ok": true
}
```

## Development

Rebuild WASM after changing `wasm/src/lib.rs`:

```sh
cargo make wasm-build
```

The generated files in `public/pkg/` are ignored by git and must exist before
the browser app can import `/pkg/pow_clicker_wasm.js`.

PHP formatting is configured in `.php-cs-fixer.dist.php`. The repository does
not currently define automated tests or Composer scripts.

## Notes

- Private keys are stored in browser `localStorage`; exporting copies the raw
  private key to the clipboard.
- Balances are stored by `sha256(public_key_bytes)`, not by the raw public key.
- Proof-of-work difficulty is currently hard-coded to five leading zero hex
  characters in both the browser and the submit endpoint.
- Auth nonces and proof-of-work tasks both expire after 120 seconds.

## License

This project is released under The Unlicense.
