# RisetProperties

A PHP/MySQL property management system.

## Run with Docker Compose

This repository includes a portable Docker setup for running the app on any machine with Docker.

1. Copy `.env.example` to `.env` if you need custom database credentials.

2. Run:

```bash
docker compose up --build -d
```

3. Open your browser at:

- `http://localhost:8080`

4. To import the database schema:

```bash
docker compose exec db mysql -u root -proot riset_properties < /var/www/html/database-setup-riset.sql
```

## Run in GitHub Codespaces

The repository includes a `.devcontainer` folder so Codespaces can build a consistent PHP/Apache/MariaDB environment.

1. Open the repository in Codespaces.
2. Rebuild the container if prompted.
3. In the container terminal, run:

```bash
cd /workspaces/Risetproperties
sudo service mysql start
mysql -u root -e "CREATE DATABASE IF NOT EXISTS riset_properties DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root riset_properties < database-setup-riset.sql
```

4. Open forwarded port `80` from the Codespaces ports panel.

## Run locally with XAMPP

1. Copy `.env.example` to `.env` and set your local MySQL credentials.
2. Place this repository inside your XAMPP `htdocs` folder.
3. Import `database-setup-riset.sql` into MySQL.
4. Visit:

- `http://localhost/RisetProperties-Website-and-AdminPanel`

## Environment file

Copy configuration from `.env.example`:

```bash
cp .env.example .env
```

Update values as needed.

## Notes

- The app loads credentials from `$_ENV` or `.env` using `dbconnect.php`.
- Docker Compose uses `DB_HOST=db` inside the web container.
- If you need to change ports, update `docker-compose.yml`.
