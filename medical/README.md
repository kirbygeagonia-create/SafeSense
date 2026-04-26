# Hospital Management System

A comprehensive hospital management system built with PHP for managing patients, doctors, and appointments.

## Features

- Patient Management (CRUD operations)
- Doctor Management (CRUD operations)
- Appointment Scheduling
- Responsive Web Interface
- Database Migrations and Seeding
- RESTful API Design

## Installation

1. Clone the repository
2. Navigate to the project directory
3. Run `composer install` to install dependencies
4. Create a database and configure `app/Config/database.php`
5. Run database migrations
6. Start the development server

## Project Structure

```
medical/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Views/
│   ├── Helpers/
│   └── Config/
├── public/
│   ├── css/
│   ├── js/
│   └── images/
├── database/
│   ├── migrations/
│   └── seeds/
├── composer.json
└── README.md
```

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer

## License

MIT License