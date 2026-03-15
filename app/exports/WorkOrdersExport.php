<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WorkOrdersExport implements FromArray, WithHeadings
{
    public string $fileName = 'work-orders.xlsx';

    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function headings(): array
    {
        return [
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
            'currentGoodQty',
        ];
    }

    public function array(): array
    {
        return array_map(function ($wo) {
            return [
                $wo['workOrderId']    ?? null,
                $wo['lineId']         ?? null,
                $wo['productId']      ?? null,
                $wo['routingId']      ?? null,
                $wo['plannedStartAt'] ?? null,
                $wo['plannedEndAt']   ?? null,
                $wo['actualStartAt']  ?? null,
                $wo['actualEndAt']    ?? null,
                $wo['targetQty']      ?? null,
                $wo['status']         ?? null,
                $wo['priority']       ?? null,
                $wo['currentGoodQty'] ?? null,
            ];
        }, $this->rows);
    }
}
