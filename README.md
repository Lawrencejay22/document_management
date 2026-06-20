# DocHub: Document Management System Guide

## Overview
DocHub is a fully-functional, dynamic Document Management System built with **PHP**, **MySQL**, **HTML5**, **CSS3**, and **JavaScript**. It is designed to securely track documents, manage user access across different departments, and provide powerful administrative tools.

## System Architecture

### 1. Database Schema (MySQL)
The system is powered by a relational database (`document_management`) with three core tables:
- **`users`**: Stores user credentials, roles (Admin, Public, Faculty Only, HR Only, etc.), and profile pictures. Passwords are securely hashed using PHP's `password_hash()`.
- **`documents`**: Stores document metadata, including the file path, category (Public, Private, or Department-Specific), uploader ID, file size, and an optional `document_password` for private documents.
- **`messages`**: Facilitates internal communication by storing sender, receiver, and message text.

### 2. Authentication & User Management
- **Login/Registration (`login.php`, `register.php`)**: Secure authentication system with PDO prepared statements to prevent SQL injection.
- **User Profiles (`profile.php`)**: Users can update their profile pictures and view their own uploaded documents.
- **Role-Based Access Control**: Sessions (`$_SESSION`) are used to track logged-in users. Roles determine what documents a user can see and whether they can access the Admin Dashboard.

### 3. Document Tracking (`document-track.php`)
The core dashboard where users interact with documents. Features include:
- **Dynamic File Uploads**: Users can upload files, which are securely moved to an `/uploads` directory.
- **Access Visibility**: Documents can be set to Public, Private (password-protected), or restricted to specific departments (e.g., Faculty Only).
- **Password Protection**: Private documents require a password to view or download.
- **Real-Time Filtering**: Client-side JavaScript (`document-track.js`) allows users to instantly search documents by title or filter by category.
- **Secure File Downloading**: Files are served through secure mechanisms, preventing unauthorized direct access.

### 4. Admin Dashboard (`admin_dashboard.php`)
A restricted area exclusively for users with the "Admin" role. It provides a high-level overview of the system:
- **Live Statistics**: Displays total documents, public/private/internal splits, and user counts dynamically using SQL aggregations.
- **Visual Analytics**: Interactive doughnut charts (powered by Chart.js) visualizing Disk Space Usage, File Categories, and Top Users.
- **User Management**: Admins can view all registered users and permanently delete accounts.
- **Global Document Control**: Admins have oversight of all documents uploaded across the platform and can delete any file.

### 5. Security Implementations
- **SQL Injection Prevention**: All database queries strictly use PDO Prepared Statements.
- **XSS Protection**: `htmlspecialchars()` is heavily utilized to sanitize user-generated content before rendering it in HTML.
- **Authentication Checks**: Every protected page verifies the user's session before loading.
- **Secure Passwords**: All user passwords and private document passwords are cryptographically hashed.

---
*Last Updated: System successfully migrated from static HTML to dynamic PHP/MySQL.*
