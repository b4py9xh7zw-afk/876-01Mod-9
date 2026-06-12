<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_network_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->string('event_type', 32)->comment('事件类型: disconnected, reconnected, page_refresh, device_change, heartbeat');
            $table->string('device_fingerprint', 64)->nullable()->comment('设备指纹');
            $table->string('ip_address', 45)->nullable()->comment('IP地址');
            $table->text('user_agent')->nullable()->comment('用户代理');
            $table->timestamp('event_at')->comment('事件发生时间');
            $table->integer('duration_seconds')->default(0)->comment('事件持续秒数(断网时长)');
            $table->text('client_data')->nullable()->comment('客户端数据(JSON)');
            $table->timestamps();

            $table->index(['exam_record_id', 'event_type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_network_events');
    }
};
