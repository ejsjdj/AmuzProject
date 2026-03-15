<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DowntimeEvent;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArrayExport;
use Carbon\Carbon;

class DowntimeEventController extends Controller
{
    /**
     * GET /api/v1/downtime-events
     *
     * - downtime_events 테이블 조회
     * - 라인/스테이션/작업지시/사유코드/기간(from/to) 필터 + 정렬 + 페이지네이션.
     */
    public function index(Request $request)
    {
        $lineId      = $request->query('lineId');
        $stationId   = $request->query('stationId');
        $workOrderId = $request->query('workOrderId');
        $reasonCode  = $request->query('reasonCode');
        $from        = $request->query('from');   // YYYY-MM-DD
        $to          = $request->query('to');     // YYYY-MM-DD
        $eventId     = $request->query('eventId');

        $page    = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('perPage', 20), 1);
        $sortBy  = $request->query('sortBy', 'startedAt');
        $sortDir = strtolower($request->query('sortDir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'startedAt'       => 'started_at',
            'endedAt'         => 'ended_at',
            'lineId'          => 'line_id',
            'stationId'       => 'station_id',
            'workOrderId'     => 'work_order_id',
            'reasonCode'      => 'reason_code',
            'durationMinutes' => 'duration_minutes',
            'eventId'         => 'event_id',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'started_at';

        $query = DowntimeEvent::query();

        $query->when($lineId,      fn($q, $v) => $q->where('line_id', $v));
        $query->when($stationId,   fn($q, $v) => $q->where('station_id', $v));
        $query->when($workOrderId, fn($q, $v) => $q->where('work_order_id', $v));
        $query->when($reasonCode,  fn($q, $v) => $q->where('reason_code', $v));
        $query->when($eventId,     fn($q, $v) => $q->where('event_id', $v));

        $query->when($from, fn($q, $v) => $q->whereDate('started_at', '>=', $v));
        $query->when($to,   fn($q, $v) => $q->whereDate('started_at', '<=', $v));

        $total = $query->count();

        $events = $query->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $results = $events->map(function (DowntimeEvent $e) {
            return [
                'eventId'         => $e->event_id,
                'startedAt'       => $e->started_at?->toIso8601String(),
                'endedAt'         => $e->ended_at?->toIso8601String(),
                'lineId'          => $e->line_id,
                'workOrderId'     => $e->work_order_id,
                'stationId'       => $e->station_id,
                'reasonCode'      => $e->reason_code,
                'durationMinutes' => $e->duration_minutes,
                'note'            => $e->note,
            ];
        })->all();

        return response()->json([
            'data' => $results,
            'meta' => [
                'total'      => $total,
                'page'       => $page,
                'perPage'    => $perPage,
                'totalPages' => (int) ceil($total / $perPage),
                'filters'    => [
                    'eventId'     => $eventId,
                    'lineId'      => $lineId,
                    'stationId'   => $stationId,
                    'workOrderId' => $workOrderId,
                    'reasonCode'  => $reasonCode,
                    'from'        => $from,
                    'to'          => $to,
                ],
                'sort' => [
                    'sortBy'  => $sortBy,
                    'sortDir' => $sortDir,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/downtime-events/export
     * - 현재 필터/정렬/페이지 조건으로 downtime_events를 엑셀로 Export
     */
    public function export(Request $request)
    {
        $lineId      = $request->query('lineId');
        $stationId   = $request->query('stationId');
        $workOrderId = $request->query('workOrderId');
        $reasonCode  = $request->query('reasonCode');
        $from        = $request->query('from');
        $to          = $request->query('to');
        $eventId     = $request->query('eventId');

        $page    = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('perPage', 20), 1);
        $sortBy  = $request->query('sortBy', 'startedAt');
        $sortDir = strtolower($request->query('sortDir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'startedAt'       => 'started_at',
            'endedAt'         => 'ended_at',
            'lineId'          => 'line_id',
            'stationId'       => 'station_id',
            'workOrderId'     => 'work_order_id',
            'reasonCode'      => 'reason_code',
            'durationMinutes' => 'duration_minutes',
            'eventId'         => 'event_id',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'started_at';

        $query = DowntimeEvent::query();

        $query->when($lineId,      fn($q, $v) => $q->where('line_id', $v));
        $query->when($stationId,   fn($q, $v) => $q->where('station_id', $v));
        $query->when($workOrderId, fn($q, $v) => $q->where('work_order_id', $v));
        $query->when($reasonCode,  fn($q, $v) => $q->where('reason_code', $v));
        $query->when($eventId,     fn($q, $v) => $q->where('event_id', $v));
        $query->when($from,        fn($q, $v) => $q->whereDate('started_at', '>=', $v));
        $query->when($to,          fn($q, $v) => $q->whereDate('started_at', '<=', $v));

        $events = $query->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $header = [
            'eventId',
            'startedAt',
            'endedAt',
            'lineId',
            'workOrderId',
            'stationId',
            'reasonCode',
            'durationMinutes',
            'note',
        ];

        $rows   = [];
        $rows[] = $header;

        foreach ($events as $e) {
            $rows[] = [
                $e->event_id,
                $e->started_at?->toIso8601String(),
                $e->ended_at?->toIso8601String(),
                $e->line_id,
                $e->work_order_id,
                $e->station_id,
                $e->reason_code,
                $e->duration_minutes,
                $e->note,
            ];
        }

        return Excel::download(new ArrayExport($rows), 'downtime-events.xlsx');
    }

    /**
     * POST /api/v1/downtime-events/import
     *
     * - 엑셀로 다운타임 이벤트 일괄 생성/수정 (DB 저장).
     * - 헤더 예시:
     *   eventId, lineId, stationId, reasonCode, startedAt, endedAt, durationMinutes, note
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt',
        ]);

        $file        = $request->file('file');
        $collections = Excel::toCollection(null, $file);

        if ($collections->isEmpty()) {
            return response()->json([
                'error' => [
                    'message' => '엑셀 시트에 데이터가 없습니다.',
                ],
            ], 400);
        }

        $rows = $collections[0];
        if ($rows->count() === 0) {
            return response()->json([
                'error' => [
                    'message' => '엑셀 시트에 데이터가 없습니다.',
                ],
            ], 400);
        }

        $header = $rows[0]->toArray();

        $requiredColumns = [
            'lineId',
            'stationId',
            'reasonCode',
            'startedAt',
        ];

        $headerIndex = [];
        foreach ($header as $index => $columnName) {
            if ($columnName === null || $columnName === '') {
                continue;
            }
            $headerIndex[$columnName] = $index;
        }

        $missing = [];
        foreach ($requiredColumns as $col) {
            if (!array_key_exists($col, $headerIndex)) {
                $missing[] = $col;
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'error' => [
                    'message'        => '필수 컬럼이 누락되었습니다.',
                    'missingColumns' => $missing,
                ],
            ], 400);
        }

        $created = 0;
        $updated = 0;
        $errors  = [];

        for ($i = 1; $i < $rows->count(); $i++) {
            $row = $rows[$i]->toArray();

            if ($this->isRowEmpty($row)) {
                continue;
            }

            $eventId         = $this->getCell($row, $headerIndex, 'eventId');
            $lineId          = $this->getCell($row, $headerIndex, 'lineId');
            $stationId       = $this->getCell($row, $headerIndex, 'stationId');
            $reasonCode      = $this->getCell($row, $headerIndex, 'reasonCode');
            $startedAt       = $this->getCell($row, $headerIndex, 'startedAt');
            $endedAt         = $this->getCell($row, $headerIndex, 'endedAt');
            $durationMinutes = $this->getCell($row, $headerIndex, 'durationMinutes');
            $note            = $this->getCell($row, $headerIndex, 'note');

            $durationInt = is_numeric($durationMinutes) ? (int) $durationMinutes : null;

            if (!$lineId || !$stationId || !$reasonCode || !$startedAt) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => '필수값(lineId, stationId, reasonCode, startedAt) 누락 또는 형식 오류',
                ];
                continue;
            }

            // 날짜 형식/순서 검증
            $start = $this->parseDateTime($startedAt);
            $end   = $endedAt ? $this->parseDateTime($endedAt) : null;

            if (!$start) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => '날짜 형식 오류 (startedAt)',
                ];
                continue;
            }

            if ($start && $end && $start->gt($end)) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => 'startedAt이 endedAt보다 늦을 수 없습니다.',
                ];
                continue;
            }

            if ($eventId) {
                $event = DowntimeEvent::where('event_id', $eventId)->first();
            } else {
                $eventId = $this->generateEventIdFromDb();
                $event   = null;
            }

            $payload = [
                'started_at'       => $startedAt,
                'ended_at'         => $endedAt ?: null,
                'line_id'          => $lineId,
                'work_order_id'    => $this->getCell($row, $headerIndex, 'workOrderId') ?: null,
                'station_id'       => $stationId,
                'reason_code'      => $reasonCode,
                'duration_minutes' => $durationInt,
                'note'             => $note ?: null,
            ];

            if ($event) {
                $event->update($payload);
                $updated++;
            } else {
                $payload['event_id'] = $eventId;
                DowntimeEvent::create($payload);
                $created++;
            }
        }

        return response()->json([
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors'  => $errors,
            ],
        ]);
    }

    /**
     * GET /api/v1/downtime-events/{eventId}
     */
    public function show(string $eventId)
    {
        $e = DowntimeEvent::where('event_id', $eventId)->first();

        if (!$e) {
            return response()->json([
                'error' => [
                    'message' => 'Downtime event not found: '.$eventId,
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'eventId'         => $e->event_id,
                'startedAt'       => $e->started_at?->toIso8601String(),
                'endedAt'         => $e->ended_at?->toIso8601String(),
                'lineId'          => $e->line_id,
                'workOrderId'     => $e->work_order_id,
                'stationId'       => $e->station_id,
                'reasonCode'      => $e->reason_code,
                'durationMinutes' => $e->duration_minutes,
                'note'            => $e->note,
            ],
        ]);
    }

    /**
     * POST /api/v1/downtime-events
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'startedAt'       => 'required|string',
            'endedAt'         => 'nullable|string',
            'lineId'          => 'required|string',
            'workOrderId'     => 'nullable|string',
            'stationId'       => 'required|string',
            'reasonCode'      => 'required|string',
            'durationMinutes' => 'nullable|integer|min:0',
            'note'            => 'nullable|string',
        ]);

        $startedAt = $validated['startedAt'];
        $endedAt   = $validated['endedAt'] ?? null;

        $start = $this->parseDateTime($startedAt);
        $end   = $endedAt ? $this->parseDateTime($endedAt) : null;

        if (!$start) {
            return response()->json([
                'message' => '날짜 형식 오류 (startedAt)',
            ], 422);
        }

        if ($start && $end && $start->gt($end)) {
            return response()->json([
                'message' => 'endedAt은 startedAt보다 이후여야 합니다.',
            ], 422);
        }

        $eventId = $this->generateEventIdFromDb();

        $e = DowntimeEvent::create([
            'event_id'        => $eventId,
            'started_at'      => $startedAt,
            'ended_at'        => $endedAt,
            'line_id'         => $validated['lineId'],
            'work_order_id'   => $validated['workOrderId'] ?? null,
            'station_id'      => $validated['stationId'],
            'reason_code'     => $validated['reasonCode'],
            'duration_minutes'=> $validated['durationMinutes'] ?? null,
            'note'            => $validated['note'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'eventId'         => $e->event_id,
                'startedAt'       => $e->started_at?->toIso8601String(),
                'endedAt'         => $e->ended_at?->toIso8601String(),
                'lineId'          => $e->line_id,
                'workOrderId'     => $e->work_order_id,
                'stationId'       => $e->station_id,
                'reasonCode'      => $e->reason_code,
                'durationMinutes' => $e->duration_minutes,
                'note'            => $e->note,
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/downtime-events/{eventId}
     */
    public function update(Request $request, string $eventId)
    {
        $validated = $request->validate([
            'startedAt'       => 'sometimes|string',
            'endedAt'         => 'sometimes|nullable|string',
            'lineId'          => 'sometimes|string',
            'workOrderId'     => 'sometimes|nullable|string',
            'stationId'       => 'sometimes|string',
            'reasonCode'      => 'sometimes|string',
            'durationMinutes' => 'sometimes|nullable|integer|min:0',
            'note'            => 'sometimes|nullable|string',
        ]);

        $e = DowntimeEvent::where('event_id', $eventId)->first();

        if (!$e) {
            return response()->json([
                'error' => [
                    'message' => 'Downtime event not found: '.$eventId,
                ],
            ], 404);
        }

        $update = [];

        if (array_key_exists('startedAt', $validated)) {
            $update['started_at'] = $validated['startedAt'];
        }
        if (array_key_exists('endedAt', $validated)) {
            $update['ended_at'] = $validated['endedAt'];
        }
        if (array_key_exists('lineId', $validated)) {
            $update['line_id'] = $validated['lineId'];
        }
        if (array_key_exists('workOrderId', $validated)) {
            $update['work_order_id'] = $validated['workOrderId'];
        }
        if (array_key_exists('stationId', $validated)) {
            $update['station_id'] = $validated['stationId'];
        }
        if (array_key_exists('reasonCode', $validated)) {
            $update['reason_code'] = $validated['reasonCode'];
        }
        if (array_key_exists('durationMinutes', $validated)) {
            $update['duration_minutes'] = $validated['durationMinutes'];
        }
        if (array_key_exists('note', $validated)) {
            $update['note'] = $validated['note'];
        }

        // 날짜 검증 (startedAt > endedAt 방지)
        $start = isset($update['started_at']) ? $this->parseDateTime($update['started_at']) : $e->started_at;
        $end   = array_key_exists('ended_at', $update)
            ? ($update['ended_at'] ? $this->parseDateTime($update['ended_at']) : null)
            : $e->ended_at;

        if ($start && $end && $start->gt($end)) {
            return response()->json([
                'message' => 'endedAt은 startedAt보다 이후여야 합니다.',
            ], 422);
        }

        if (!empty($update)) {
            $e->update($update);
        }

        return response()->json([
            'data' => [
                'eventId'         => $e->event_id,
                'startedAt'       => $e->started_at?->toIso8601String(),
                'endedAt'         => $e->ended_at?->toIso8601String(),
                'lineId'          => $e->line_id,
                'workOrderId'     => $e->work_order_id,
                'stationId'       => $e->station_id,
                'reasonCode'      => $e->reason_code,
                'durationMinutes' => $e->duration_minutes,
                'note'            => $e->note,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/downtime-events/{eventId}
     */
    public function destroy(string $eventId)
    {
        $e = DowntimeEvent::where('event_id', $eventId)->first();

        if (!$e) {
            return response()->json([
                'error' => [
                    'message' => 'Downtime event not found: '.$eventId,
                ],
            ], 404);
        }

        $e->delete();

        return response()->json([
            'data' => [
                'deleted' => $eventId,
            ],
        ]);
    }

    // =========================================
    // 내부 헬퍼
    // =========================================

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }
        return true;
    }

    private function getCell(array $row, array $headerIndex, string $column): ?string
    {
        if (!array_key_exists($column, $headerIndex)) {
            return null;
        }
        $idx = $headerIndex[$column] ?? null;

        return $idx !== null ? ($row[$idx] ?? null) : null;
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $format = 'Y-m-d\TH:i:sP';

        try {
            $dt = Carbon::createFromFormat($format, $value);
            if ($dt === false) {
                return null;
            }
            return $dt;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateEventIdFromDb(): string
    {
        $prefix = 'DT-';

        $count = DowntimeEvent::where('event_id', 'like', $prefix.'%')->count();

        $nextSeq = $count + 1;

        return $prefix . str_pad((string) $nextSeq, 6, '0', STR_PAD_LEFT);
    }
}
