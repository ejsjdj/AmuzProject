<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArrayExport;
use App\Models\Defect;
use Carbon\Carbon;

class DefectController extends Controller
{
    /**
     * GET /api/v1/defects
     *
     * - defects 테이블을 조회한다.
     * - 라인/스테이션/작업지시/불량코드/기간/defectId 필터, 정렬, 페이지네이션 지원.
     */
    public function index(Request $request)
    {
        $lineId      = $request->query('lineId');
        $stationId   = $request->query('stationId');
        $workOrderId = $request->query('workOrderId');
        $defectCode  = $request->query('defectCode');
        $from        = $request->query('from');      // YYYY-MM-DD
        $to          = $request->query('to');        // YYYY-MM-DD
        $defectId    = $request->query('defectId');

        $page    = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('perPage', 20), 1);
        $sortBy  = $request->query('sortBy', 'occurredAt');
        $sortDir = strtolower($request->query('sortDir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // 프론트 sortBy → DB 컬럼명 매핑
        $sortMap = [
            'occurredAt' => 'timestamp',
            'defectId'   => 'defect_id',
            'lineId'     => 'line_id',
            'stationId'  => 'station_id',
            'workOrderId'=> 'work_order_id',
            'defectCode' => 'defect_code',
            'qty'        => 'qty',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'timestamp';

        $query = Defect::query();

        $query->when($lineId,      fn($q, $v) => $q->where('line_id', $v));
        $query->when($stationId,   fn($q, $v) => $q->where('station_id', $v));
        $query->when($workOrderId, fn($q, $v) => $q->where('work_order_id', $v));
        $query->when($defectCode,  fn($q, $v) => $q->where('defect_code', $v));
        $query->when($defectId,    fn($q, $v) => $q->where('defect_id', $v));

        $query->when($from, fn($q, $v) => $q->whereDate('timestamp', '>=', $v));
        $query->when($to,   fn($q, $v) => $q->whereDate('timestamp', '<=', $v));

        $total = $query->count();

        $defects = $query->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $results = $defects->map(function (Defect $d) {
            return [
                'defectId'    => $d->defect_id,
                'occurredAt'  => $d->timestamp?->toIso8601String(),
                'shiftId'     => $d->shift_id,
                'lineId'      => $d->line_id,
                'workOrderId' => $d->work_order_id,
                'stationId'   => $d->station_id,
                'defectCode'  => $d->defect_code,
                'qty'         => $d->qty,
                'operatorId'  => $d->operator_id,
                'lotNo'       => $d->lot_no,
                'note'        => $d->note,
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
                    'lineId'      => $lineId,
                    'stationId'   => $stationId,
                    'workOrderId' => $workOrderId,
                    'defectCode'  => $defectCode,
                    'from'        => $from,
                    'to'          => $to,
                    'defectId'    => $defectId,
                ],
                'sort' => [
                    'sortBy'  => $sortBy,
                    'sortDir' => $sortDir,
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/defects/import
     * - 업로드된 엑셀을 읽어서 defects 테이블에 업데이트/추가
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt',
        ]);

        $file       = $request->file('file');
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
            'defectId',
            'workOrderId',
            'lineId',
            'stationId',
            'defectCode',
            'quantity',   // 엑셀 헤더에서 qty 대신 quantity 사용
            'occurredAt',
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

            $defectId    = $this->getCell($row, $headerIndex, 'defectId');
            $workOrderId = $this->getCell($row, $headerIndex, 'workOrderId');
            $lineId      = $this->getCell($row, $headerIndex, 'lineId');
            $stationId   = $this->getCell($row, $headerIndex, 'stationId');
            $defectCode  = $this->getCell($row, $headerIndex, 'defectCode');
            $quantity    = $this->getCell($row, $headerIndex, 'quantity');
            $occurredAt  = $this->getCell($row, $headerIndex, 'occurredAt');
            $operatorId  = $this->getCell($row, $headerIndex, 'operatorId');
            $lotNo       = $this->getCell($row, $headerIndex, 'lotNo');
            $note        = $this->getCell($row, $headerIndex, 'note');

            $quantityInt = is_numeric($quantity) ? (int) $quantity : null;

            if (
                !$workOrderId || !$lineId ||
                !$defectCode || $quantityInt === null || !$occurredAt
            ) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => '필수값(workOrderId, lineId, defectCode, quantity, occurredAt) 누락 또는 형식 오류',
                ];
                continue;
            }

            if (strlen($occurredAt) < 10) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => '날짜 형식 오류 (occurredAt)',
                ];
                continue;
            }

            // 날짜는 그대로 문자열로 저장 (ISO 형식)
            $timestamp = $occurredAt;

            if ($defectId) {
                $defect = Defect::where('defect_id', $defectId)->first();
            } else {
                $defectId = $this->generateDefectIdFromDb();
                $defect   = null;
            }

            if ($defect) {
                // 업데이트
                $defect->update([
                    'timestamp'     => $timestamp,
                    'line_id'       => $lineId,
                    'work_order_id' => $workOrderId,
                    'station_id'    => $stationId,
                    'defect_code'   => $defectCode,
                    'qty'           => $quantityInt,
                ]);
                $updated++;
            } else {
                // 새로 생성
                Defect::create([
                    'defect_id'     => $defectId,
                    'timestamp'     => $timestamp,
                    'line_id'       => $lineId,
                    'work_order_id' => $workOrderId,
                    'station_id'    => $stationId,
                    'defect_code'   => $defectCode,
                    'qty'           => $quantityInt,
                ]);
                $created++;
            }

            // operatorId, lotNo, note 는 지금 테이블에 없으니 무시
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
     * GET /api/v1/defects/export
     * - 현재 필터/정렬/페이지네이션 조건으로 defects 데이터를 엑셀로 Export
     */
    public function export(Request $request)
    {
        $lineId      = $request->query('lineId');
        $stationId   = $request->query('stationId');
        $workOrderId = $request->query('workOrderId');
        $defectCode  = $request->query('defectCode');
        $from        = $request->query('from');
        $to          = $request->query('to');
        $defectId    = $request->query('defectId');

        $page    = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('perPage', 20), 1);
        $sortBy  = $request->query('sortBy', 'occurredAt');
        $sortDir = strtolower($request->query('sortDir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'occurredAt' => 'timestamp',
            'defectId'   => 'defect_id',
            'lineId'     => 'line_id',
            'stationId'  => 'station_id',
            'workOrderId'=> 'work_order_id',
            'defectCode' => 'defect_code',
            'qty'        => 'qty',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'timestamp';

        $query = Defect::query();

        $query->when($lineId,      fn($q, $v) => $q->where('line_id', $v));
        $query->when($stationId,   fn($q, $v) => $q->where('station_id', $v));
        $query->when($workOrderId, fn($q, $v) => $q->where('work_order_id', $v));
        $query->when($defectCode,  fn($q, $v) => $q->where('defect_code', $v));
        $query->when($defectId,    fn($q, $v) => $q->where('defect_id', $v));
        $query->when($from,        fn($q, $v) => $q->whereDate('timestamp', '>=', $v));
        $query->when($to,          fn($q, $v) => $q->whereDate('timestamp', '<=', $v));

        $defects = $query->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $header = [
            'defectId',
            'workOrderId',
            'lineId',
            'stationId',
            'defectCode',
            'quantity',
            'occurredAt',
            'operatorId',
            'lotNo',
            'note',
        ];

        $rows   = [];
        $rows[] = $header;

        foreach ($defects as $d) {
            $rows[] = [
                $d->defect_id,
                $d->work_order_id,
                $d->line_id,
                $d->station_id,
                $d->defect_code,
                $d->qty,
                $d->timestamp?->toIso8601String(),
                null, // operatorId
                null, // lotNo
                null, // note
            ];
        }

        return Excel::download(new ArrayExport($rows), 'defects.xlsx');
    }

    /**
     * GET /api/v1/defects/{defectId}
     */
    public function show(string $defectId)
    {
        $d = Defect::where('defect_id', $defectId)->first();

        if (!$d) {
            return response()->json([
                'error' => [
                    'message' => 'Defect not found: '.$defectId,
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'defectId'    => $d->defect_id,
                'occurredAt'  => $d->timestamp?->toIso8601String(),
                'shiftId'     => $d->shift_id,
                'lineId'      => $d->line_id,
                'workOrderId' => $d->work_order_id,
                'stationId'   => $d->station_id,
                'defectCode'  => $d->defect_code,
                'qty'         => $d->qty,
                'operatorId'  => $d->operator_id,
                'lotNo'       => $d->lot_no,
                'note'        => $d->note,
            ],
        ]);
    }

    /**
     * POST /api/v1/defects
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'occurredAt' => 'required|string',
            'workOrderId'=> 'required|string',
            'lineId'     => 'required|string',
            'stationId'  => 'required|string',
            'defectCode' => 'required|string',
            'qty'        => 'required|integer|min:1',
            'operatorId' => 'nullable|string',
            'lotNo'      => 'nullable|string',
            'note'       => 'nullable|string',
        ]);

        $defectId  = $this->generateDefectIdFromDb();
        $timestamp = $validated['occurredAt'];

        $d = Defect::create([
            'defect_id'     => $defectId,
            'timestamp'     => $timestamp,
            'line_id'       => $validated['lineId'],
            'work_order_id' => $validated['workOrderId'],
            'station_id'    => $validated['stationId'],
            'defect_code'   => $validated['defectCode'],
            'qty'           => $validated['qty'],
            'operator_id'   => $validated['operatorId'] ?? null,
            'lot_no'        => $validated['lotNo'] ?? null,
            'note'          => $validated['note'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'defectId'    => $d->defect_id,
                'occurredAt'  => $d->timestamp?->toIso8601String(),
                'workOrderId' => $d->work_order_id,
                'lineId'      => $d->line_id,
                'stationId'   => $d->station_id,
                'defectCode'  => $d->defect_code,
                'qty'         => $d->qty,
                'operatorId'  => $validated['operatorId'] ?? null,
                'lotNo'       => $validated['lotNo'] ?? null,
                'note'        => $validated['note'] ?? null,
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/defects/{defectId}
     */
    public function update(Request $request, string $defectId)
    {
        $validated = $request->validate([
            'occurredAt' => 'sometimes|string',
            'qty'        => 'sometimes|integer|min:1',
            'note'       => 'sometimes|nullable|string',
            'lineId'     => 'sometimes|string',
            'stationId'  => 'sometimes|string',
            'defectCode' => 'sometimes|string',
            'operatorId' => 'sometimes|nullable|string',
            'lotNo'      => 'sometimes|nullable|string',
        ]);

        $d = Defect::where('defect_id', $defectId)->first();

        if (!$d) {
            return response()->json([
                'error' => [
                    'message' => 'Defect not found: '.$defectId,
                ],
            ], 404);
        }

        $update = [];

        if (array_key_exists('occurredAt', $validated)) {
            $update['timestamp'] = $validated['occurredAt'];
        }
        if (array_key_exists('qty', $validated)) {
            $update['qty'] = $validated['qty'];
        }
        if (array_key_exists('lineId', $validated)) {
            $update['line_id'] = $validated['lineId'];
        }
        if (array_key_exists('stationId', $validated)) {
            $update['station_id'] = $validated['stationId'];
        }
        if (array_key_exists('defectCode', $validated)) {
            $update['defect_code'] = $validated['defectCode'];
        }
        if (array_key_exists('operatorId', $validated)) {
            $update['operator_id'] = $validated['operatorId'];
        }
        if (array_key_exists('lotNo', $validated)) {
            $update['lot_no'] = $validated['lotNo'];
        }
        if (array_key_exists('note', $validated)) {
            $update['note'] = $validated['note'];
        }

        if (!empty($update)) {
            $d->update($update);
        }

        return response()->json([
            'data' => [
                'defectId'    => $d->defect_id,
                'occurredAt'  => $d->timestamp?->toIso8601String(),
                'workOrderId' => $d->work_order_id,
                'lineId'      => $d->line_id,
                'stationId'   => $d->station_id,
                'defectCode'  => $d->defect_code,
                'qty'         => $d->qty,
                'operatorId'  => null,
                'lotNo'       => null,
                'note'        => $validated['note'] ?? null,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/defects/{defectId}
     */
    public function destroy(string $defectId)
    {
        $d = Defect::where('defect_id', $defectId)->first();

        if (!$d) {
            return response()->json([
                'error' => [
                    'message' => 'Defect not found: '.$defectId,
                ],
            ], 404);
        }

        $d->delete();

        return response()->json([
            'data' => [
                'deleted' => $defectId,
            ],
        ]);
    }

    // ============================================================
    // 내부 헬퍼 메서드들 (엑셀 파싱용 + ID 생성)
    // ============================================================

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

    /**
     * DB 기준으로 새 defectId 생성 (DF-000001 패턴)
     */
    private function generateDefectIdFromDb(): string
    {
        $prefix = 'DF-';

        $count = Defect::where('defect_id', 'like', $prefix.'%')->count();

        $nextSeq = $count + 1;

        return $prefix . str_pad((string) $nextSeq, 6, '0', STR_PAD_LEFT);
    }
}
