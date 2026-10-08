# Local development database only. Production uses the goneo MySQL server.
FROM mysql:8.4

# Copied (not bind-mounted) so the file is non-executable and gets sourced by
# the entrypoint, which provides docker_process_sql.
COPY --chmod=0644 init/ /docker-entrypoint-initdb.d/
