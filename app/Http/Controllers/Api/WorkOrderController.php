<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\WorkOrdersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\WorkOrder;
use App\Models\ProductionRecord;

class WorkOrderController extends Controller
{
    /**
     * GET /api/v1/work-orders
     *
     * - DB의 work_orders + production_records 를 사용해서 작업지시 목록을 조회한다.
     * - 필터(lineId, productId, from, to, search), 정렬(sortBy, sortDir), 페이지네이션(page, perPage)을 지원한다.
     * - 각 작업지시에 현재까지의 양품 수량(currentGoodQty)을 계산해서 함께 내려준다.
     */
    public function index(Request $request)
    {
        $lineId    = $request->query('lineId');
        $productId = $request->query('productId');
        $from      = $request->query('from');   // YYYY-MM-DD
        $to        = $request->query('to');     // YYYY-MM-DD
        $search    = $request->query('search'); // 작업지시 ID 검색

        $page    = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('perPage', 20), 1);
        $sortBy  = $request->query('sortBy', 'plannedStartAt');
        $sortDir = strtolower($request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // 프론트에서 넘어오는 sortBy 값 → DB 컬럼명 매핑
        $sortMap = [
            'plannedStartAt' => 'planned_start_at',
            'plannedEndAt'   => 'planned_end_at',
            'actualStartAt'  => 'actual_start_at',
            'actualEndAt'    => 'actual_end_at',
            'targetQty'      => 'target_qty',
            'status'         => 'status',
            'priority'       => 'priority',
            'workOrderId'    => 'work_order_id',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'planned_start_at';

        $query = WorkOrder::query();

        // 필터
        $query->when($lineId,    fn($q, $v) => $q->where('line_id', $v));
        $query->when($productId, fn($q, $v) => $q->where('product_id', $v));
        $query->when($from,      fn($q, $v) => $q->whereDate('planned_start_at', '>=', $v));
        $query->when($to,        fn($q, $v) => $q->whereDate('planned_start_at', '<=', $v));
        $query->when($search,    fn($q, $v) => $q->where('work_order_id', 'like', '%'.$v.'%'));

        $total = $query->count();

        $workOrders = $query->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // goodQty 집계 (production_records 기준)
        $goodQtyByWorkOrder = ProductionRecord::selectRaw('work_order_id, SUM(good_qty) as sum_good')
            ->whereIn('work_order_id', $workOrders->pluck('work_order_id'))
            ->groupBy('work_order_id')
            ->pluck('sum_good', 'work_order_id'); // key: work_order_id

        // 응답 포맷을 기존 json 구조와 맞추기
        $results = $workOrders->map(function ($wo) use ($goodQtyByWorkOrder) {
            $id = $wo->work_order_id;
            $currentGoodQty = $goodQtyByWorkOrder->get($id, 0);

            return [
                'workOrderId'    => $id,
                'lineId'         => $wo->line_id,
                'productId'      => $wo->product_id,
                'routingId'      => $wo->routing_id,
                'plannedStartAt' => $wo->planned_start_at?->toIso8601String(),
                'plannedEndAt'   => $wo->planned_end_at?->toIso8601String(),
                'actualStartAt'  => $wo->actual_start_at?->toIso8601String(),
                'actualEndAt'    => $wo->actual_end_at?->toIso8601String(),
                'targetQty'      => $wo->target_qty,
                'status'         => $wo->status,
                'priority'       => $wo->priority,
                'currentGoodQty' => $currentGoodQty,
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
                    'lineId'    => $lineId,
                    'productId' => $productId,
                    'from'      => $from,
                    'to'        => $to,
                    'search'    => $search,
                ],
                'sort' => [
                    'sortBy'  => $sortBy,
                    'sortDir' => $sortDir,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/work-orders/export
     * - 현재 필터/검색/정렬/페이지네이션 조건으로 데이터를 엑셀로 Export
     */
    public function export(Request $request)
    {
        // index()와 동일한 필터/정렬 로직 재사용
        $lineId    = $request->query('lineId');
        $productId = $request->query('productId');
        $from      = $request->query('from');
        $to        = $request->query('to');
        $search    = $request->query('search');

        $page    = max((int) $request->query('page', 1), 1);
        $perPage = max((int) $request->query('perPage', 20), 1);
        $sortBy  = $request->query('sortBy', 'plannedStartAt');
        $sortDir = strtolower($request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'plannedStartAt' => 'planned_start_at',
            'plannedEndAt'   => 'planned_end_at',
            'actualStartAt'  => 'actual_start_at',
            'actualEndAt'    => 'actual_end_at',
            'targetQty'      => 'target_qty',
            'status'         => 'status',
            'priority'       => 'priority',
            'workOrderId'    => 'work_order_id',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'planned_start_at';

        $query = WorkOrder::query();

        $query->when($lineId,    fn($q, $v) => $q->where('line_id', $v));
        $query->when($productId, fn($q, $v) => $q->where('product_id', $v));
        $query->when($from,      fn($q, $v) => $q->whereDate('planned_start_at', '>=', $v));
        $query->when($to,        fn($q, $v) => $q->whereDate('planned_start_at', '<=', $v));
        $query->when($search,    fn($q, $v) => $q->where('work_order_id', 'like', '%'.$v.'%'));

        $workOrders = $query->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $goodQtyByWorkOrder = ProductionRecord::selectRaw('work_order_id, SUM(good_qty) as sum_good')
            ->whereIn('work_order_id', $workOrders->pluck('work_order_id'))
            ->groupBy('work_order_id')
            ->pluck('sum_good', 'work_order_id');

        $exportRows = $workOrders->map(function ($wo) use ($goodQtyByWorkOrder) {
            $id = $wo->work_order_id;
            $currentGoodQty = $goodQtyByWorkOrder->get($id, 0);

            return [
                'workOrderId'    => $id,
                'lineId'         => $wo->line_id,
                'productId'      => $wo->product_id,
                'routingId'      => $wo->routing_id,
                'plannedStartAt' => $wo->planned_start_at?->toIso8601String(),
                'plannedEndAt'   => $wo->planned_end_at?->toIso8601String(),
                'actualStartAt'  => $wo->actual_start_at?->toIso8601String(),
                'actualEndAt'    => $wo->actual_end_at?->toIso8601String(),
                'targetQty'      => $wo->target_qty,
                'status'         => $wo->status,
                'priority'       => $wo->priority,
                'currentGoodQty' => $currentGoodQty,
            ];
        })->all();

        return Excel::download(new WorkOrdersExport($exportRows), 'work-orders.xlsx');
    }

    /**
     * POST /api/v1/work-orders/import
     *
     * - 업로드된 엑셀(xlsx/csv)을 읽어서 work_orders 테이블을 업데이트/추가한다.
     * - 기존 workOrderId 존재 시 업데이트, 없으면 새로 생성.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt',
        ]);

        $file = $request->file('file');

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
            'workOrderId',
            'lineId',
            'productId',
            'routingId',
            'plannedStartAt',
            'plannedEndAt',
            'actualStartAt',
            'actualEndAt',
            'targetQty',
            'status',
            'priority',
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

            $workOrderId    = $this->getCell($row, $headerIndex, 'workOrderId');
            $lineId         = $this->getCell($row, $headerIndex, 'lineId');
            $productId      = $this->getCell($row, $headerIndex, 'productId');
            $routingId      = $this->getCell($row, $headerIndex, 'routingId');
            $plannedStartAt = $this->getCell($row, $headerIndex, 'plannedStartAt');
            $plannedEndAt   = $this->getCell($row, $headerIndex, 'plannedEndAt');
            $actualStartAt  = $this->getCell($row, $headerIndex, 'actualStartAt');
            $actualEndAt    = $this->getCell($row, $headerIndex, 'actualEndAt');
            $targetQty      = $this->getCell($row, $headerIndex, 'targetQty');
            $status         = $this->getCell($row, $headerIndex, 'status');
            $priority       = $this->getCell($row, $headerIndex, 'priority');

            $targetQtyInt = is_numeric($targetQty) ? (int) $targetQty : null;

            if (
                !$lineId || !$productId || !$routingId ||
                !$plannedStartAt || !$plannedEndAt || $targetQtyInt === null
            ) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => '필수값(lineId, productId, routingId, plannedStartAt, plannedEndAt, targetQty) 누락 또는 형식 오류',
                ];
                continue;
            }

            // 날짜 파싱
            $plannedStart = $this->parseDateTime($plannedStartAt);
            $plannedEnd   = $this->parseDateTime($plannedEndAt);
            $actualStart  = $this->parseDateTime($actualStartAt);
            $actualEnd    = $this->parseDateTime($actualEndAt);

            if (!$plannedStart || !$plannedEnd) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => '날짜 형식 오류 (plannedStartAt/plannedEndAt)',
                ];
                continue;
            }

            if ($plannedStart->gt($plannedEnd)) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => 'plannedStartAt이 plannedEndAt보다 늦을 수 없습니다.',
                ];
                continue;
            }

            if ($actualStart && $actualEnd && $actualStart->gt($actualEnd)) {
                $errors[] = [
                    'row'    => $i + 1,
                    'reason' => 'actualStartAt이 actualEndAt보다 늦을 수 없습니다.',
                ];
                continue;
            }

            // 없으면 새로 ID 생성
            if (!$workOrderId) {
                $workOrderId = $this->generateWorkOrderIdFromDb();
            }

            $wo = WorkOrder::where('work_order_id', $workOrderId)->first();

            if ($wo) {
                // 업데이트
                $wo->update([
                    'line_id'          => $lineId,
                    'product_id'       => $productId,
                    'routing_id'       => $routingId,
                    'planned_start_at' => $plannedStartAt,
                    'planned_end_at'   => $plannedEndAt,
                    'actual_start_at'  => $actualStartAt ?: null,
                    'actual_end_at'    => $actualEndAt ?: null,
                    'target_qty'       => $targetQtyInt,
                    'status'           => $status ?: $wo->status,
                    'priority'         => $priority ?: $wo->priority,
                ]);
                $updated++;
            } else {
                // 새 작업지시
                WorkOrder::create([
                    'work_order_id'    => $workOrderId,
                    'line_id'          => $lineId,
                    'product_id'       => $productId,
                    'routing_id'       => $routingId,
                    'planned_start_at' => $plannedStartAt,
                    'planned_end_at'   => $plannedEndAt,
                    'actual_start_at'  => $actualStartAt ?: null,
                    'actual_end_at'    => $actualEndAt ?: null,
                    'target_qty'       => $targetQtyInt,
                    'status'           => $status ?: 'PLANNED',
                    'priority'         => $priority ?: 'NORMAL',
                ]);
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
     * POST /api/v1/work-orders
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lineId'         => 'required|string',
            'productId'      => 'required|string',
            'routingId'      => 'required|string',
            'plannedStartAt' => 'required|string',
            'plannedEndAt'   => 'required|string',
            'targetQty'      => 'required|integer|min:1',
            'priority'       => 'nullable|string',
        ]);

        $plannedStart = $this->parseDateTime($validated['plannedStartAt']);
        $plannedEnd   = $this->parseDateTime($validated['plannedEndAt']);

        if (!$plannedStart || !$plannedEnd) {
            return response()->json([
                'message' => '날짜 형식 오류 (plannedStartAt/plannedEndAt)',
            ], 422);
        }

        if ($plannedStart->gt($plannedEnd)) {
            return response()->json([
                'message' => '계획 종료 시간은 계획 시작 시간보다 이후여야 합니다.',
            ], 422);
        }

        $workOrderId = $this->generateWorkOrderIdFromDb();

        $wo = WorkOrder::create([
            'work_order_id'    => $workOrderId,
            'line_id'          => $validated['lineId'],
            'product_id'       => $validated['productId'],
            'routing_id'       => $validated['routingId'],
            'planned_start_at' => $validated['plannedStartAt'],
            'planned_end_at'   => $validated['plannedEndAt'],
            'actual_start_at'  => null,
            'actual_end_at'    => null,
            'target_qty'       => $validated['targetQty'],
            'status'           => 'PLANNED',
            'priority'         => $validated['priority'] ?? 'NORMAL',
        ]);

        return response()->json([
            'data' => [
                'workOrderId'    => $wo->work_order_id,
                'lineId'         => $wo->line_id,
                'productId'      => $wo->product_id,
                'routingId'      => $wo->routing_id,
                'plannedStartAt' => $wo->planned_start_at?->toIso8601String(),
                'plannedEndAt'   => $wo->planned_end_at?->toIso8601String(),
                'actualStartAt'  => null,
                'actualEndAt'    => null,
                'targetQty'      => $wo->target_qty,
                'status'         => $wo->status,
                'priority'       => $wo->priority,
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/work-orders/{workOrderId}
     */
    public function update(Request $request, string $workOrderId)
    {
        $validated = $request->validate([
            'plannedStartAt' => 'sometimes|string',
            'plannedEndAt'   => 'sometimes|string',
            'actualStartAt'  => 'sometimes|nullable|string',
            'actualEndAt'    => 'sometimes|nullable|string',
            'targetQty'      => 'sometimes|integer|min:1',
            'status'         => 'sometimes|string',
            'priority'       => 'sometimes|string',
        ]);

        $wo = WorkOrder::where('work_order_id', $workOrderId)->first();

        if (!$wo) {
            return response()->json([
                'error' => [
                    'message' => 'WorkOrder not found: ' . $workOrderId,
                ],
            ], 404);
        }

        // 현재 값 + 변경 값 merge (문자열 기준으로)
        $merged = array_merge([
            'plannedStartAt' => $wo->planned_start_at?->toIso8601String(),
            'plannedEndAt'   => $wo->planned_end_at?->toIso8601String(),
            'actualStartAt'  => $wo->actual_start_at?->toIso8601String(),
            'actualEndAt'    => $wo->actual_end_at?->toIso8601String(),
            'targetQty'      => $wo->target_qty,
            'status'         => $wo->status,
            'priority'       => $wo->priority,
        ], $validated);

        $plannedStartAt = $merged['plannedStartAt'] ?? null;
        $plannedEndAt   = $merged['plannedEndAt'] ?? null;
        $actualStartAt  = $merged['actualStartAt'] ?? null;
        $actualEndAt    = $merged['actualEndAt'] ?? null;

        $plannedStart = $plannedStartAt ? $this->parseDateTime($plannedStartAt) : null;
        $plannedEnd   = $plannedEndAt ? $this->parseDateTime($plannedEndAt) : null;
        $actualStart  = $actualStartAt ? $this->parseDateTime($actualStartAt) : null;
        $actualEnd    = $actualEndAt ? $this->parseDateTime($actualEndAt) : null;

        if (($plannedStartAt && !$plannedStart) || ($plannedEndAt && !$plannedEnd)) {
            return response()->json([
                'message' => '날짜 형식 오류 (plannedStartAt/plannedEndAt)',
            ], 422);
        }

        if ($plannedStart && $plannedEnd && $plannedStart->gt($plannedEnd)) {
            return response()->json([
                'message' => '계획 종료 시간은 계획 시작 시간보다 이후여야 합니다.',
            ], 422);
        }

        if (($actualStartAt && !$actualStart) || ($actualEndAt && !$actualEnd)) {
            return response()->json([
                'message' => '날짜 형식 오류 (actualStartAt/actualEndAt)',
            ], 422);
        }

        if ($actualStart && $actualEnd && $actualStart->gt($actualEnd)) {
            return response()->json([
                'message' => '실제 종료 시간은 실제 시작 시간보다 이후여야 합니다.',
            ], 422);
        }

        $wo->update([
            'planned_start_at' => $plannedStartAt,
            'planned_end_at'   => $plannedEndAt,
            'actual_start_at'  => $actualStartAt,
            'actual_end_at'    => $actualEndAt,
            'target_qty'       => $merged['targetQty'],
            'status'           => $merged['status'],
            'priority'         => $merged['priority'],
        ]);

        return response()->json([
            'data' => [
                'workOrderId'    => $wo->work_order_id,
                'lineId'         => $wo->line_id,
                'productId'      => $wo->product_id,
                'routingId'      => $wo->routing_id,
                'plannedStartAt' => $wo->planned_start_at?->toIso8601String(),
                'plannedEndAt'   => $wo->planned_end_at?->toIso8601String(),
                'actualStartAt'  => $wo->actual_start_at?->toIso8601String(),
                'actualEndAt'    => $wo->actual_end_at?->toIso8601String(),
                'targetQty'      => $wo->target_qty,
                'status'         => $wo->status,
                'priority'       => $wo->priority,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/work-orders/{workOrderId}
     */
    public function destroy(string $workOrderId)
    {
        $wo = WorkOrder::where('work_order_id', $workOrderId)->first();

        if (!$wo) {
            return response()->json([
                'error' => [
                    'message' => 'WorkOrder not found: ' . $workOrderId,
                ],
            ], 404);
        }

        $wo->delete();

        return response()->json([
            'data' => [
                'deleted' => $workOrderId,
            ],
        ]);
    }

    // ============================================================
    // 내부 헬퍼 메서드들 (json 저장 관련은 삭제)
    // ============================================================

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }
        return true;
    }

    private function getCell(array $row, array $headerIndex, string $columnName): ?string
    {
        if (!isset($headerIndex[$columnName])) {
            return null;
        }
        $idx = $headerIndex[$columnName];
        return isset($row[$idx]) ? (string) $row[$idx] : null;
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

    /**
     * DB 기준으로 새로운 workOrderId 생성
     * 예: 현재 월의 개수 + 1 → WO-2603-0001
     */
    private function generateWorkOrderIdFromDb(): string
    {
        $prefix = 'WO-' . date('ym') . '-';

        $countThisMonth = WorkOrder::where('work_order_id', 'like', $prefix.'%')->count();

        $nextSeq = $countThisMonth + 1;

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
