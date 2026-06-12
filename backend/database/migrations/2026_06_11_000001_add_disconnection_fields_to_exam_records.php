<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            $table->string('device_fingerprint', 64)->nullable()->comment('设备指纹');
            $table->timestamp('last_heartbeat_at')->nullable()->comment('最后心跳时间');
            $table->integer('disconnection_count')->default(0)->comment('断网次数');
            $table->integer('total_disconnection_seconds')->default(0)->comment('累计断网秒数');
            $table->timestamp('last_disconnection_at')->nullable()->comment('上次断网时间');
            $table->boolean('needs_review')->default(false)->comment('是否需要监考审核');
            $table->text('review_note')->nullable()->comment('审核备注');
            $table->integer('extension_minutes')->default(0)->comment('延长考试时间(分钟)');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->comment('审核人');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->string('session_id', 64)->nullable()->comment('会话ID，用于检测刷新');
        });
    }

    public function down(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            $table->dropColumn([
                'device_fingerprint',
                'last_heartbeat_at',
                'disconnection_count',
                'total_disconnection_seconds',
                'last_disconnection_at',
                'needs_review',
                'review_note',
                'extension_minutes',
                'reviewed_by',
                'reviewed_at',
                'session_id',
            ]);
        });
    }
};
