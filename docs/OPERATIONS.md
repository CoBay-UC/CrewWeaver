# Operations

## Backup

Quiesce writes, dump MariaDB with routines and transaction consistency, then archive the upload volume. Back up both at the same logical point:

```sh
docker compose exec -T db mariadb-dump -ucrewweaver -p"$DB_PASSWORD" --single-transaction crewweaver > crewweaver.sql
docker run --rm -v crewweaver_uploads:/data -v "$PWD":/backup alpine tar czf /backup/crewweaver-uploads.tgz -C /data .
```

## Restore

Restore into an empty compatible database, restore uploads with their original randomized names, run migrations, then verify `/health` and a protected file download. Test restoration periodically outside production.

## Upgrade

Back up first, pull the new image, run `php bin/migrate.php`, restart the web service, and inspect health/logs. Migrations are forward-only. Never run development seeds in production.

## Attachment cleanup after scope deletion

CrewWeaver attempts to remove stored files immediately after a team or sub-team deletion commits. If the app reports queued files, resolve the storage error and retry from the web container.

```sh
docker compose exec -T web php bin/purge-deleted-attachments.php
```

