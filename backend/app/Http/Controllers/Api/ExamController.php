<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use App\Models\ExamNetworkEvent;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $examPapers = ExamPaper::with('creator')
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'exam_papers' => $examPapers,
        ]);
    }

    public function start(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'device_fingerprint' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existingRecord = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingRecord) {
            $issues = $existingRecord->detectSuspiciousBehavior(
                $request->device_fingerprint,
                $request->header('X-Session-Id', '')
            );

            if (!empty($issues)) {
                $eventType = $issues[0]['type'] === 'device_change'
                    ? ExamNetworkEvent::EVENT_TYPE_DEVICE_CHANGE
                    : ExamNetworkEvent::EVENT_TYPE_PAGE_REFRESH;

                $existingRecord->recordNetworkEvent($eventType, [
                    'device_fingerprint' => $request->device_fingerprint,
                    'session_id' => $request->header('X-Session-Id'),
                    'issues' => $issues,
                ]);
            }

            $sessionId = $existingRecord->session_id ?: Str::random(32);
            $existingRecord->update([
                'device_fingerprint' => $existingRecord->device_fingerprint ?: $request->device_fingerprint,
                'session_id' => $sessionId,
                'last_heartbeat_at' => now(),
            ]);

            $existingRecord->recordNetworkEvent(ExamNetworkEvent::EVENT_TYPE_HEARTBEAT, [
                'device_fingerprint' => $request->device_fingerprint,
                'session_id' => $sessionId,
            ]);

            $remainingTime = $existingRecord->getRemainingTimeWithExtension($examPaper->total_time * 60);

            return response()->json([
                'message' => '考试已在进行中',
                'exam_record' => $existingRecord,
                'exam_paper' => [
                    'id' => $examPaper->id,
                    'title' => $examPaper->title,
                    'total_time' => $examPaper->total_time,
                    'total_score' => $examPaper->total_score,
                ],
                'questions' => $this->getQuestionsData($examPaper),
                'session_id' => $sessionId,
                'remaining_time' => $remainingTime,
                'suspicious_issues' => $issues,
                'needs_review' => $existingRecord->needs_review,
                'extension_minutes' => $existingRecord->extension_minutes,
            ]);
        }

        $sessionId = Str::random(32);
        $record = ExamRecord::create([
            'user_id' => $request->user()->id,
            'exam_paper_id' => $examPaper->id,
            'start_time' => now(),
            'status' => 'in_progress',
            'device_fingerprint' => $request->device_fingerprint,
            'session_id' => $sessionId,
            'last_heartbeat_at' => now(),
        ]);

        $record->recordNetworkEvent(ExamNetworkEvent::EVENT_TYPE_HEARTBEAT, [
            'device_fingerprint' => $request->device_fingerprint,
            'session_id' => $sessionId,
        ]);

        return response()->json([
            'message' => '考试开始',
            'exam_record' => $record,
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
                'total_time' => $examPaper->total_time,
                'total_score' => $examPaper->total_score,
            ],
            'questions' => $this->getQuestionsData($examPaper),
            'session_id' => $sessionId,
            'remaining_time' => $examPaper->total_time * 60,
            'suspicious_issues' => [],
            'needs_review' => false,
            'extension_minutes' => 0,
        ]);
    }

    protected function getQuestionsData(ExamPaper $examPaper)
    {
        return $examPaper->questions()->get()->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'title' => $q->title,
                'options' => $q->options,
                'score' => $q->pivot->score,
            ];
        });
    }

    public function getQuestions(Request $request, ExamPaper $examPaper)
    {
        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $questions = $examPaper->questions()->get();

        $questionsData = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'title' => $q->title,
                'options' => $q->options,
                'score' => $q->pivot->score,
            ];
        });

        return response()->json([
            'exam_record' => $record,
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
                'total_time' => $examPaper->total_time,
                'total_score' => $examPaper->total_score,
            ],
            'questions' => $questionsData,
        ]);
    }

    public function submit(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $totalScore = 0;
        $questionMap = $examPaper->questions->keyBy('id');

        foreach ($request->answers as $answerData) {
            $question = $questionMap->get($answerData['question_id']);
            if (!$question) {
                continue;
            }

            $isCorrect = $this->checkAnswer($question, $answerData['answer']);
            $score = $isCorrect ? $question->pivot->score : 0;

            ExamRecordAnswer::create([
                'exam_record_id' => $record->id,
                'question_id' => $answerData['question_id'],
                'answer' => $answerData['answer'],
                'is_correct' => $isCorrect,
                'score' => $score,
            ]);

            $totalScore += $score;
        }

        $record->update([
            'end_time' => now(),
            'score' => $totalScore,
            'status' => 'graded',
        ]);

        return response()->json([
            'message' => '提交成功',
            'score' => $totalScore,
            'exam_record' => $record->load('answers'),
        ]);
    }

    public function myRecords(Request $request)
    {
        $records = ExamRecord::with('examPaper')
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'records' => $records,
        ]);
    }

    public function showRecord(Request $request, ExamRecord $record)
    {
        if ($record->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $record->load(['examPaper.questions', 'answers.question']);

        return response()->json([
            'record' => $record,
        ]);
    }

    protected function checkAnswer(Question $question, string $userAnswer): bool
    {
        $correctAnswer = $question->answer;

        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            case 'multiple_choice':
                $userAnswers = explode(',', strtoupper(trim($userAnswer)));
                $correctAnswers = explode(',', strtoupper(trim($correctAnswer)));
                sort($userAnswers);
                sort($correctAnswers);
                return $userAnswers === $correctAnswers;
            case 'fill_blank':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            default:
                return false;
        }
    }

    public function heartbeat(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'device_fingerprint' => 'required|string|max:64',
            'is_offline' => 'boolean',
            'local_time' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $issues = $record->detectSuspiciousBehavior(
            $request->device_fingerprint,
            $request->header('X-Session-Id', '')
        );

        $record->update([
            'last_heartbeat_at' => now(),
        ]);

        $record->recordNetworkEvent(ExamNetworkEvent::EVENT_TYPE_HEARTBEAT, [
            'device_fingerprint' => $request->device_fingerprint,
            'session_id' => $request->header('X-Session-Id'),
            'is_offline' => $request->input('is_offline', false),
            'local_time' => $request->input('local_time'),
            'suspicious_issues' => $issues,
        ]);

        $remainingTime = $record->getRemainingTimeWithExtension($examPaper->total_time * 60);

        return response()->json([
            'message' => '心跳正常',
            'server_time' => now()->timestamp,
            'remaining_time' => $remainingTime,
            'needs_review' => $record->needs_review,
            'extension_minutes' => $record->extension_minutes,
            'is_exceeded_timeout' => $record->isExceededMaxDisconnection(),
            'total_disconnection_seconds' => $record->total_disconnection_seconds,
            'suspicious_issues' => $issues,
        ]);
    }

    public function reportDisconnection(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'device_fingerprint' => 'required|string|max:64',
            'event_type' => 'required|in:disconnected,reconnected',
            'disconnection_started_at' => 'integer',
            'disconnection_ended_at' => 'integer',
            'duration_seconds' => 'integer',
            'local_answers' => 'array',
            'client_time' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $eventType = $request->event_type === 'disconnected'
            ? ExamNetworkEvent::EVENT_TYPE_DISCONNECTED
            : ExamNetworkEvent::EVENT_TYPE_RECONNECTED;

        $durationSeconds = $request->input('duration_seconds', 0);

        $record->recordNetworkEvent($eventType, [
            'device_fingerprint' => $request->device_fingerprint,
            'session_id' => $request->header('X-Session-Id'),
            'disconnection_started_at' => $request->disconnection_started_at,
            'disconnection_ended_at' => $request->disconnection_ended_at,
            'local_answers' => $request->local_answers,
            'client_time' => $request->client_time,
            'duration_seconds' => $durationSeconds,
        ]);

        if ($eventType === ExamNetworkEvent::EVENT_TYPE_DISCONNECTED) {
            $record->increment('disconnection_count');
            $record->update(['last_disconnection_at' => now()]);
        } elseif ($eventType === ExamNetworkEvent::EVENT_TYPE_RECONNECTED) {
            $newTotal = $record->total_disconnection_seconds + $durationSeconds;
            $record->update([
                'total_disconnection_seconds' => $newTotal,
            ]);

            if ($newTotal > ExamRecord::MAX_ALLOWED_DISCONNECTION_SECONDS && !$record->needs_review) {
                $record->update([
                    'needs_review' => true,
                    'review_note' => "累计断网{$newTotal}秒，超过" . ExamRecord::MAX_ALLOWED_DISCONNECTION_SECONDS . "秒限制，需监考审核",
                ]);
            }
        }

        $remainingTime = $record->getRemainingTimeWithExtension($examPaper->total_time * 60);

        return response()->json([
            'message' => $request->event_type === 'disconnected' ? '断网事件已记录' : '重连事件已记录',
            'remaining_time' => $remainingTime,
            'needs_review' => $record->needs_review,
            'is_exceeded_timeout' => $record->isExceededMaxDisconnection(),
            'total_disconnection_seconds' => $record->total_disconnection_seconds,
            'disconnection_count' => $record->disconnection_count,
            'extension_minutes' => $record->extension_minutes,
            'exam_record' => $record,
        ]);
    }

    public function syncAnswers(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'device_fingerprint' => 'required|string|max:64',
            'answers' => 'required|array',
            'sync_time' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $existingAnswers = $record->answers()->get()->keyBy('question_id');
        $questionMap = $examPaper->questions->keyBy('id');
        $totalScore = 0;

        foreach ($request->answers as $answerData) {
            $questionId = $answerData['question_id'];
            $answer = is_array($answerData['answer'])
                ? implode(',', $answerData['answer'])
                : $answerData['answer'];

            $question = $questionMap->get($questionId);
            if (!$question) continue;

            $isCorrect = $this->checkAnswer($question, $answer);
            $score = $isCorrect ? $question->pivot->score : 0;

            if ($existingAnswers->has($questionId)) {
                $existingAnswers->get($questionId)->update([
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);
            } else {
                ExamRecordAnswer::create([
                    'exam_record_id' => $record->id,
                    'question_id' => $questionId,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);
            }

            $totalScore += $score;
        }

        $record->recordNetworkEvent(ExamNetworkEvent::EVENT_TYPE_HEARTBEAT, [
            'device_fingerprint' => $request->device_fingerprint,
            'session_id' => $request->header('X-Session-Id'),
            'sync_type' => 'answer_sync',
            'synced_count' => count($request->answers),
            'sync_time' => $request->sync_time,
        ]);

        return response()->json([
            'message' => '答案同步成功',
            'synced_count' => count($request->answers),
            'current_score' => $totalScore,
            'exam_record' => $record->fresh('answers'),
        ]);
    }

    public function requestExtension(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'device_fingerprint' => 'required|string|max:64',
            'reason' => 'required|string|max:500',
            'requested_minutes' => 'required|integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $record->update([
            'needs_review' => true,
            'review_note' => "学生申请延时{$request->requested_minutes}分钟，原因：{$request->reason}。\n累计断网{$record->total_disconnection_seconds}秒，断网{$record->disconnection_count}次",
        ]);

        $record->recordNetworkEvent(ExamNetworkEvent::EVENT_TYPE_HEARTBEAT, [
            'device_fingerprint' => $request->device_fingerprint,
            'session_id' => $request->header('X-Session-Id'),
            'event_type' => 'extension_request',
            'requested_minutes' => $request->requested_minutes,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => '延时申请已提交，请等待监考老师审核',
            'needs_review' => true,
            'requested_minutes' => $request->requested_minutes,
        ]);
    }

    public function getPendingReviews(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $records = ExamRecord::with(['user', 'examPaper', 'networkEvents'])
            ->where('needs_review', true)
            ->where('status', 'in_progress')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'records' => $records,
            'max_allowed_seconds' => ExamRecord::MAX_ALLOWED_DISCONNECTION_SECONDS,
        ]);
    }

    public function reviewExtension(Request $request, ExamRecord $record)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['message' => '无权访问'], 403);
        }

        if ($record->status !== 'in_progress') {
            return response()->json(['message' => '考试已结束，无法审核'], 400);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'extension_minutes' => 'integer|min:0|max:120',
            'review_note' => 'string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $extensionMinutes = $request->action === 'approve'
            ? ($request->extension_minutes ?? 0)
            : 0;

        $originalNote = $record->review_note;
        $reviewNote = $request->review_note ?? '';
        $newNote = "{$originalNote}\n\n审核结果：" . ($request->action === 'approve' ? '同意' : '拒绝') .
            ($extensionMinutes > 0 ? "，延时{$extensionMinutes}分钟" : '') .
            "。\n审核备注：{$reviewNote}\n审核人：{$request->user()->name}";

        $record->update([
            'needs_review' => false,
            'extension_minutes' => $extensionMinutes,
            'review_note' => $newNote,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $record->recordNetworkEvent(ExamNetworkEvent::EVENT_TYPE_HEARTBEAT, [
            'event_type' => 'review_decision',
            'action' => $request->action,
            'extension_minutes' => $extensionMinutes,
            'review_note' => $reviewNote,
            'reviewed_by' => $request->user()->id,
            'reviewed_by_name' => $request->user()->name,
        ]);

        return response()->json([
            'message' => '审核完成',
            'action' => $request->action,
            'extension_minutes' => $extensionMinutes,
            'exam_record' => $record,
        ]);
    }

    public function getNetworkEvents(Request $request, ExamRecord $record)
    {
        if ($record->user_id !== $request->user()->id && !in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $events = $record->networkEvents()
            ->orderBy('event_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'events' => $events,
            'event_types' => ExamNetworkEvent::EVENT_TYPES,
        ]);
    }
}
