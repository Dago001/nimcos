<?php

namespace App\Services\Audit;

/** Canonical audit action names (spec §25). */
final class AuditAction
{
    // Voter authentication
    public const VOTER_LOOKUP_FAILED = 'voter.lookup_failed';

    public const OTP_ISSUED = 'voter.otp_issued';

    public const OTP_DELIVERY_FAILED = 'voter.otp_delivery_failed';

    public const OTP_VERIFIED = 'voter.otp_verified';

    public const OTP_FAILED = 'voter.otp_failed';

    public const OTP_RATE_LIMITED = 'voter.otp_rate_limited';

    public const VOTER_LOGOUT = 'voter.logout';

    // Voting
    public const VOTING_SESSION_STARTED = 'voting.session_started';

    public const VOTING_SESSION_REVOKED = 'voting.session_revoked';

    public const VOTE_CAST = 'voting.ballot_cast';

    public const VOTE_REJECTED = 'voting.ballot_rejected';

    public const DUPLICATE_VOTE_ATTEMPT = 'voting.duplicate_attempt';

    public const VOTE_REPLAYED = 'voting.idempotent_replay';

    // Admin authentication
    public const ADMIN_LOGIN = 'admin.login';

    public const ADMIN_LOGIN_FAILED = 'admin.login_failed';

    public const ADMIN_LOCKED = 'admin.locked_out';

    public const ADMIN_LOGOUT = 'admin.logout';

    public const ADMIN_MFA_FAILED = 'admin.mfa_failed';

    public const ADMIN_MFA_ENABLED = 'admin.mfa_enabled';

    public const ADMIN_MFA_DISABLED = 'admin.mfa_disabled';

    public const ADMIN_PASSWORD_CHANGED = 'admin.password_changed';

    public const REAUTH_FAILED = 'admin.reauth_failed';

    public const ACCESS_DENIED = 'admin.access_denied';

    // Administration
    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_ROLES_CHANGED = 'user.roles_changed';

    public const ROLE_PERMISSIONS_CHANGED = 'role.permissions_changed';

    public const SETTINGS_CHANGED = 'settings.changed';

    // Voter register
    public const VOTER_CREATED = 'voter.created';

    public const VOTER_UPDATED = 'voter.updated';

    public const VOTER_VERIFIED = 'voter.verified';

    public const VOTER_SUSPENDED = 'voter.suspended';

    public const VOTER_REINSTATED = 'voter.reinstated';

    public const VOTER_IMPORT_UPLOADED = 'voter_import.uploaded';

    public const VOTER_IMPORT_CONFIRMED = 'voter_import.confirmed';

    public const VOTER_IMPORT_COMPLETED = 'voter_import.completed';

    public const VOTER_IMPORT_FAILED = 'voter_import.failed';

    public const VOTER_IMPORT_CANCELLED = 'voter_import.cancelled';

    // Elections
    public const ELECTION_CREATED = 'election.created';

    public const ELECTION_UPDATED = 'election.updated';

    public const ELECTION_DELETED = 'election.deleted';

    public const ELECTION_SCHEDULED = 'election.scheduled';

    public const ELECTION_UNSCHEDULED = 'election.unscheduled';

    public const ELECTION_OPENED = 'election.opened';

    public const ELECTION_CLOSED = 'election.closed';

    public const ELECTION_ARCHIVED = 'election.archived';

    public const ELECTION_POSITIONS_CHANGED = 'election.positions_changed';

    public const ELIGIBILITY_GRANTED = 'eligibility.granted';

    public const ELIGIBILITY_CHANGED = 'eligibility.changed';

    public const ANNOUNCEMENT_CREATED = 'announcement.created';

    public const ANNOUNCEMENT_UPDATED = 'announcement.updated';

    public const ANNOUNCEMENT_DELETED = 'announcement.deleted';

    public const POSITION_CREATED = 'position.created';

    public const POSITION_UPDATED = 'position.updated';

    public const CANDIDATE_CREATED = 'candidate.created';

    public const CANDIDATE_UPDATED = 'candidate.updated';

    public const CANDIDATE_STATUS_CHANGED = 'candidate.status_changed';

    public const CANDIDATE_PHOTO_UPLOADED = 'candidate.photo_uploaded';

    // Results
    public const RESULTS_CALCULATED = 'results.calculated';

    public const RESULTS_VERIFIED = 'results.verified';

    public const RESULTS_VERIFICATION_FAILED = 'results.verification_failed';

    public const TIE_RESOLVED = 'results.tie_resolved';

    public const RESULTS_PUBLISHED = 'results.published';

    public const REPORT_GENERATED = 'report.generated';

    // Security
    public const SECURITY_ALERT = 'security.alert';

    public const SECURITY_ALERT_REVIEWED = 'security.alert_reviewed';
}
