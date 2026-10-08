#!/bin/bash
# Creates the separate database used by the automated test suite (see phpunit.xml).
# Sourced by the official MySQL entrypoint on first start, which provides docker_process_sql.
docker_process_sql --database=mysql <<-EOSQL
	CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_testing\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
	GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_testing\`.* TO '${MYSQL_USER}'@'%';
	FLUSH PRIVILEGES;
EOSQL
