<?php

namespace App\Services\Audit;

use App\Enums\AuditResult;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Voter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Append-only, hash-chained audit log.
 *
 * Each row's hash covers its own content plus the previous row's hash, so any
 * deletion, insertion or edit made outside the application breaks the chain and
 * is reported by `php artisan audit:verify`. Never pass OTPs, passwords, tokens
 * or ballot selections in metadata.
 */
class AuditLogger
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Metadata keys that must never be persisted, whatever the caller passes. */
    private const FORBIDDEN_KEYS = ['password', 'otp', 'code', 'token', 'secret', 'selections', 'candidate_id', 'ballot_id', 'reference', 'mfa_code'];

    public function __construct(private readonly ?Request $request = null) {}

    /**
     * @param  Model|array{0:string,1:string|null}|null  $entity
     * @param  array<string, mixed>  $metadata
     * @param  array{type:string,id:?string,label:?string}|null  $actor
     */
    public function log(
        string $action,
        AuditResult $result = AuditResult::SUCCESS,
        Model|array|null $entity = null,
        array $metadata = [],
        ?array $actor = null,
    ): AuditLog {
        $actor ??= $this->resolveActor();
        [$entityType, $entityId] = $this->resolveEntity($entity);

        $row = [
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'actor_label' => $actor['label'] !== null ? mb_substr($actor['label'], 0, 191) : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'result' => $result->value,
            'ip' => $this->request?->ip(),
            'user_agent' => $this->request ? mb_substr((string) $this->request->userAgent(), 0, 255) : null,
            'metadata' => self::canonicalise($this->scrub($metadata)),
        ];

        return DB::transaction(function () use ($row) {
            // Serialise chain writers so every row links to its true predecessor.
            DB::statement("SELECT pg_advisory_xact_lock(hashtext('nimcos_audit_chain'))");

            $prev = DB::table('audit_logs')->orderByDesc('id')->value('hash') ?? self::GENESIS;
            $createdAt = CarbonImmutable::now('UTC');

            $row['created_at'] = $createdAt->format('Y-m-d H:i:s.uP');
            $row['prev_hash'] = $prev;
            $row['hash'] = self::computeHash($row, $createdAt, $prev);
            $row['metadata'] = $row['metadata'] === [] ? null : json_encode($row['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $id = DB::table('audit_logs')->insertGetId($row);

            return AuditLog::query()->findOrFail($id);
        });
    }

    public function failure(string $action, Model|array|null $entity = null, array $metadata = [], ?array $actor = null): AuditLog
    {
        return $this->log($action, AuditResult::FAILURE, $entity, $metadata, $actor);
    }

    public function denied(string $action, Model|array|null $entity = null, array $metadata = [], ?array $actor = null): AuditLog
    {
        return $this->log($action, AuditResult::DENIED, $entity, $metadata, $actor);
    }

    /** @param  array<string, mixed>  $row */
    public static function computeHash(array $row, CarbonImmutable $createdAt, string $prevHash): string
    {
        $metadata = $row['metadata'];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        $metadata = self::canonicalise($metadata ?? []);

        $payload = implode('|', [
            $prevHash,
            $createdAt->utc()->format('Y-m-d\TH:i:s.u\Z'),
            $row['actor_type'],
            (string) $row['actor_id'],
            (string) $row['actor_label'],
            $row['action'],
            (string) $row['entity_type'],
            (string) $row['entity_id'],
            $row['result'],
            (string) $row['ip'],
            (string) $row['user_agent'],
            $metadata === [] ? '' : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Stable representation: keys sorted recursively and scalars as strings/ints/bools,
     * so the hash is identical after a round-trip through PostgreSQL jsonb.
     */
    public static function canonicalise(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = self::canonicalise($value);
            } elseif (is_float($value)) {
                $value = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
            } elseif ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = CarbonImmutable::instance($value)->utc()->toIso8601String();
            } elseif (is_object($value)) {
                $value = (string) json_encode($value);
            }
            $out[$key] = $value;
        }
        if (! array_is_list($out)) {
            ksort($out, SORT_STRING);
        }

        return $out;
    }

    /** @return array{type:string,id:?string,label:?string} */
    private function resolveActor(): array
    {
        $admin = Auth::guard('web')->user();
        if ($admin instanceof User) {
            return ['type' => 'ADMIN', 'id' => $admin->getKey(), 'label' => $admin->email];
        }

        $voter = Auth::guard('voter')->user();
        if ($voter instanceof Voter) {
            return ['type' => 'VOTER', 'id' => $voter->getKey(), 'label' => $voter->service_number];
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return ['type' => 'SYSTEM', 'id' => null, 'label' => 'scheduler/console'];
        }

        return ['type' => 'ANONYMOUS', 'id' => null, 'label' => null];
    }

    /** @return array{0:?string,1:?string} */
    private function resolveEntity(Model|array|null $entity): array
    {
        if ($entity instanceof Model) {
            return [class_basename($entity), (string) $entity->getKey()];
        }
        if (is_array($entity)) {
            return [$entity[0] ?? null, isset($entity[1]) ? (string) $entity[1] : null];
        }

        return [null, null];
    }

    private function scrub(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_KEYS, true)) {
                unset($metadata[$key]);

                continue;
            }
            if (is_array($value)) {
                $metadata[$key] = $this->scrub($value);
            }
        }

        return $metadata;
    }
}
