<?php

use App\Enums\AnnouncementDisplay;
use App\Enums\AnnouncementLevel;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Notices the Super Admin posts to voters, shown as a pop-up and/or a scrolling
 * ticker on the public and voter pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 150);
            $table->text('body');
            $table->string('display', 10);
            $table->string('level', 10)->default(AnnouncementLevel::INFO->value);
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        DB::statement('ALTER TABLE announcements ADD CONSTRAINT announcements_display_check CHECK ('.AnnouncementDisplay::checkSql('display').')');
        DB::statement('ALTER TABLE announcements ADD CONSTRAINT announcements_level_check CHECK ('.AnnouncementLevel::checkSql('level').')');
        DB::statement('ALTER TABLE announcements ADD CONSTRAINT announcements_window_check CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)');

        // Existing installations: add the permission and give it to the Super Admin role.
        // (On a fresh install the roles seeder does this.)
        $meta = Permissions::catalogue()[Permissions::MANAGE_ANNOUNCEMENTS];
        DB::table('permissions')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'name' => Permissions::MANAGE_ANNOUNCEMENTS,
            'label' => $meta['label'],
            'group' => $meta['group'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('name', Permissions::MANAGE_ANNOUNCEMENTS)->value('id');
        $roleId = DB::table('roles')->where('name', 'SUPER_ADMIN')->value('id');
        if ($roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        DB::table('permissions')->where('name', Permissions::MANAGE_ANNOUNCEMENTS)->delete();
    }
};
