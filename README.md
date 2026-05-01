 # AutoShare — REST API Backend

RESTful PHP backend for AutoShare, a full-stack car rental platform.

## Tech Stack
- PHP
- MariaDB
- Docker Compose
- JWT Authentication
- FastRoute

## Features
- User registration and login with JWT
- Forgot password and reset password flow
- Profile update and password change
- Car create, edit and delete for owners
- Car filtering and pagination
- Booking creation with overlap prevention
- Internal messaging between users
- Admin role management

## Run

```bash
docker compose up --build
```

Then import `database/autoshare.sql` into the `autoshare` database.

- API: `http://localhost/api`
- phpMyAdmin: `http://localhost:8080`

## Demo Accounts
- customer: `sara@customer.com` / `12345678`
- owner: `salma@owner.com` / `123456`
- admin: `aziz@admin.com` / `123456`

## Frontend
See [autoshare-frontend](https://github.com/elcoleta/autoshare-frontend)
