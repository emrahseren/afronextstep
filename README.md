# AfroNextStep website

Single-page site for [afronextstep.com](https://www.afronextstep.com): Software, Business and Investment Consulting.

## Files

| File | Purpose |
|---|---|
| `index.html` | The whole site (HTML, CSS and JavaScript in one file) |
| `contact.php` | Sends the contact form by email through the Namecheap Private Email mailbox (SMTP) |
| `contact-config.example.php` | Template for the mail password file |
| `favicon.ico`, `favicon.svg`, `apple-touch-icon.png` | Site icons |
| `og-image.png` | Preview image for shared links |
| `.htaccess` | Blocks web access to the password file and logs |

## Deploy

Upload the files to `public_html` on the cPanel hosting (or deploy this repository with cPanel Git Version Control).

## One-time setup on the server

The mailbox password is not in this repository.

1. In `public_html`, copy `contact-config.example.php` to `contact-config.php`.
2. Edit `contact-config.php` and type the password of `nuh@afronextstep.com`.

`contact-config.php` is in `.gitignore`, so deploys do not overwrite it. If the form says the message could not be sent, the reason is written to `error_log` in `public_html`.
