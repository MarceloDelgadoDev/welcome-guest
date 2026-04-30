# welcome-guest

A welcome page for PHP servers that replaces the default *"It works!"* screen.

Shows the latest IPs that accessed the server, with date/time and user-agent.

---

## Preview

```
welcome, guest
this server has been visited 7 times

your ip → 189.26.xx.xx — 2025-01-15 14:32:01

recent visitors
#   datetime              ip               user-agent
1   2025-01-15 14:32:01   189.26.xx.xx     Mozilla/5.0 ...
2   2025-01-15 13:10:44   177.84.xx.xx     curl/8.1.0
...
```

---

## Files

```
welcome-guest/
├── index.php   → logic + UI
├── ips.log     → generated automatically (not versioned)
├── .gitignore
└── README.md
```

---

## Usage

### On an Apache/Nginx server with PHP

1. Clone the repository into the server root:

```bash
git clone https://github.com/your-username/welcome-guest.git /var/www/html
```

2. Make sure PHP can write to the directory so it can create `ips.log`:

```bash
chown www-data:www-data /var/www/html
# or
chmod 755 /var/www/html
```

3. Open the server in your browser. `ips.log` will be created automatically.

### Test locally with the PHP built-in server

```bash
cd welcome-guest
php -S localhost:8080
```

Open `http://localhost:8080` in your browser.

---

## Configuration

At the top of `index.php` there are two constants:

| Constant      | Default     | Description                    |
|---------------|-------------|--------------------------------|
| `LOG_FILE`    | `./ips.log` | Path to the log file           |
| `MAX_ENTRIES` | `50`        | Maximum number of rows kept    |

---

## Security

- `ips.log` is ignored by Git (`.gitignore`) so public IPs are not exposed in the repo.
- Values shown on the page go through `htmlspecialchars()` to mitigate XSS.
- Intended for personal or development servers.

---

## License

MIT
