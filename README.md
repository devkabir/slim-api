# Slim 4 Todo List CRUD API (with Memcached & MySQL)

A RESTful Todo List CRUD API built using **Slim 4**, **MySQL** (via PDO), and **Memcached** (Cache-Aside pattern).

---

## 🚀 Features

- **Slim 4 Framework**: Fast and lightweight PSR-7 / PSR-15 micro-framework.
- **MySQL Persistence**: PDO prepared statements for secure, SQL-injection safe CRUD operations.
- **Memcached Caching**: High-performance caching layer with cache invalidation on mutations (create, update, delete) and fallback if Memcached is unavailable.
- **CORS & JSON Middleware**: Cross-origin requests support & automatic JSON request body decoding.
- **Input Validation**: Clean validation for required fields and request payloads.

---

## 📁 Project Structure

```
.
├── composer.json               # Dependencies and PSR-4 autoloading
├── .env                        # Environment configuration (DB & Memcached credentials)
├── .env.example                # Example environment configuration
├── schema.sql                  # MySQL database and table schema
├── public/
│   ├── index.php               # Application entry point and route definitions
│   └── .htaccess               # Apache URL rewrite rules
└── src/
    ├── Config/
    │   ├── Database.php        # PDO MySQL connection
    │   └── Cache.php           # Memcached connection & helper methods
    ├── Controllers/
    │   └── TodoController.php  # Handles RESTful requests & responses
    ├── Models/
    │   └── Todo.php            # Todo entity model
    ├── Repositories/
    │   └── TodoRepository.php  # Direct MySQL query execution
    ├── Services/
    │   └── TodoService.php     # Business logic & Cache-Aside coordination
    └── Middleware/
        ├── JsonBodyParserMiddleware.php
        └── CorsMiddleware.php
```

---

## 🛠️ Setup & Installation

### 1. Database Setup
Create database and table:
```bash
mysql -u root < schema.sql
```

### 2. Dependencies
Install composer packages:
```bash
composer install
```

### 3. Environment Configuration
Check `.env` file for MySQL and Memcached connection settings:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=slim_todo_db
DB_USER=root
DB_PASS=

MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
MEMCACHED_TTL=3600
```

### 4. Run Development Server
```bash
php -S 127.0.0.1:8000 -t public
```

---

## 📡 API Endpoints

| Method | Endpoint | Description | Cache Behavior |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | Health check & API status | Checks MySQL & Memcached |
| `GET` | `/api/todos` | List all todos (filter `?completed=1` or `?completed=0`) | Cached in Memcached (`todo_list_*`) |
| `GET` | `/api/todos/{id}` | Get single todo by ID | Cached in Memcached (`todo_item_{id}`) |
| `POST` | `/api/todos` | Create a new todo | Writes to DB, warms item cache, invalidates list cache |
| `PUT` | `/api/todos/{id}` | Update an existing todo | Updates DB, invalidates item & list caches |
| `DELETE` | `/api/todos/{id}` | Delete a todo | Deletes from DB, invalidates item & list caches |

---

## 💡 cURL Examples

### 1. Health Check
```bash
curl -X GET http://127.0.0.1:8000/
```

### 2. Create a Todo
```bash
curl -X POST http://127.0.0.1:8000/api/todos \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Learn Slim Framework",
    "description": "Build a CRUD API with Memcached and MySQL",
    "completed": false
  }'
```

### 3. Get All Todos
```bash
curl -X GET http://127.0.0.1:8000/api/todos
```

### 4. Filter by Completion Status
```bash
# Get only completed
curl -X GET "http://127.0.0.1:8000/api/todos?completed=1"

# Get only pending
curl -X GET "http://127.0.0.1:8000/api/todos?completed=0"
```

### 5. Get a Specific Todo
```bash
curl -X GET http://127.0.0.1:8000/api/todos/1
```

### 6. Update a Todo
```bash
curl -X PUT http://127.0.0.1:8000/api/todos/1 \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Learn Slim Framework (Completed)",
    "completed": true
  }'
```

### 7. Delete a Todo
```bash
curl -X DELETE http://127.0.0.1:8000/api/todos/1
```
