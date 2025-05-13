# ConnectMe

## About
ConnectMe is a social networking application built with Laravel and Angular. Users can create accounts, share posts, connect with friends, and communicate through messages.

## Tech Stack
- Backend: Laravel 12.13.0 (PHP 8.2.28)
- Frontend: Angular
- Database: MySQL 8.0
- Environment: Docker

## Setup Instructions

1. Clone the repository
```bash
git clone https://github.com/douaeelassal/connectme.git
cd connectme
```

2. Start Docker containers
```bash
docker compose up -d
```

3. Set up the database
```bash
docker exec -it connectme-backend bash
cd /var/www/html
php artisan migrate
php artisan db:seed
```

4. Access the application
- Frontend: http://localhost:4201
- Backend API: http://localhost:8000
- Database: http://localhost:8080
  - Username: connectme
  - Password: password

## Features
- User authentication
- Post creation and interaction
- Comments and likes
- Friend requests
- Messaging
- Mobile-responsive design

## Project Structure
```
connectme/
├── backend/      # Laravel backend
├── frontend/     # Angular frontend
└── docker-compose.yml
```

## Database Tables
- users
- posts
- comments
- likes
- friend_requests
- messages

---

© 2025 ConnectMe
