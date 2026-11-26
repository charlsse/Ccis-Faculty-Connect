# FacultyConnect

FacultyConnect is the CCIS Faculty Club Management Portal built with Laravel and MySQL.

Security and deployment guidance for sensitive information is documented in
[Data Protection](docs/data-protection.md).

See [Final account workflow](docs/account-workflow.md) for administrator setup, temporary-credential invitations, officer roles, onboarding and recovery.

See [Water collections and QR payments](docs/water-collections.md) for the verified-payment workflow, private receipts, permissions, migration, setup and test results.

Run the automated suite with:

```bash
php artisan config:clear
php artisan test
```
