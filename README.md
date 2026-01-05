# Bakong OpenAPI Auto Gateway

Slim 4 based gateway that fronts the Bakong OpenAPI endpoints. It renews and stores an access token, injects it into proxy requests, and exposes a simple cron-friendly endpoint so you do not have to manage token refresh logic in your client applications.

## Features
- **Reverse proxy** for Bakong OpenAPI with automatic bearer token injection.
- **Token storage** in `storage/token.json` with timestamp metadata.
- **Cron-compatible renewal endpoint** (`/bakong/renew/cron`) guarded by a shared secret.
- **Manual renewal script** (`renew-token.php`) for command-line or scheduled jobs.
- Optional request/response **logging hooks** for debugging by toggling a single flag.

## Repository layout
```
.
├── public/           # Slim entry point (`index.php`) and public assets
├── storage/          # Persists the latest Bakong access token
├── logs/             # Optional runtime logs (disabled by default)
├── renew-token.php   # CLI script to renew the Bakong token manually
├── CRON_SETUP.md     # Additional cron configuration guidance
├── composer.json     # PHP dependencies (Slim 4 + PSR-7 implementation)
└── README.md         # You are here
```

## Prerequisites
- PHP 8.0 or newer (Slim 4 supports PHP 7.4+, but PHP 8+ is recommended).
- [Composer](https://getcomposer.org/) v2.
- cURL extension enabled (used for outbound Bakong requests).

## Installation
1. Install dependencies:
   ```bash
   composer install
   ```
2. Ensure the `storage/` and `logs/` directories are writable by the PHP runtime (logs are optional if you keep them disabled).
3. Copy `public/.htaccess` to your web root if you are hosting on Apache, or configure your web server to route all requests to `public/index.php`.

## Configuration
Most runtime options are declared at the top of `public/index.php` and `renew-token.php`:

| Variable | Location | Purpose |
| --- | --- | --- |
| `$BAKONG_BASE_URL` | `public/index.php`, `renew-token.php` | Base URL for the upstream Bakong API. |
| `$TOKEN_FILE` | `public/index.php` | Path where the bearer token is stored. |
| `$EMAIL` | `renew-token.php` | Email associated with the Bakong account when renewing the token manually. |
| `$ENABLE_LOGS` | `public/index.php`, `renew-token.php` | Toggle verbose log output to files under `logs/`. |
| `$cronKey` | `public/index.php` | Secret key protecting the `/bakong/renew/cron` endpoint. Change this immediately after cloning. |

Update these values to match your Bakong sandbox or production environment. The defaults point to `https://api-bakong.nbc.gov.kh` using placeholder credentials.

## Running the gateway locally
Use PHP's built-in server or a compatible web server:

```bash
php -S localhost:8000 -t public
```

Visit `http://localhost:8000/` to confirm the health endpoint responds with metadata about the gateway.

All other paths (e.g., `/v1/check_transaction_by_md5`) are forwarded to the Bakong API. The proxy automatically:
1. Extracts the incoming method, path, query, and body.
2. Loads the stored access token from `storage/token.json`.
3. Adds an `Authorization: Bearer {token}` header if the client did not provide one.
4. Relays the upstream response payload and HTTP status back to the caller.

If the proxy detects an HTML response from Bakong (commonly a 404 page), it converts it into a JSON 404 structure so API clients receive consistent JSON responses.

## Token renewal workflows
### Cron HTTP endpoint
- `GET /bakong/renew/cron?key=<your-secret>` triggers a renewal via the Bakong `/v1/renew_token` API.
- The endpoint writes the refreshed token to `storage/token.json` and logs to `logs/renew-token.log` when logging is enabled.
- Update the hardcoded `$cronKey` and optionally the request payload (email) before deploying.
- See **CRON_SETUP.md** for detailed scheduling instructions for Linux crontab or cloud cron services.

### CLI script
Run the standalone script for manual renewals or when integrating with task schedulers that can execute PHP scripts directly:

```bash
php renew-token.php
```

The script shares the same configuration variables (base URL, email, logging flag) and updates `storage/token.json` on success. It emits exit codes suitable for cron monitoring (`0` on success, `1` on failure).

## Logging
Enable logging by setting `$ENABLE_LOGS = true;` in both `public/index.php` and `renew-token.php`. When enabled:
- Proxy requests/responses are appended to `logs/proxy.log`.
- Token renewal events are appended to `logs/renew-token.log`.

Remember to rotate or purge logs in production to avoid unbounded growth.

## Deployment checklist
1. **Secure the cron key**: Replace the default secret with a high-entropy string and keep it private.
2. **Protect storage**: Ensure `storage/token.json` is not web-accessible. The default structure keeps it outside the public directory.
3. **TLS**: Serve the gateway via HTTPS when exposing it publicly.
4. **Firewall**: Allow inbound traffic only from trusted systems if the gateway is not intended for public consumption.
5. **Monitoring**: Consider adding health checks or log aggregation if running in production.

## Troubleshooting
| Symptom | Suggested fix |
| --- | --- |
| `Access token not available. Please renew token.` | Trigger the cron endpoint or run `php renew-token.php` to obtain a fresh token. Ensure the storage path is writeable. |
| `Gateway Error` with cURL error details | Verify network connectivity to Bakong, TLS certificates, and that PHP's cURL extension is enabled. |
| HTML response returned as JSON 404 | The proxied path likely does not exist upstream. Double-check the Bakong API route or query parameters. |
| Logs not written | Confirm `$ENABLE_LOGS` is `true` and that the `logs/` directory is writable by the PHP process. |

## Contact
Have questions or want to share feedback? Reach out via Telegram: [@CHHEAN0](https://t.me/CHHEAN0).

## Support the project
If this gateway is useful in your workflow, feel free to support future maintenance via KHQR. Scan either code below with your preferred wallet.

| KHQR (KHR) | KHQR (USD) |
| --- | --- |
| ![KHQR KHR payment code](https://storage.perfectcdn.com/axz9n1/phmp0ofcq4eu4gdh.jpg) | ![KHQR USD payment code](https://storage.perfectcdn.com/axz9n1/g3ccceb3ng7o9431.jpg) |

## Additional resources
- [Slim Framework Documentation](https://www.slimframework.com/docs/v4/)
- [Bakong OpenAPI Reference](https://bakong.nbc.gov.kh/)
- `CRON_SETUP.md` in this repository for step-by-step cron configuration guidance.
