<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('note_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->integer('display_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('contact_link_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('channel_type');
            $table->string('name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('contact_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('status_id')->constrained('lead_statuses')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status')->default('open');
            $table->timestampTz('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->integer('unread_count')->default(0);
            $table->string('assignment_reason')->nullable();
            $table->boolean('assignment_seen_by_assignee')->default(false);
            $table->unsignedBigInteger('reassigned_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('object_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_plural');
            $table->string('icon')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('object_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('object_type_id')->constrained('object_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('object_type_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('object_type_id')->constrained('object_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('field_type');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->integer('display_order')->default(0);
            $table->jsonb('field_config')->nullable();
            $table->timestampsTz();
        });

        Schema::create('conversation_objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            // Assuming objects was renamed to relink_objects earlier
            $table->foreignUuid('object_id')->constrained('relink_objects')->cascadeOnDelete();
            $table->unsignedBigInteger('linked_by')->nullable();
            $table->timestampTz('linked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('contact_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('status_id')->nullable()->constrained('note_statuses')->nullOnDelete();
            $table->text('content');
            $table->string('color')->nullable();
            $table->timestampTz('reminder_at')->nullable();
            $table->boolean('reminder_shown')->default(false);
            $table->uuid('created_by');
            $table->timestampsTz();
        });

        Schema::create('routing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('conditions')->nullable();
            $table->jsonb('actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('rule_action_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('rule_id')->constrained('routing_rules')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('action_type');
            $table->jsonb('action_details')->nullable();
            $table->timestampTz('executed_at');
            $table->timestampsTz();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint');
            $table->string('p256dh');
            $table->string('auth');
            $table->timestampsTz();
        });

        Schema::create('chat_distribution', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_assigned_user_id')->nullable();
            $table->integer('assignment_index')->default(0);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_distribution');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('rule_action_logs');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('contact_notes');
        Schema::dropIfExists('conversation_objects');
        Schema::dropIfExists('object_type_fields');
        Schema::dropIfExists('object_statuses');
        Schema::dropIfExists('object_types');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('contact_statuses');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_link_groups');
        Schema::dropIfExists('note_statuses');
        Schema::dropIfExists('lead_statuses');
    }
};
