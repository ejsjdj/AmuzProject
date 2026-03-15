<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WorkOrder;
use App\Models\ProductionRecord;
use App\Models\Defect;
use App\Models\DowntimeEvent;

class ImportMockData extends Command
{
    protected $signature = 'mock:import';
    protected $description = 'Import runtime data from mock-data.json into MySQL';

    public function handle(): int
    {
        $path = storage_path('app/mock-data.json');

        if (!file_exists($path)) {
            $this->error('mock-data.json not found: '.$path);
            return self::FAILURE;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->error('Failed to parse mock-data.json');
            return self::FAILURE;
        }

        $workOrders        = $data['runtime']['workOrders']        ?? [];
        $productionRecords = $data['runtime']['productionRecords'] ?? [];

        $this->info('Importing work orders: '.count($workOrders));

        foreach ($workOrders as $wo) {
            WorkOrder::updateOrCreate(
                ['work_order_id' => $wo['workOrderId']],
                [
                    'line_id'          => $wo['lineId'],
                    'product_id'       => $wo['productId'],
                    'routing_id'       => $wo['routingId'],
                    'planned_start_at' => $wo['plannedStartAt'],
                    'planned_end_at'   => $wo['plannedEndAt'],
                    'actual_start_at'  => $wo['actualStartAt'] ?? null,
                    'actual_end_at'    => $wo['actualEndAt'] ?? null,
                    'target_qty'       => $wo['targetQty'],
                    'status'           => $wo['status'],
                    'priority'         => $wo['priority'],
                ]
            );
        }

        $this->info('Importing production records: '.count($productionRecords));

        foreach ($productionRecords as $pr) {
            ProductionRecord::updateOrCreate(
                ['record_id' => $pr['recordId']],
                [
                    'timestamp'     => $pr['timestamp'],
                    'shift_id'      => $pr['shiftId'],
                    'line_id'       => $pr['lineId'],
                    'work_order_id' => $pr['workOrderId'],
                    'station_id'    => $pr['stationId'],
                    'input_qty'     => $pr['inputQty'],
                    'good_qty'      => $pr['goodQty'],
                    'scrap_qty'     => $pr['scrapQty'],
                ]
            );
        }

        $defects = $data['runtime']['defects'] ?? [];

        $this->info('Importing defects: '.count($defects));

        foreach ($defects as $d) {
            Defect::updateOrCreate(
                ['defect_id' => $d['defectId']],
                [
                    'timestamp'     => $d['occurredAt'] ?? null,
                    'shift_id'      => $d['shiftId']     ?? null,
                    'line_id'       => $d['lineId'],
                    'work_order_id' => $d['workOrderId'],
                    'station_id'    => $d['stationId'],
                    'defect_code'   => $d['defectCode'],
                    'qty'           => $d['qty'],
                    'operator_id'   => $d['operatorId']  ?? null,
                    'lot_no'        => $d['lotNo']       ?? null,
                    'note'          => $d['note']        ?? null,
                ]
            );
        }

        $downtimeEvents = $data['runtime']['downtimeEvents'] ?? [];

        $this->info('Importing downtime events: '.count($downtimeEvents));

        foreach ($downtimeEvents as $e) {
            // durationMinutes 는 mock-data 에 있고, endedAt - startedAt 이 안 맞으면 mock 값 그대로 사용
            DowntimeEvent::updateOrCreate(
                ['event_id' => $e['eventId']],
                [
                    'started_at'       => $e['startedAt'] ?? null,
                    'ended_at'         => $e['endedAt']   ?? null,
                    'line_id'          => $e['lineId'],
                    'work_order_id'    => $e['workOrderId'] ?? null,
                    'station_id'       => $e['stationId'],
                    'reason_code'      => $e['reasonCode'],
                    'duration_minutes' => $e['durationMinutes'] ?? null,
                    'note'             => $e['note'] ?? null,
                ]
            );
        }

        $this->info('Import finished.');
        return self::SUCCESS;
    }
}
