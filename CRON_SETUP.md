# Cron Job Auto Token Renewal Setup

Since you are not using Windows for the cron job but rather a global/external cron service (or a Linux server), use the following endpoint to renew your token automatically.

## 1. The Endpoint
**URL**: `https://your-domain.com/bakong/renew/cron?key=bakong_secret_secure_key_2026`
**Method**: `GET`

*Replace `https://your-domain.com` with your actual deployed domain.*

## 2. Hardcoded Key
For security, this endpoint is protected by a key.
**Key**: `bakong_secret_secure_key_2026`
*(You can change this in `public/index.php` line 53)*

## 3. Setting up the Cron Job
You have two main options depending on your hosting environment.

### Option A: Using a Linux Server (crontab)
If you have SSH access to a Linux server, you can set up a crontab entry.

1. Open crontab:
   ```bash
   crontab -e
   ```

2. Add a line to run the renewal **once a month** (e.g., at midnight on the 1st of every month):
   ```cron
   0 0 1 * * curl "https://your-domain.com/bakong/renew/cron?key=bakong_secret_secure_key_2026"
   ```

   **Or every 3 months:**
   ```cron
   0 0 1 */3 * curl "https://your-domain.com/bakong/renew/cron?key=bakong_secret_secure_key_2026"
   ```

### Option B: Using an Online Cron Service
If you don't manage the server, use a free cron service like:
- [cron-job.org](https://cron-job.org/)
- [EasyCron](https://www.easycron.com/)

**Configuration:**
- **URL**: `https://your-domain.com/bakong/renew/cron?key=b460c5c497f5abf57ba5e245f6edbd0ce1f4c85380f69af4774a67cb6f672b13`
- **Schedule**: "Every Month" or "Every 3 Months"
- **Method**: GET

## 4. Verification
You can check if the renewal worked by:
1. Visiting the URL manually in your browser.
2. Checking the log file at `logs/renew-token.log`.
