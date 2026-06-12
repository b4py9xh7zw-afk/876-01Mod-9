<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamNetworkEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_record_id',
        'user_id',
        'event_type',
        'device_fingerprint',
        'ip_address',
        'user_agent',
        'event_at',
        'duration_seconds',
        'client_data',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'user_id' => 'integer',
        'event_at' => 'datetime',
        'duration_seconds' => 'integer',
        'client_data' => 'array',
    ];

    public const EVENT_TYPE_DISCONNECTED = 'disconnected';
    public const EVENT_TYPE_RECONNECTED = 'reconnected';
    public const EVENT_TYPE_PAGE_REFRESH = 'page_refresh';
    public const EVENT_TYPE_DEVICE_CHANGE = 'device_change';
    public const EVENT_TYPE_HEARTBEAT = 'heartbeat';

    public const EVENT_TYPES = [
        self::EVENT_TYPE_DISCONNECTED => '网络断开',
        self::EVENT_TYPE_RECONNECTED => '网络恢复',
        self::EVENT_TYPE_PAGE_REFRESH => '页面刷新',
        self::EVENT_TYPE_DEVICE_CHANGE => '设备变更',
        self::EVENT_TYPE_HEARTBEAT => '心跳',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
