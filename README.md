# HamroSewa - Community Engagement Platform

A unified platform for community engagement, blood donation camps, volunteer tasks, campaigns, and polls.

## Tech Stack

- **Frontend**: React 18
- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Authentication**: JWT

## Setup Instructions

### Database Setup

1. Import the database schema:
```bash
mysql -u root -p < database/schema.sql
```

2. Update database credentials in `backend/config/database.php` if needed.

### Backend Setup

1. The backend is located in the `backend/` directory
2. Ensure PHP is installed and Apache/nginx is configured
3. Update `backend/config/config.php` with your settings:
   - JWT_SECRET (change in production)
   - CORS_ORIGIN (your frontend URL)
   - Email settings (for verification)

### Frontend Setup

1. Navigate to the frontend directory:
```bash
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Create a `.env` file:
```
REACT_APP_API_URL=http://localhost/backend
```

4. Start the development server:
```bash
npm start
```

## Project Structure

```
hackathon/
├── backend/
│   ├── api/          # API endpoints
│   ├── config/       # Configuration files
│   └── utils/        # Utility functions
├── database/
│   └── schema.sql    # Database schema
├── frontend/
│   ├── public/
│   └── src/
│       ├── components/
│       ├── pages/
│       └── utils/
└── README.md
```

## Features

- ✅ User authentication (signup/login)
- ✅ Role-based access (user, organization, admin)
- ✅ Blood donation camp management
- ✅ Volunteer task board
- ✅ Community campaigns with token support
- ✅ Polls and voting
- ✅ Organization verification
- ✅ Notifications system
- ✅ Admin dashboard

## API Endpoints

### Authentication
- `POST /api/auth/signup` - User registration
- `POST /api/auth/login` - User login

### Users
- `GET /api/users/me` - Get current user
- `PATCH /api/users/me` - Update user profile

### Camps
- `GET /api/camps` - List camps
- `POST /api/camps` - Create camp (org only)
- `POST /api/camps/join` - Join camp

### Tasks
- `GET /api/tasks` - List tasks
- `POST /api/tasks` - Create task (org only)
- `POST /api/tasks/apply` - Apply for task

### Campaigns
- `GET /api/campaigns` - List campaigns
- `POST /api/campaigns` - Create campaign (org only)
- `POST /api/campaigns/support` - Support campaign

### Polls
- `GET /api/polls` - List polls
- `POST /api/polls` - Create poll
- `POST /api/polls/vote` - Vote on poll

### Organizations
- `GET /api/organizations` - List organizations
- `POST /api/organizations` - Create organization
- `POST /api/organizations/verify` - Verify organization (admin only)

### Notifications
- `GET /api/notifications` - Get notifications
- `PATCH /api/notifications` - Mark as read

## Default Admin

To create an admin user, you can either:
1. Manually insert into the database
2. Sign up as a regular user and update the role in the database

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

## Development Notes

- The backend uses PDO for database operations
- JWT tokens are used for authentication
- CORS is configured for local development
- All API responses are in JSON format

## License

MIT

