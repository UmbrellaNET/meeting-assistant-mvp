<?php

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            $this->createCurrentSchema();

            return;
        }

        if ($this->isLegacySchema()) {
            $this->rebuildFromLegacy();

            return;
        }

        $this->addMissingCurrentColumns();
    }

    public function down(): void
    {
        // Fresh installs already get the current schema from 2026_08_20_000500.
        // Rolling this back must not strip those columns.
    }

    protected function isLegacySchema(): bool
    {
        return Schema::hasColumn('activity_logs', 'actor_user_id')
            || Schema::hasColumn('activity_logs', 'action')
            || Schema::hasColumn('activity_logs', 'target_user_id')
            || Schema::hasColumn('activity_logs', 'metadata');
    }

    protected function rebuildFromLegacy(): void
    {
        $users = User::query()->get()->keyBy('id');
        $rows = DB::table('activity_logs')
            ->orderBy('created_at')
            ->get()
            ->map(fn (object $row): array => $this->mapLegacyRow($row, $users))
            ->all();

        Schema::dropIfExists('activity_logs');
        $this->createCurrentSchema();

        foreach ($rows as $row) {
            DB::table('activity_logs')->insert($row);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $users
     * @return array<string, mixed>
     */
    protected function mapLegacyRow(object $row, $users): array
    {
        $action = (string) ($row->action ?? 'logged');
        $event = ActivityEvent::tryFrom($action);
        $isImpersonation = str_starts_with($action, 'impersonation');
        $actorId = $row->actor_user_id ?? null;
        $targetId = $row->target_user_id ?? null;
        $causerId = $isImpersonation ? ($targetId ?: $actorId) : $actorId;
        $subjectId = $targetId;
        $causer = $causerId ? $users->get($causerId) : null;
        $subject = $subjectId ? $users->get($subjectId) : null;

        return [
            'id' => $row->id,
            'tenant_id' => $row->tenant_id ?? null,
            'log_name' => $event?->logName() ?? $this->legacyLogName($action),
            'description' => $event?->description() ?? ($action !== '' ? $action : 'Activity'),
            'subject_type' => $subjectId ? User::class : null,
            'subject_id' => $subjectId,
            'event' => $action !== '' ? $action : 'logged',
            'causer_type' => $causerId ? User::class : null,
            'causer_id' => $causerId,
            'properties' => $this->legacyProperties($row->metadata ?? null),
            'ip_address' => $row->ip_address ?? null,
            'user_agent' => $row->user_agent ?? null,
            'source' => 'api',
            'impersonator_id' => $isImpersonation ? $actorId : null,
            'actor_name' => $causer?->name,
            'actor_email' => $causer?->email,
            'subject_label' => $subject?->name ?? $subject?->email,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    protected function createCurrentSchema(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $this->defineCurrentColumns($table);
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    protected function addMissingCurrentColumns(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $this->defineCurrentColumns($table, onlyMissing: true);
        });

        if (! $this->hasIndex('activity_logs_tenant_id_created_at_index')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    protected function defineCurrentColumns(Blueprint $table, bool $onlyMissing = false): void
    {
        $add = function (string $column, callable $define) use ($table, $onlyMissing): void {
            if ($onlyMissing && Schema::hasColumn('activity_logs', $column)) {
                return;
            }

            $define($table);
        };

        $add('log_name', fn (Blueprint $table) => $table->string('log_name')->nullable()->index());
        $add('description', fn (Blueprint $table) => $table->text('description')->default('Activity'));
        $add('subject_type', fn (Blueprint $table) => $table->string('subject_type')->nullable());
        $add('subject_id', fn (Blueprint $table) => $table->uuid('subject_id')->nullable());
        $add('event', fn (Blueprint $table) => $table->string('event')->nullable()->index());
        $add('causer_type', fn (Blueprint $table) => $table->string('causer_type')->nullable());
        $add('causer_id', fn (Blueprint $table) => $table->uuid('causer_id')->nullable());
        $add('properties', fn (Blueprint $table) => $table->json('properties')->nullable());
        $add('batch_uuid', fn (Blueprint $table) => $table->uuid('batch_uuid')->nullable()->index());
        $add('ip_address', fn (Blueprint $table) => $table->string('ip_address', 45)->nullable());
        $add('user_agent', fn (Blueprint $table) => $table->text('user_agent')->nullable());
        $add('browser', fn (Blueprint $table) => $table->string('browser')->nullable());
        $add('browser_version', fn (Blueprint $table) => $table->string('browser_version')->nullable());
        $add('os', fn (Blueprint $table) => $table->string('os')->nullable());
        $add('device_type', fn (Blueprint $table) => $table->string('device_type')->nullable());
        $add('country', fn (Blueprint $table) => $table->string('country')->nullable());
        $add('region', fn (Blueprint $table) => $table->string('region')->nullable());
        $add('city', fn (Blueprint $table) => $table->string('city')->nullable());
        $add('source', fn (Blueprint $table) => $table->string('source')->nullable()->index());
        $add('integration', fn (Blueprint $table) => $table->string('integration')->nullable()->index());
        $add('impersonator_id', function (Blueprint $table): void {
            $table->foreignUuid('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
        });
        $add('actor_name', fn (Blueprint $table) => $table->string('actor_name')->nullable());
        $add('actor_email', fn (Blueprint $table) => $table->string('actor_email')->nullable());
        $add('subject_label', fn (Blueprint $table) => $table->string('subject_label')->nullable());
        $add('request_id', fn (Blueprint $table) => $table->string('request_id')->nullable()->index());

        if (! $onlyMissing || ! $this->hasIndex('activity_logs_subject_type_subject_id_index')) {
            $table->index(['subject_type', 'subject_id']);
        }
        if (! $onlyMissing || ! $this->hasIndex('activity_logs_causer_type_causer_id_index')) {
            $table->index(['causer_type', 'causer_id']);
        }
    }

    protected function legacyLogName(string $action): string
    {
        if ($action === '') {
            return 'default';
        }

        return str_contains($action, '.') ? explode('.', $action, 2)[0] : 'default';
    }

    protected function legacyProperties(mixed $metadata): ?string
    {
        if ($metadata === null || $metadata === '') {
            return null;
        }

        if (is_array($metadata)) {
            return json_encode($metadata);
        }

        if (! is_string($metadata)) {
            return json_encode(['legacy' => $metadata]);
        }

        json_decode($metadata, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $metadata;
        }

        return json_encode(['legacy' => $metadata]);
    }

    protected function hasIndex(string $name): bool
    {
        if (! Schema::hasTable('activity_logs')) {
            return false;
        }

        foreach (Schema::getIndexes('activity_logs') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
