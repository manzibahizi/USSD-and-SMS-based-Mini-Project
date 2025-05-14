# AccessCare USSD System

A USSD-based healthcare appointment management system that allows patients to book appointments with doctors and doctors to manage their appointments. Built specifically for Africa's Talking USSD and SMS services.

It is developed by ##MANZI BAHIZI Bertin and DUSINGIZIMANA Innocent.

## Features

- Patient Registration and Authentication
- Doctor Registration and Profile Management
- Appointment Booking System
- Appointment Management (Approve/Reject)
- SMS Notifications via Africa's Talking
- Doctor Availability Management
- Specialization Management

## Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Africa's Talking Account and API Key

## Installation

1. Clone the repository to your web server directory:
```bash
git clone [repository-url]
cd accesscare-ussd
```

2. Create a MySQL database:
```bash
mysql -u root -p
CREATE DATABASE accesscare_db;
exit;
```

3. Run the database migrations:
```bash
php migrate.php
```

4. Configure Africa's Talking settings in `ussd_handler.php`:
```php
define('API_KEY', 'your_africas_talking_api_key');
define('ALPHANUMERIC_CODE', 'your_alphanumeric_code');
```

5. Set up Africa's Talking USSD Service:
   - Log in to your Africa's Talking account
   - Go to USSD > Create USSD Service
   - Set the following:
     - Service Code: *384*0722#
     - Callback URL: https://your-domain.com/ussd_handler.php
     - HTTP Method: POST

6. Set up Africa's Talking SMS Service:
   - Go to SMS > Settings
   - Enable SMS
   - Set your Alphanumeric Sender ID

## Usage

### For Patients

1. Dial *384*0722# to access the system
2. Register as a patient if you haven't already
3. Use the menu to:
   - Book appointments
   - View your appointments
   - View available doctors

### For Doctors

1. Dial *384*0722# to access the system
2. Register as a doctor if you haven't already
3. Use the menu to:
   - View pending appointments
   - Approve or reject appointments
   - Update your profile and availability
   - View approved and rejected appointments

## SMS Notifications

The system sends SMS notifications via Africa's Talking for:
- Appointment booking confirmation
- Appointment approval
- Appointment rejection

## Database Migrations

The system uses a migration system to manage database changes. To run migrations:

```bash
php migrate.php
```

This will:
1. Create necessary tables if they don't exist
2. Add required indexes
3. Insert default data
4. Track executed migrations

## Security

- All database queries use prepared statements to prevent SQL injection
- Phone numbers are validated before processing
- API keys and sensitive data are stored securely
- Africa's Talking API authentication is implemented

## Support

For technical support or questions, please contact:
[Your Contact Information]

## License

[Your License Information] 
