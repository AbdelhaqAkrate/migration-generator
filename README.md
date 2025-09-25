# 🚀 Migration Generator (Laravel)

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-9+-red.svg)](https://laravel.com/)

> ⚠️ **Under Development** – If You discovered any bug or issue please inform me to improve it , TY :D

A simple Laravel package to scaffold **migrations**, **models**, **repositories**, **managers**, and **services** interactively.

---

## 📖 Table of Contents

* [Introduction](#✨-introduction)
* [Installation](#-installation)
* [Configuration](#-configuration)
* [Commands](#-available-commands)

  * [Generate Migration](#1️⃣-generate-migration)
  * [Make Structure](#2️⃣-make-structure)
* [Features](#-features)
* [Troubleshooting](#-troubleshooting)
* [Contributors](#-contributors)
* [License](#-license)

---

## ✨ Introduction

The **Migration Generator** package simplifies repetitive scaffolding in Laravel:

* **`generate:migration`** – Step-by-step interactive migration generator.
* **`make:structure`** – Auto-create a **Model**, **Repository**, **Manager**, and **Service** for a table.

Reduces boilerplate and speeds up development.

---

## ⚙️ Installation

### ✅ Option 1: Install via GitHub

Add the repository to your `composer.json`:

```json
"require": {
    "migra-vendor/migration-generator": "dev-main"
},
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/AbdelhaqAkrate/migration-generator.git"
    }
]
```

Install with Composer:

```bash
composer require migra-vendor/migration-generator:dev-main
```

### 🧪 Option 2: Install Locally

Clone the repository locally and add it to `composer.json`:

```json
"repositories": [
  {
    "type": "path",
    "url": "path/to/cloned/repo",
    "options": {
      "symlink": true
    }
  }
]
```

Install with Composer:

```bash
composer require migra-vendor/migration-generator:dev-main
```

### ⚠️ Manual Provider Registration

If Laravel doesn’t auto-discover, add to `config/app.php`:

```php
'providers' => [
    MigraVendor\MigrationGenerator\MigrationGeneratorServiceProvider::class,
],
```

#### Publish Stubs (Optional)

```bash
php artisan vendor:publish --provider="MigrationGeneratorServiceProvider"
```

---

## 🔧 Configuration

Customize migration stubs via `config/migration-generator.php`.
If missing:

```bash
php artisan vendor:publish --provider="MigrationGeneratorServiceProvider"
```

---

## 🛠️ Available Commands

### 1️⃣ Generate Migration

Run the interactive migration generator:

```bash
php artisan generate:migration
```

**Flow:**

1. Enter table name
2. Add ID column? (default: yes)
3. Define columns:

```bash
column_name:column_type[|n][|u][|f:table:column][|d:value]
```

**Modifiers:**

* `n` → nullable
* `u` → unique
* `f:table:column:c:r` → foreign key (cascade/restrict)
* `d:value` → default value

**Help Command:**

Type `help` at any prompt:

```
📘 Supported Column Types:
- string, integer, unsignedBigInteger, text, boolean, decimal(8,2), date, datetime, timestamps, etc.

🔧 Modifiers:
- |n   → nullable
- |u   → unique
- |d:0 → default value
- |f:table:column:c:r → foreign key
  - c = cascade on delete
  - r = restrict on update

📌 Examples:
- name:string|n|u
- price:decimal(8,2)|d:0.00
- user_id:unsignedBigInteger|f:users:id:c:r
```

---

### 2️⃣ Make Structure

Generate a **Model**, **Repository**, **Manager**, and **Service**:

```bash
php artisan make:structure users
```

**Output (App/Models/Users/):**

* `User.php` (Model)
* `UserRepository.php`
* `UserManager.php`
* `UserService.php`

> Automatically generates constants and getter methods based on your database schema.

---

## 🌟 Features

* Interactive migration generator
* Auto-generated project structure
* Supports foreign keys, defaults, nullable, and unique constraints
* Laravel-native column types
* Reduces repetitive boilerplate

---

## 🐛 Troubleshooting

* Ensure Laravel auto-discovery works
* Check `config/migration-generator.php` for stub paths
* Column definitions must follow the correct format

---

## 👥 Contributors

* Abdelhaq Akrate – [GitHub](https://github.com/AbdelhaqAkrate)

---
