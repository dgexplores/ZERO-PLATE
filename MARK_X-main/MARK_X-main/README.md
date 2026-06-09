# ZeroPLATE — Food Recovery & Surplus Marketplace

![ZeroPLATE](img/coverimage.jpeg)

A comprehensive web-based system designed to collect excess/leftover food from donors (hotels, restaurants, marriage halls, etc.) and distribute it to needy people, reducing food waste and helping communities.

## 📋 Table of Contents

- [Overview](#overview)
- [Project docs](#project-docs)
- [Features](#features)
- [Technologies Used](#technologies-used)
- [System Modules](#system-modules)
- [Installation](#installation)
- [GitHub Deployment](#github-deployment)
- [Live Hosting Options](#live-hosting-options)
- [Deployment Paths (New)](#deployment-paths-new)
- [Security Features](#security-features)
- [Project Structure](#project-structure)
- [Usage](#usage)
- [Contributing](#contributing)

## 📚 Project Docs

- [README.md](README.md) - complete project documentation, setup, modules, and deployment notes.
- [STARTUP_PITCH_20_SLIDES.md](STARTUP_PITCH_20_SLIDES.md) - ready-to-use 20-slide presentation layout.

## 🎯 Overview

ZeroPLATE is a multi-role platform that efficiently manages food donations and surplus sales from listing to delivery. The system connects donors/restaurants with NGOs/processors and delivery partners to ensure food reaches the right destination on time.

## ✨ Features

- **Mobile Responsive Design** - Works seamlessly on all devices
- **Help chatbot (FAQ)** - Rule-based assistant with optional text-to-speech on the contact page (not a third-party LLM)
- **Secure Login System** - Password hashing and session management
- **Five User Roles** - User, Admin, Delivery, NGO, and Processor modules
- **SLA Escalation + Auto-Routing** - Overdue NGO offers auto-escalate to processors
- **Charge Ledger Settlement** - Admin can mark platform/service charges as paid
- **Food Donation Tracking** - Complete donation management system
- **Analytics Dashboard** - Admin analytics and reporting
- **Location-based Matching** - Connects donors with nearby organizations

## 🛠 Technologies Used

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Web Server:** Apache (XAMPP/WAMP/LAMP)
- **Security:** Password hashing, SQL injection protection, input validation

## 📦 System Modules

### 1. User Module
The User module allows individuals and organizations to donate food:
- User registration and authentication
- Food donation form with category selection (raw, cooked, packed)
- Donation history tracking
- Profile management

### 2. Admin Module
The Admin module is for platform regulation:
- Admin registration and dashboard
- View and manage food donations
- Assign donations to delivery partners
- Analytics and reporting
- Feedback management

### 3. NGO / Processor Module
Organizations can register and operate from dedicated interfaces:
- Organization registration/login (`org/signup.php`, `org/login.php`)
- Smart donation feed by city + recommendation (`ngo` vs `processor`)
- Accept/reject donations with notes and charge records
- Delivery/service charge and platform fee tracking
- SLA-driven escalation from NGO feed to processor feed for stale offers

### 4. Automation / Ops
- `api/automation_tick.php` can run escalation logic on demand or via cron
- Protect cron execution using `MARK16_AUTOMATION_KEY`
- Tune SLA with `MARK16_ORG_SLA_MINUTES` (default 45)

### 5. Delivery Module
The Delivery Person module for pickup and delivery services:
- Delivery person registration
- View assigned donations
- Track pickup and drop locations
- Order management

## 🚀 Installation

### Prerequisites
- XAMPP/WAMP/LAMP installed
- PHP 7.4 or higher
- MySQL/MariaDB
- Web browser

### Local Setup (XAMPP)

1. **Clone or Download the Repository**
   ```bash
   git clone https://github.com/yourusername/food-waste-management.git
   ```
   Or download the ZIP file and extract it.

2. **Copy to Web Server Directory**
   - **XAMPP:** Copy folder to `C:\xampp\htdocs\MARK_16`
   - **WAMP:** Copy folder to `C:\wamp\www\MARK_16`
   - **LAMP:** Copy folder to `/var/www/html/MARK_16`

3. **Start Services**
   - Open XAMPP Control Panel
   - Start **Apache** service
   - Start **MySQL** service

4. **Database Setup**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database named `demo`
   - Select the `demo` database
   - Click "Import" tab
   - Choose file: `database/demo.sql`
   - Click "Go" to import

5. **Database Configuration**
   - Defaults in `connection.php` match typical XAMPP (`localhost`, `root`, empty password, database `demo`).
   - For another environment, copy `connection.local.php.example` to `connection.local.php` and set host, user, password, database, and optional port. That file is gitignored.
   - On managed hosting you can instead set environment variables: `MARK16_DB_HOST`, `MARK16_DB_USER`, `MARK16_DB_PASSWORD`, `MARK16_DB_NAME`, `MARK16_DB_PORT`.

6. **Verify deployment**
   - From the project folder run:
     ```bash
     php deploy_check.php
     ```
   - This confirms the database connection, required tables, and creates the `password_resets` table if missing (used for **forgot password**). For a browser-based check, set `MARK16_DEPLOY_CHECK_KEY` in the server environment and open `deploy_check.php?key=YOUR_SECRET`.

**Email (optional):** Set `MARK16_MAIL_FROM` (e.g. `noreply@yourdomain.com`) and ensure the host can send mail (`mail()` or configure sendmail/SMTP). Donation status emails are sent when delivery updates status or an admin assigns a partner.

7. **Access the Application**
   - Open browser: `http://localhost/MARK_16/`
   - You should see the welcome page

**Command-line import (optional, XAMPP Windows example):**
```bat
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS demo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
C:\xampp\mysql\bin\mysql.exe -u root demo < database\demo.sql
```

### Quick Start Commands

```bash
# For XAMPP (Windows)
# 1. Copy project to C:\xampp\htdocs\
# 2. Start Apache and MySQL from XAMPP Control Panel
# 3. Access: http://localhost/MARK_16/

# For Linux/Mac (LAMP)
sudo cp -r MARK_16 /var/www/html/
sudo systemctl start apache2
sudo systemctl start mysql
# Access: http://localhost/MARK_16/
```

## 📤 GitHub Deployment

### ⚠️ Important Note About GitHub Pages

**GitHub Pages does NOT support PHP applications.** GitHub Pages only hosts static websites (HTML, CSS, JavaScript). Since this project uses PHP and MySQL, you have two options:

### Option 1: Host Code on GitHub (Version Control Only)

Use GitHub to store and version control your code, but host it elsewhere:

1. **Create a GitHub Repository**
   ```bash
   git init
   git add .
   git commit -m "Initial commit: Food Waste Management System"
   git branch -M main
   git remote add origin https://github.com/yourusername/food-waste-management.git
   git push -u origin main
   ```

2. **Add .gitignore** (already included)
   ```
   error_log.txt
   *.log
   .htaccess
   config.php
   ```

3. **Update connection.php for Production**
   - Never commit production database credentials
   - Use environment variables or separate config files

### Option 2: Deploy to Free PHP Hosting

Deploy your code to a free PHP hosting service:

#### Recommended Free PHP Hosting Services:

1. **000webhost** (https://www.000webhost.com/)
   - Free PHP hosting with MySQL
   - Steps:
     - Sign up for free account
     - Create a new website
     - Upload files via File Manager or FTP
     - Create database in cPanel
     - Import `database/demo.sql`
     - Update `connection.php` with hosting credentials

2. **InfinityFree** (https://www.infinityfree.net/)
   - Free unlimited hosting
   - PHP and MySQL support
   - Steps similar to 000webhost

3. **Heroku** (with PHP buildpack)
   - Free tier available
   - Requires some configuration
   - Good for learning deployment

4. **Railway** (https://railway.app/)
   - Free tier available
   - Supports PHP applications

#### Deployment Steps for Free Hosting:

1. **Prepare Files**
   - Ensure all files are ready
   - Update `connection.php` with hosting database credentials
   - Remove or update `config.php` for production

2. **Upload Files**
   - Use FTP client (FileZilla) or hosting File Manager
   - Upload all files to `public_html` or `www` folder

3. **Database Setup**
   - Create database in hosting cPanel
   - Import `database/demo.sql`
   - Note database credentials

4. **Update Configuration**
   - Edit `connection.php`:
     ```php
     $db_host = "your_host"; // e.g., localhost or provided host
     $db_username = "your_username";
     $db_password = "your_password";
     $db_name = "your_database_name";
     ```

5. **Test the Application**
   - Visit your hosting URL
   - Test all modules

## 🚀 Deployment Paths (New)

Use these ready-made folders for safer deployment without touching your main app files.

### Path A: Vercel Frontend + PHP Backend (Recommended Hybrid)

Use folder:

- `vercel-ready/`

What it does:

- Deploys a static launch frontend on Vercel
- Sends users to your real PHP backend pages

Steps:

1. Deploy backend first (Hostinger/cPanel/Railway/VPS), example:
   - `https://your-backend-domain.com/MARK_16`
2. Open:
   - `vercel-ready/public/config.js`
3. Set:
   - `backendBaseUrl` to your backend URL
4. Deploy `vercel-ready` to Vercel
5. Open Vercel URL and verify buttons route to backend

### Path B: Full PHP Hosting (Single Host)

Use docs in:

- `cpanel-ready/README.md`
- `cpanel-ready/.env.local.production.example`
- `cpanel-ready/DEPLOY_SMOKE_TEST.md`

Steps:

1. Upload full `MARK_16` folder to hosting (`public_html/MARK_16`)
2. Create MySQL DB and import `database/demo.sql` (or latest export)
3. Update `connection.php` DB credentials
4. Create `.env.local` from production example
5. Set cron for automation:
   - `api/automation_tick.php?key=YOUR_AUTOMATION_KEY`
6. Run smoke test checklist

### Which one should I use?

- Want simple cloud frontend + existing PHP backend: **Path A**
- Want complete app on one host: **Path B**

### Important note

Vercel alone is not enough for this project because business APIs/auth are PHP+MySQL.

## 🌐 Live Hosting Options

### Free Hosting Services Comparison

| Service | PHP Support | MySQL | Free Tier | Best For |
|---------|------------|-------|-----------|----------|
| 000webhost | ✅ | ✅ | Yes | Beginners |
| InfinityFree | ✅ | ✅ | Yes | Small projects |
| Heroku | ✅ | ✅ (Add-on) | Limited | Learning |
| Railway | ✅ | ✅ | Limited | Modern apps |

### Paid Hosting (Recommended for Production)

- **Hostinger** - Affordable shared hosting
- **Bluehost** - Reliable PHP hosting
- **SiteGround** - Good performance
- **DigitalOcean** - VPS hosting (more control)

## 🔒 Security Features

- ✅ SQL Injection Protection
- ✅ Password Hashing (bcrypt)
- ✅ Input Validation & Sanitization
- ✅ Session Security
- ✅ XSS Protection Headers
- ✅ Secure Redirects
- ✅ Error Handling

## 📁 Project Structure

```
MARK_16/
├── admin/                 # Admin module
│   ├── admin.php         # Admin dashboard
│   ├── analytics.php     # Analytics page
│   ├── connect.php       # Admin connection
│   ├── donate.php        # Donation management
│   ├── feedback.php      # Feedback management
│   ├── login.php         # Admin login
│   ├── signin.php        # Admin sign in
│   ├── signup.php        # Admin registration
│   └── adminprofile.php  # Admin profile
├── chatbot/              # Chatbot functionality
│   ├── chatbot.js
│   ├── constants.js
│   └── speech.js
├── database/             # Database files
│   └── demo.sql         # Database schema
├── vercel-ready/         # Vercel frontend deployment package
├── cpanel-ready/         # Full PHP hosting deployment guides/templates
├── delivery/             # Delivery module
│   ├── delivery.php     # Delivery dashboard
│   ├── deliverylogin.php
│   ├── deliverysignup.php
│   └── deliverymyord.php
├── img/                  # Images and assets
├── config.php            # Configuration file
├── connection.php         # Database connection
├── .htaccess            # Apache security config
├── index.html           # Entry point
├── signup.php          # User registration
├── signin.php          # User login
├── login.php           # Login handler
├── profile.php         # User profile
├── fooddonateform.php  # Food donation form
├── feedback.php       # Feedback form
├── logout.php         # Logout handler
├── README.md          # This file
└── STARTUP_PITCH_20_SLIDES.md # 20-slide pitch outline
```

## 💻 Usage

### For Users:
1. Register/Login at `signup.php` or `signin.php`
2. Fill food donation form
3. View donation history in profile

### For Admins:
1. Register/Login at `admin/signup.php` or `admin/signin.php`
2. View donations in dashboard
3. Manage and assign donations
4. View analytics

### For Delivery Persons:
1. Register/Login at `delivery/deliverysignup.php` or `delivery/deliverylogin.php`
2. View assigned orders
3. Track pickup and delivery

## 🔧 Configuration

### Database Configuration (`connection.php`)
```php
$db_host = "localhost";
$db_username = "root";
$db_password = "";
$db_name = "demo";
```

### Error Reporting (`config.php`)
- Development: `error_reporting(E_ALL)`
- Production: `error_reporting(0)`

## 📝 Default Credentials

**Note:** After first deployment, create accounts through the registration forms. No default accounts are provided for security reasons.

## 🐛 Troubleshooting

### Database Connection Error
- Check MySQL service is running
- Verify database name is `demo`
- Check credentials in `connection.php`

### Page Not Found (404)
- Verify files are in correct directory
- Check Apache service is running
- Clear browser cache

### Session Issues
- Ensure `session_start()` is called
- Check PHP session configuration
- Verify file permissions

### Images Not Loading
- Check `img/` folder exists
- Verify image paths
- Check file permissions

## 📄 License

This project is open source and available for educational purposes.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📞 Support

For issues, questions, or contributions:
- Open an issue on GitHub
- Re-run `php deploy_check.php` after setup changes
- Review error logs in `error_log.txt` (if configured)

## 🙏 Acknowledgments

- Food donation concept inspired by community needs
- Built with modern web technologies
- Security best practices implemented

---

**⭐ If you find this project helpful, please give it a star on GitHub!**

**📌 Remember:** This is a PHP application and requires a PHP-enabled server. GitHub Pages will NOT work. Use free PHP hosting services or local development with XAMPP.

