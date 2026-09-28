<?php

namespace App\Support;

use App\Models\Role;

/**
 * Permission catalogue and the default role matrix (docs/ARCHITECTURE.md §8).
 * Application code checks permissions, never role names.
 */
final class Permissions
{
    public const VIEW_DASHBOARD = 'view_dashboard';

    public const MANAGE_VOTERS = 'manage_voters';

    public const IMPORT_VOTERS = 'import_voters';

    public const VERIFY_VOTERS = 'verify_voters';

    public const MANAGE_ELECTIONS = 'manage_elections';

    public const MANAGE_POSITIONS = 'manage_positions';

    public const MANAGE_CANDIDATES = 'manage_candidates';

    public const OPEN_ELECTION = 'open_election';

    public const CLOSE_ELECTION = 'close_election';

    public const VIEW_LIVE_STATISTICS = 'view_live_statistics';

    public const VIEW_RESULTS = 'view_results';

    public const PUBLISH_RESULTS = 'publish_results';

    public const GENERATE_REPORTS = 'generate_reports';

    public const VIEW_AUDIT_LOGS = 'view_audit_logs';

    public const MANAGE_ADMINS = 'manage_admins';

    public const MANAGE_SYSTEM_SETTINGS = 'manage_system_settings';

    /** @return array<string, array{label:string, group:string}> */
    public static function catalogue(): array
    {
        return [
            self::VIEW_DASHBOARD => ['label' => 'View dashboard', 'group' => 'General'],
            self::MANAGE_VOTERS => ['label' => 'Manage voter register', 'group' => 'Voters'],
            self::IMPORT_VOTERS => ['label' => 'Import voter register', 'group' => 'Voters'],
            self::VERIFY_VOTERS => ['label' => 'Verify voters', 'group' => 'Voters'],
            self::MANAGE_ELECTIONS => ['label' => 'Manage elections and eligibility', 'group' => 'Elections'],
            self::MANAGE_POSITIONS => ['label' => 'Manage positions', 'group' => 'Elections'],
            self::MANAGE_CANDIDATES => ['label' => 'Manage candidates', 'group' => 'Elections'],
            self::OPEN_ELECTION => ['label' => 'Open elections', 'group' => 'Election control'],
            self::CLOSE_ELECTION => ['label' => 'Close elections', 'group' => 'Election control'],
            self::VIEW_LIVE_STATISTICS => ['label' => 'View live statistics', 'group' => 'Monitoring'],
            self::VIEW_RESULTS => ['label' => 'View results', 'group' => 'Results'],
            self::PUBLISH_RESULTS => ['label' => 'Calculate, verify and publish results', 'group' => 'Results'],
            self::GENERATE_REPORTS => ['label' => 'Generate reports', 'group' => 'Results'],
            self::VIEW_AUDIT_LOGS => ['label' => 'View audit logs and security alerts', 'group' => 'Audit'],
            self::MANAGE_ADMINS => ['label' => 'Manage administrators and roles', 'group' => 'Administration'],
            self::MANAGE_SYSTEM_SETTINGS => ['label' => 'Manage system settings', 'group' => 'Administration'],
        ];
    }

    /** @return array<string, array{label:string, description:string, permissions:list<string>}> */
    public static function defaultRoles(): array
    {
        return [
            Role::SUPER_ADMIN => [
                'label' => 'Super Admin',
                'description' => 'Full technical administration. Does not certify or publish results (separation of duties).',
                'permissions' => array_values(array_diff(array_keys(self::catalogue()), [self::PUBLISH_RESULTS])),
            ],
            Role::ELECTION_ADMINISTRATOR => [
                'label' => 'Election Administrator',
                'description' => 'Manages elections, positions, candidates and the voter register.',
                'permissions' => [
                    self::VIEW_DASHBOARD, self::MANAGE_VOTERS, self::IMPORT_VOTERS, self::VERIFY_VOTERS,
                    self::MANAGE_ELECTIONS, self::MANAGE_POSITIONS, self::MANAGE_CANDIDATES,
                    self::OPEN_ELECTION, self::CLOSE_ELECTION, self::VIEW_LIVE_STATISTICS,
                    self::VIEW_RESULTS, self::GENERATE_REPORTS,
                ],
            ],
            Role::RETURNING_OFFICER => [
                'label' => 'Returning Officer',
                'description' => 'Monitors the election, certifies and publishes results.',
                'permissions' => [
                    self::VIEW_DASHBOARD, self::OPEN_ELECTION, self::CLOSE_ELECTION, self::VIEW_LIVE_STATISTICS,
                    self::VIEW_RESULTS, self::PUBLISH_RESULTS, self::GENERATE_REPORTS, self::VIEW_AUDIT_LOGS,
                ],
            ],
            Role::AUDITOR => [
                'label' => 'Auditor',
                'description' => 'Read-only access to statistics, results, reports and audit records.',
                'permissions' => [
                    self::VIEW_DASHBOARD, self::VIEW_LIVE_STATISTICS, self::VIEW_RESULTS,
                    self::GENERATE_REPORTS, self::VIEW_AUDIT_LOGS,
                ],
            ],
        ];
    }
}
