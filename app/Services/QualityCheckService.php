<?php

namespace App\Services;

use App\Models\QualityCheck;
use App\Models\JobOrderReceive;
use App\Models\WarehouseStockMovement;
use Illuminate\Support\Facades\DB;

class QualityCheckService
{
    public function create(array $data, ?int $userId = null): QualityCheck
    {
        return DB::transaction(function () use ($data, $userId) {

            $inspected = (float) $data['quantity_inspected'];
            $passed    = (float) $data['quantity_passed'];
            $rejected  = round($inspected - $passed, 3);

            if ($rejected < -0.001) {
                throw new \Exception('Quantity passed cannot exceed quantity inspected.');
            }

            $qc = QualityCheck::create([
                'qc_no'                => $this->generateQcNo(),
                'job_order_receive_id' => $data['job_order_receive_id'],
                'product_id'           => $data['product_id'],
                'quantity_inspected'   => $inspected,
                'quantity_passed'      => $passed,
                'quantity_rejected'    => $rejected,
                'rejection_reason'     => $data['rejection_reason'] ?? null,
                'qc_date'              => $data['qc_date'],
                'remarks'              => $data['remarks'] ?? null,
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ]);

            if ($rejected > 0) {
                WarehouseStockMovement::create([
                    'product_id'      => $data['product_id'],
                    'movement_type'   => 'QCRejected',
                    'quantity'        => -$rejected,
                    'amount'          => 0, // quantity write-off, no valued reversal
                    'reference_type'  => 'QualityCheck',
                    'reference_id'    => $qc->id,
                    'movement_date'   => $data['qc_date'],
                ]);
            }

            return $qc->load('jobOrderReceive.jobOrder.vendor', 'product');
        });
    }

    public function delete(QualityCheck $qc): void
    {
        DB::transaction(function () use ($qc) {
            WarehouseStockMovement::where('reference_type', 'QualityCheck')
                ->where('reference_id', $qc->id)
                ->delete();

            $qc->delete();
        });
    }

    // Products available for QC — output lines from receives not yet fully inspected
    public function pendingReceiveOutputs()
    {
        return \App\Models\JobOrderReceiveOutput::with('jobOrderReceive.jobOrder.vendor', 'outputProduct')
            ->get()
            ->map(function ($output) {
                $alreadyQcd = QualityCheck::where('job_order_receive_id', $output->job_order_receive_id)
                    ->where('product_id', $output->output_product_id)
                    ->sum('quantity_inspected');

                $remaining = round((float) $output->quantity_output - (float) $alreadyQcd, 3);

                return [
                    'job_order_receive_id' => $output->job_order_receive_id,
                    'receive_no'           => $output->jobOrderReceive->receive_no ?? '',
                    'job_no'               => $output->jobOrderReceive->jobOrder->job_no ?? '',
                    'vendor_name'          => $output->jobOrderReceive->jobOrder->vendor->name ?? '',
                    'product_id'           => $output->output_product_id,
                    'product_name'         => $output->outputProduct->name ?? '',
                    'total_received'       => (float) $output->quantity_output,
                    'remaining_to_qc'      => $remaining,
                ];
            })
            ->filter(fn($row) => $row['remaining_to_qc'] > 0.001)
            ->values();
    }

    private function generateQcNo(): string
    {
        $last = QualityCheck::orderByDesc('id')->value('qc_no');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return 'QC-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}