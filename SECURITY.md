# Security policy

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report suspected vulnerabilities confidentially to the project maintainers
(contact address to be defined by Gemeinde Merching – see open issues in the
project documentation). Include a description, affected URL/component and
steps to reproduce. We aim to acknowledge reports within five working days.

A `/.well-known/security.txt` will be published once the official contact is
defined.

## Supported versions

Only the currently deployed `main` branch is supported.

## Handling of secrets

- Credentials, keys and tokens live only in the server's `.env` (outside the
  web root, `chmod 600`) and in the municipality's password manager – never
  in Git, tickets, chat or logs.
- If a secret may have leaked: rotate it immediately (database password, SMTP
  password; `APP_KEY` only with `APP_PREVIOUS_KEYS` and a re-encryption plan,
  see docs/security.md), end all sessions (`DELETE FROM sessions`) and review
  the audit log.

## Security design

See [docs/security.md](docs/security.md) for authentication, MFA, session,
CSP, logging and upload controls.
