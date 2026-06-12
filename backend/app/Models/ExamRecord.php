<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_paper_id',
        'start_time',
        'end_time',
        'score',
        'status',
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
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exam_paper_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'score' => 'decimal:2',
        'status' => 'string',
        'last_heartbeat_at' => 'datetime',
        'disconnection_count' => 'integer',
        'total_disconnection_seconds' => 'integer',
        'last_disconnection_at' => 'datetime',
        'needs_review' => 'boolean',
        'extension_minutes' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public const MAX_ALLOWED_DISCONNECTION_SECONDS = 300;

    public const DISCONNECTION_THRESHOLD_SECONDS = 10;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => '进行中',
        self::STATUS_SUBMITTED => '已提交',
        self::STATUS_GRADED => '已评分',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamRecordAnswer::class, 'exam_record_id');
    }

    public function networkEvents()
    {
        return $this->hasMany(ExamNetworkEvent::class, 'exam_record_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function recordNetworkEvent(string $eventType, array $clientData = []): ExamNetworkEvent
    {
        return $this->networkEvents()->create([
            'user_id' => $this->user_id,
            'event_type' => $eventType,
            'device_fingerprint' => $clientData['device_fingerprint'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'event_at' => now(),
            'duration_seconds' => $clientData['duration_seconds'] ?? 0,
            'client_data' => $clientData,
        ]);
    }

    public function isExceededMaxDisconnection(): bool
    {
        return $this->total_disconnection_seconds > self::MAX_ALLOWED_DISCONNECTION_SECONDS;
    }

    public function getRemainingTimeWithExtension(int $totalExamSeconds): int
    {
        $extensionSeconds = $this->extension_minutes * 60;
        $elapsedSeconds = $this->start_time->diffInSeconds(now());
        $remaining = $totalExamSeconds + $extensionSeconds - $elapsedSeconds;

        return max(0, $remaining);
    }

    public function verifyDevice(string $fingerprint): bool
    {
        if (empty($this->device_fingerprint)) {
            return true;
        }
        return $this->device_fingerprint === $fingerprint;
    }

    public function verifySession(string $sessionId): bool
    {
        if (empty($this->session_id)) {
            return true;
        }
        return $this->session_id === $sessionId;
    }

    public function detectSuspiciousBehavior(string $fingerprint, string $sessionId): array
    {
        $issues = [];

        if (!$this->verifyDevice($fingerprint)) {
            $issues[] = [
                'type' => 'device_change',
                'severity' => 'high',
                'message' => '检测到设备变更',
            ];
        }

        if (!$this->verifySession($sessionId)) {
            $issues[] = [
                'type' => 'page_refresh',
                'severity' => 'medium',
                'message' => '检测到页面刷新',
            ];
        }

        if ($this->isExceededMaxDisconnection()) {
            $issues[] = [
                'type' => 'exceeded_timeout',
                'severity' => 'high',
                'message' => '累计断网时间超过允许范围',
            ];
        }

        return $issues;
    }
}
