# 🔄 SkillSwap

A PHP-based web platform that connects people to **exchange skills** with one another — no money involved, just mutual learning. Users can offer skills they know and request skills they want to learn, match with others, and communicate via real-time video calls.

---

## ✨ Features

- 🔐 **Authentication** — Secure user registration, login, and session management
- 👤 **User Profiles** — Upload profile pictures, list skills offered and skills wanted
- 🔍 **Skill Matching** — Browse and discover users with complementary skills
- 📬 **Email Notifications** — Automated emails via PHPMailer (e.g., swap requests, confirmations)
- 📹 **Video Calling** — Built-in video call feature for real-time skill sessions
- 🛠️ **Admin Panel** — Manage users, monitor activity, and maintain platform integrity
- 🎨 **Animated UI** — Dynamic animated background for an engaging user experience

---

## 🗂️ Project Structure

```
skill_swap_public/
├── Auth/                   # Login, registration, logout logic
├── admin/                  # Admin dashboard and management pages
├── user/                   # User dashboard and profile pages
├── db/                     # Database connection and queries
├── includes/
│   └── scripts/            # Shared PHP includes and JS scripts
├── mailSender/             # PHPMailer email sending logic
├── videoCall/              # Video call implementation
├── uploads/
│   └── profiles/           # User uploaded profile pictures
├── assets/                 # CSS, images, and other static assets
├── vendor/                 # Composer dependencies
├── animated-bg.php         # Animated background component
├── index.php               # Application entry point
├── composer.json           # PHP dependency manifest
└── composer.lock           # Locked dependency versions
```

---

## 🛠️ Tech Stack

| Layer       | Technology                        |
|-------------|-----------------------------------|
| Backend     | PHP                               |
| Frontend    | HTML, CSS, JavaScript             |
| Database    | MySQL (via PHP MySQLi/PDO)        |
| Email       | PHPMailer `^7.0`                  |
| Video       | WebRTC / custom video call module |
| Dependency Manager | Composer                 |

---

## 🚀 Getting Started

### Prerequisites

- PHP `>= 7.4`
- MySQL or MariaDB
- [Composer](https://getcomposer.org/)
- A local server like **XAMPP**, **WAMP**, or **Laragon**

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/MihirAmin2006/skill_swap_public.git
   cd skill_swap_public
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Set up the database**
   - Create a new MySQL database (e.g., `skill_swap`)
   - Import the SQL schema from the `db/` folder
   - Update your database credentials in the connection file inside `db/`

4. **Configure mail settings**
   - Open the mail configuration in `mailSender/`
   - Enter your SMTP credentials (Gmail, Mailtrap, etc.)

5. **Run the application**
   - Place the project in your local server's web root (e.g., `htdocs/` for XAMPP)
   - Visit `http://localhost/skill_swap_public/` in your browser

---

## 👨‍💻 Author

**Mihir Amin**  
GitHub: [@MihirAmin2006](https://github.com/MihirAmin2006)
