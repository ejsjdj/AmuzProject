<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductionRecord;
use App\Models\Defect;
use App\Models\WorkOrder;
use App\Models\DowntimeEvent;

class DashboardController extends Controller
{
    /**
     * 전체 KPI
     * - 총 생산, 양품, 불량, 불량률, defects 기준 불량 수량
     * - 가동 관련 지표: Availability, Performance, Quality, OEE
     * - 필터: lineId, from, to
     */
    public function kpi(Request $request)
    {
        $lineId = $request->query('lineId');
        $from   = $request->query('from'); // YYYY-MM-DD
        $to     = $request->query('to');   // YYYY-MM-DD

        // --------------------------------------------------
        // 1) 생산량, 불량률 계산 (production_records + defects)
        // --------------------------------------------------

        // 생산 기록 필터링 (DB)
        $prQuery = ProductionRecord::query();
        $prQuery->when($lineId, fn($q, $v) => $q->where('line_id', $v));
        $prQuery->when($from,   fn($q, $v) => $q->whereDate('timestamp', '>=', $v));
        $prQuery->when($to,     fn($q, $v) => $q->whereDate('timestamp', '<=', $v));

        $totalGood      = (clone $prQuery)->sum('good_qty');
        $totalScrap     = (clone $prQuery)->sum('scrap_qty');
        $totalProduced  = $totalGood + $totalScrap;

        $defectRate = $totalProduced > 0
            ? round($totalScrap / $totalProduced * 100, 2)
            : 0;

        // defects 기준 불량 수량 (같은 필터)
        $dfQuery = Defect::query();
        $dfQuery->when($lineId, fn($q, $v) => $q->where('line_id', $v));
        $dfQuery->when($from,   fn($q, $v) => $q->whereDate('timestamp', '>=', $v));
        $dfQuery->when($to,     fn($q, $v) => $q->whereDate('timestamp', '<=', $v));

        $totalDefectQty = (clone $dfQuery)->sum('qty');

        // --------------------------------------------------
        // 2) 가동 지표 계산 (work_orders + downtime_events)
        // --------------------------------------------------

        // 기간 + 라인으로 workOrders 필터
        $woQuery = WorkOrder::query();
        $woQuery->when($lineId, fn($q, $v) => $q->where('line_id', $v));
        $woQuery->when($from,   fn($q, $v) => $q->whereDate('planned_start_at', '>=', $v));
        $woQuery->when($to,     fn($q, $v) => $q->whereDate('planned_start_at', '<=', $v));

        $workOrders = $woQuery->get();

        $plannedMinutes = 0;
        $totalTargetQty = 0;

        foreach ($workOrders as $wo) {
            $plannedStartAt = $wo->planned_start_at;
            $plannedEndAt   = $wo->planned_end_at;

            if ($plannedStartAt && $plannedEndAt) {
                $start = strtotime($plannedStartAt);
                $end   = strtotime($plannedEndAt);

                if ($start !== false && $end !== false && $start < $end) {
                    $plannedMinutes += (int) round(($end - $start) / 60);
                }
            }

            $totalTargetQty += $wo->target_qty ?? 0;
        }

        // downtimeEvents 필터 (lineId/from/to 기준)
        $dtQuery = DowntimeEvent::query();
        $dtQuery->when($lineId, fn($q, $v) => $q->where('line_id', $v));
        $dtQuery->when($from,   fn($q, $v) => $q->whereDate('started_at', '>=', $v));
        $dtQuery->when($to,     fn($q, $v) => $q->whereDate('started_at', '<=', $v));

        $downtimes = $dtQuery->get();

        // 정지시간(분) 합계
        $downtimeMinutes = 0;
        foreach ($downtimes as $e) {
            $startedAt = $e->started_at?->toIso8601String();
            $endedAt   = $e->ended_at?->toIso8601String();

            $duration = $this->calcDurationMinutes($startedAt, $endedAt);
            if ($duration !== null) {
                $downtimeMinutes += $duration;
            }
        }

        // 실제 가동시간(분)
        $runtimeMinutes = max($plannedMinutes - $downtimeMinutes, 0);

        // --------------------------------------------------
        // 3) Availability / Performance / Quality / OEE 계산
        // --------------------------------------------------

        // Availability: 실제 가동시간 / 계획시간
        $availability = $plannedMinutes > 0
            ? round($runtimeMinutes / $plannedMinutes * 100, 2)
            : 0;

        // Performance: 실제 생산량 / 이론상 최대 생산량 (여기서는 targetQty 합)
        $actualOutput = $totalProduced;
        $performance  = $totalTargetQty > 0
            ? round($actualOutput / $totalTargetQty * 100, 2)
            : 0;

        // Quality: 양품 / 총 생산
        $quality = $totalProduced > 0
            ? round($totalGood / $totalProduced * 100, 2)
            : 0;

        // OEE: A * P * Q / 100^2
        $oee = ($availability > 0 && $performance > 0 && $quality > 0)
            ? round($availability * $performance * $quality / 10000, 2)
            : 0;

        return response()->json([
            'data' => [
                'totalProduced'  => $totalProduced,
                'totalGood'      => $totalGood,
                'totalScrap'     => $totalScrap,
                'defectRate'     => $defectRate,
                'totalDefectQty' => $totalDefectQty,

                'plannedMinutes'  => $plannedMinutes,
                'downtimeMinutes' => $downtimeMinutes,
                'runtimeMinutes'  => $runtimeMinutes,
                'totalTargetQty'  => $totalTargetQty,
                'availability'    => $availability,
                'performance'     => $performance,
                'quality'         => $quality,
                'oee'             => $oee,
            ],
            'meta' => [
                'filters' => [
                    'lineId' => $lineId,
                    'from'   => $from,
                    'to'     => $to,
                ],
            ],
        ]);
    }

    /**
     * 품질 파레토 (불량코드 기준)
     * - 필터: lineId, from, to
     * - 집계는 DB(defects), 이름 매핑은 mock-data.master.defectCodes 사용
     */
    public function quality(Request $request)
    {
        $data = $this->loadMockData();
        $defectCodesM = $data['master']['defectCodes'] ?? [];

        // defectCode → name 매핑
        $defectCodeNameMap = [];
        foreach ($defectCodesM as $row) {
            if (!isset($row['defectCode'])) {
                continue;
            }
            $defectCodeNameMap[$row['defectCode']] = $row['name'] ?? $row['defectCode'];
        }

        $lineId = $request->query('lineId');
        $from   = $request->query('from'); // YYYY-MM-DD
        $to     = $request->query('to');   // YYYY-MM-DD

        // DB에서 defects 필터링
        $query = Defect::query();
        $query->when($lineId, fn($q, $v) => $q->where('line_id', $v));
        $query->when($from,   fn($q, $v) => $q->whereDate('timestamp', '>=', $v));
        $query->when($to,     fn($q, $v) => $q->whereDate('timestamp', '<=', $v));

        $rows = $query->get(['defect_code', 'qty']);

        // defectCode별 qty 합산
        $byCode         = [];
        $totalDefectQty = 0;

        foreach ($rows as $df) {
            $code = $df->defect_code;
            if (!$code) {
                continue;
            }

            $qty = $df->qty ?? 0;

            if (!isset($byCode[$code])) {
                $byCode[$code] = 0;
            }
            $byCode[$code] += $qty;
            $totalDefectQty += $qty;
        }

        // 파레토용 배열로 변환 + 이름 붙이기
        $pareto = [];
        foreach ($byCode as $code => $qty) {
            $pareto[] = [
                'defectCode' => $code,
                'defectName' => $defectCodeNameMap[$code] ?? $code,
                'qty'        => $qty,
            ];
        }

        // qty 기준 내림차순 정렬
        usort($pareto, function ($a, $b) {
            return $b['qty'] <=> $a['qty'];
        });

        return response()->json([
            'data' => [
                'paretoByDefectCode' => $pareto,
                'totalDefectQty'     => $totalDefectQty,
            ],
            'meta' => [
                'filters' => [
                    'lineId' => $lineId,
                    'from'   => $from,
                    'to'     => $to,
                ],
            ],
        ]);
    }

    /**
     * 생산 현황 (시간대별 생산량)
     * - 필터: lineId, from, to
     * - 1시간 단위로 good/scrap 합산
     */
    public function production(Request $request)
    {
        $lineId = $request->query('lineId');
        $from   = $request->query('from'); // YYYY-MM-DD
        $to     = $request->query('to');   // YYYY-MM-DD

        $query = ProductionRecord::query();
        $query->when($lineId, fn($q, $v) => $q->where('line_id', $v));
        $query->when($from,   fn($q, $v) => $q->whereDate('timestamp', '>=', $v));
        $query->when($to,     fn($q, $v) => $q->whereDate('timestamp', '<=', $v));

        $records = $query->get();

        $byHour     = [];
        $totalGood  = 0;
        $totalScrap = 0;

        foreach ($records as $pr) {
            $ts = $pr->timestamp?->toIso8601String();
            if (!$ts) {
                continue;
            }

            // "2026-01-01 00:00" 같은 키로 정규화
            $hourKey = substr($ts, 0, 13);               // "2026-01-01T00"
            $hourKey = str_replace('T', ' ', $hourKey) . ':00';

            $good  = $pr->good_qty  ?? 0;
            $scrap = $pr->scrap_qty ?? 0;

            if (!isset($byHour[$hourKey])) {
                $byHour[$hourKey] = [
                    'time'     => $hourKey,
                    'goodQty'  => 0,
                    'scrapQty' => 0,
                ];
            }

            $byHour[$hourKey]['goodQty']  += $good;
            $byHour[$hourKey]['scrapQty'] += $scrap;

            $totalGood  += $good;
            $totalScrap += $scrap;
        }

        // 시간순 정렬
        usort($byHour, function ($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

        return response()->json([
            'data' => [
                'byHour'     => $byHour,
                'totalGood'  => $totalGood,
                'totalScrap' => $totalScrap,
            ],
            'meta' => [
                'filters' => [
                    'lineId' => $lineId,
                    'from'   => $from,
                    'to'     => $to,
                ],
            ],
        ]);
    }

    // ============================================================
    // 공통 헬퍼
    // ============================================================

    /**
     * mock-data.json을 읽어서 배열로 반환한다.
     * - 지금은 defectCodes 같은 master 정보용으로만 사용.
     */
    private function loadMockData(): array
    {
        $path = storage_path('app/mock-data.json');

        if (!file_exists($path)) {
            abort(500, 'mock-data.json file not found: ' . $path);
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            abort(500, 'Failed to parse mock-data.json');
        }

        return $data;
    }

    private function calcDurationMinutes(?string $startedAt, ?string $endedAt): ?int
    {
        if (!$startedAt || !$endedAt) {
            return null;
        }

        $start = strtotime($startedAt);
        $end   = strtotime($endedAt);

        if ($start === false || $end === false || $start > $end) {
            return null;
        }

        return (int) round(($end - $start) / 60);
    }
}
