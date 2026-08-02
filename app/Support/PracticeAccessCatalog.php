<?php

namespace App\Support;

final class PracticeAccessCatalog
{
    /**
     * @return array<string, array{name: string, group: string, description: string}>
     */
    public static function permissions(): array
    {
        return [
            'administration.access' => self::permission('Access administration', 'Administration', 'Open the firm administration workspace.'),
            'firm-settings.manage' => self::permission('Manage firm settings', 'Administration', 'Maintain firm identity, currency, and practice-wide settings.'),
            'users.manage' => self::permission('Manage users', 'Administration', 'Create and update firm user accounts.'),
            'users.delete' => self::permission('Delete users', 'Administration', 'Permanently remove user accounts from the practice workspace.'),
            'departments.manage' => self::permission('Manage departments', 'Administration', 'Maintain departments and positions.'),
            'roles.manage' => self::permission('Manage roles', 'Access control', 'Create and maintain role templates.'),
            'permissions.manage' => self::permission('Manage permissions', 'Access control', 'Create and maintain permission definitions.'),
            'dashboard.view' => self::permission('View dashboard', 'Monitoring', 'View practice monitoring information.'),
            'reports.view' => self::permission('View reports', 'Monitoring', 'View practice and financial reports.'),
            'audit-logs.view' => self::permission('View audit logs', 'Monitoring', 'Review firm audit activity.'),
            'clients.view' => self::permission('View clients', 'Clients', 'View client contact and profile information.'),
            'clients.create' => self::permission('Create clients', 'Clients', 'Create new client records.'),
            'clients.update' => self::permission('Update clients', 'Clients', 'Update existing client records.'),
            'clients.configure' => self::permission('Configure clients', 'Clients', 'Manage client numbering and custom fields.'),
            'matters.view.all' => self::permission('View all matters', 'Matters', 'View all firm matters.'),
            'matters.view.department' => self::permission('View department matters', 'Matters', 'View matters assigned to the user’s department.'),
            'matters.view.assigned' => self::permission('View assigned matters', 'Matters', 'View matters where the user is responsible or a participant.'),
            'matters.create' => self::permission('Open matters', 'Matters', 'Open matters for clients.'),
            'matters.update' => self::permission('Update matters', 'Matters', 'Update matter details and status.'),
            'matters.close' => self::permission('Close matters', 'Matters', 'Close or reopen legal matters.'),
            'matters.participants.manage' => self::permission('Manage matter participants', 'Matters', 'Assign responsible attorneys and internal participants.'),
            'matters.configure' => self::permission('Configure matters', 'Matters', 'Manage matter numbering, custom fields, and matter types.'),
            'matter-types.manage' => self::permission('Manage matter types', 'Matters', 'Maintain matter-type configuration.'),
            'tasks.manage' => self::permission('Manage tasks and deadlines', 'Legal work', 'Create, update, assign, and complete matter tasks.'),
            'time-entries.create' => self::permission('Record time entries', 'Legal work', 'Record time spent on a matter.'),
            'time-entries.approve' => self::permission('Approve time entries', 'Legal work', 'Approve time entries for billing.'),
            'documents.view' => self::permission('View matter documents', 'Legal work', 'View documents on accessible matters.'),
            'documents.upload' => self::permission('Upload matter documents', 'Legal work', 'Upload documents to accessible matters.'),
            'documents.view.private' => self::permission('View private documents', 'Legal work', 'View private legal documents on accessible matters.'),
            'notes.view.internal' => self::permission('View internal notes', 'Legal work', 'View internal matter notes on accessible matters.'),
            'notes.create' => self::permission('Create notes', 'Legal work', 'Create internal matter notes.'),
            'emails.manage' => self::permission('Manage matter emails', 'Legal work', 'Compose and retain matter correspondence.'),
            'calendar.manage' => self::permission('Manage calendar events', 'Legal work', 'Create and update calendar events.'),
            'expenses.create' => self::permission('Record expenses', 'Legal work', 'Record matter expenses and receipts.'),
            'flat-fees.create' => self::permission('Create flat fees', 'Legal work', 'Create billable flat-fee charges.'),
            'company-files.view' => self::permission('View company files', 'Company drive', 'View accessible company-drive folders and files.'),
            'company-files.upload' => self::permission('Upload company files', 'Company drive', 'Upload files and create folders in the company drive.'),
            'company-files.manage' => self::permission('Manage company files', 'Company drive', 'Manage company-drive folders and files.'),
            'invoices.view' => self::permission('View invoices', 'Finance', 'View invoices and outstanding balances.'),
            'invoices.manage' => self::permission('Manage invoices', 'Finance', 'Create and update invoice drafts.'),
            'invoices.configure' => self::permission('Configure invoices', 'Finance', 'Manage invoice numbering, tax, and invoice templates.'),
            'payments.view' => self::permission('View payments', 'Finance', 'View recorded payments and payment plans.'),
            'payments.record' => self::permission('Record payments', 'Finance', 'Record invoice payments.'),
            'payment-plans.manage' => self::permission('Manage payment plans', 'Finance', 'Create and manage staged payment plans.'),
            'billing-items.manage' => self::permission('Manage billing items', 'Finance', 'Maintain billing-item configuration.'),
            'expense-categories.manage' => self::permission('Manage expense categories', 'Finance', 'Maintain expense-category configuration.'),
            'payment-methods.manage' => self::permission('Manage payment methods', 'Finance', 'Maintain payment-method configuration.'),
        ];
    }

    /**
     * @return array<string, array{description: string, permissions: list<string>|'*'}>
     */
    public static function roles(): array
    {
        return [
            'Super Admin' => ['description' => 'Full system access. Assign only to trusted firm system owners.', 'permissions' => '*'],
            'Firm Administrator' => ['description' => 'Manages firm setup, users, departments, and operational configuration.', 'permissions' => ['administration.access', 'firm-settings.manage', 'users.manage', 'departments.manage', 'dashboard.view', 'reports.view', 'clients.view', 'clients.create', 'clients.update', 'clients.configure', 'matters.view.all', 'matters.create', 'matters.update', 'matters.participants.manage', 'matters.configure', 'matter-types.manage', 'calendar.manage', 'company-files.view', 'company-files.upload', 'company-files.manage', 'invoices.view', 'billing-items.manage', 'expense-categories.manage', 'payment-methods.manage']],
            'Managing Partner' => ['description' => 'Firm-wide oversight of legal work, performance, and financial information.', 'permissions' => ['dashboard.view', 'reports.view', 'audit-logs.view', 'clients.view', 'clients.create', 'clients.update', 'matters.view.all', 'matters.create', 'matters.update', 'matters.close', 'matters.participants.manage', 'tasks.manage', 'time-entries.create', 'time-entries.approve', 'documents.view', 'documents.upload', 'documents.view.private', 'notes.view.internal', 'notes.create', 'emails.manage', 'calendar.manage', 'expenses.create', 'flat-fees.create', 'company-files.view', 'invoices.view', 'invoices.manage', 'payments.view']],
            'Department Head' => ['description' => 'Supervises matters and workload within the user’s legal department.', 'permissions' => ['dashboard.view', 'reports.view', 'clients.view', 'clients.create', 'clients.update', 'matters.view.department', 'matters.create', 'matters.update', 'matters.participants.manage', 'tasks.manage', 'time-entries.create', 'time-entries.approve', 'documents.view', 'documents.upload', 'documents.view.private', 'notes.view.internal', 'notes.create', 'emails.manage', 'calendar.manage', 'expenses.create', 'flat-fees.create', 'company-files.view', 'invoices.view']],
            'Attorney' => ['description' => 'Performs legal work on matters where they are responsible or a participant.', 'permissions' => ['dashboard.view', 'clients.view', 'clients.create', 'clients.update', 'matters.view.assigned', 'matters.create', 'matters.update', 'tasks.manage', 'time-entries.create', 'documents.view', 'documents.upload', 'documents.view.private', 'notes.view.internal', 'notes.create', 'emails.manage', 'calendar.manage', 'expenses.create', 'flat-fees.create', 'company-files.view']],
            'Paralegal & Clerk' => ['description' => 'Supports assigned legal matters, deadlines, documents, and time recording.', 'permissions' => ['dashboard.view', 'clients.view', 'matters.view.assigned', 'tasks.manage', 'time-entries.create', 'documents.view', 'documents.upload', 'notes.view.internal', 'notes.create', 'calendar.manage', 'expenses.create', 'company-files.view']],
            'Finance Manager' => ['description' => 'Controls billing, payments, payment plans, and financial configuration.', 'permissions' => ['dashboard.view', 'reports.view', 'clients.view', 'matters.view.all', 'invoices.view', 'invoices.manage', 'invoices.configure', 'payments.view', 'payments.record', 'payment-plans.manage', 'billing-items.manage', 'expense-categories.manage', 'payment-methods.manage']],
            'Finance Officer' => ['description' => 'Prepares invoices and records payments under finance supervision.', 'permissions' => ['dashboard.view', 'clients.view', 'matters.view.all', 'invoices.view', 'invoices.manage', 'payments.view', 'payments.record']],
            'Client Services' => ['description' => 'Manages client contact and relationship information without access to legal work.', 'permissions' => ['dashboard.view', 'clients.view', 'clients.create', 'clients.update', 'calendar.manage']],
            'ICT Support' => ['description' => 'Full system access for trusted ICT support staff who administer and support the practice platform.', 'permissions' => '*'],
            'Security Auditor' => ['description' => 'Reviews security and audit activity without client or legal-work access.', 'permissions' => ['audit-logs.view']],
        ];
    }

    /**
     * @return array{name: string, group: string, description: string}
     */
    private static function permission(string $name, string $group, string $description): array
    {
        return compact('name', 'group', 'description');
    }
}
