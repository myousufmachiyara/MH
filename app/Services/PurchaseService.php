<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\WarehouseStockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    public function create(array $data, array $items, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
            }
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $total     = $subtotal + $taxAmount;

            $purchase = Purchase::create(array_merge($data, [
                'purchase_no'   => $this->generatePurchaseNo(),
                'subtotal'      => $subtotal,
                'tax_amount'    => $taxAmount,
                'total_amount'  => $total,
                'status'        => $data['status'] ?? 'Posted',
                'created_by'    => $userId,
                'updated_by'    => $userId,
            ]));

            foreach ($items as $item) {
                $amount = (float) $item['quantity'] * (float) $item['unit_price'];

                $purchaseItem = PurchaseItem::create([
                    'purchase_id'           => $purchase->id,
                    'product_id'            => $item['product_id'],
                    'product_variation_id'  => $item['product_variation_id'] ?? null,
                    'quantity'              => $item['quantity'],
                    'unit_price'            => $item['unit_price'],
                    'amount'                => $amount,
                ]);

                // Stock in — increases the client's own warehouse stock,
                // records the value (used for weighted-average costing).
                WarehouseStockMovement::create([
                    'product_id'      => $item['product_id'],
                    'movement_type'   => 'Purchase',
                    'quantity'        => $item['quantity'],
                    'amount'          => $amount,
                    'reference_type'  => 'Purchase',
                    'reference_id'    => $purchase->id,
                    'movement_date'   => $purchase->purchase_date,
                ]);
            }

            $this->postVoucher($purchase, $userId);

            return $purchase->load('items.product', 'vendor');
        });
    }

    public function update(Purchase $purchase, array $data, array $items, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $items, $userId) {

            // Reverse old stock movements + voucher, then re-create —
            // simplest way to keep everything consistent on edit.
            WarehouseStockMovement::where('reference_type', 'Purchase')
                ->where('reference_id', $purchase->id)
                ->delete();

            $this->voucherService->deleteByReference('Purchase', $purchase->id);

            $purchase->items()->delete();

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
            }
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $total     = $subtotal + $taxAmount;

            $purchase->update(array_merge($data, [
                'subtotal'      => $subtotal,
                'tax_amount'    => $taxAmount,
                'total_amount'  => $total,
                'updated_by'    => $userId,
            ]));

            foreach ($items as $item) {
                $amount = (float) $item['quantity'] * (float) $item['unit_price'];

                PurchaseItem::create([
                    'purchase_id'           => $purchase->id,
                    'product_id'            => $item['product_id'],
                    'product_variation_id'  => $item['product_variation_id'] ?? null,
                    'quantity'              => $item['quantity'],
                    'unit_price'            => $item['unit_price'],
                    'amount'                => $amount,
                ]);

                WarehouseStockMovement::create([
                    'product_id'      => $item['product_id'],
                    'movement_type'   => 'Purchase',
                    'quantity'        => $item['quantity'],
                    'amount'          => $amount,
                    'reference_type'  => 'Purchase',
                    'reference_id'    => $purchase->id,
                    'movement_date'   => $purchase->purchase_date,
                ]);
            }

            $this->postVoucher($purchase, $userId);

            return $purchase->load('items.product', 'vendor');
        });
    }

    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            WarehouseStockMovement::where('reference_type', 'Purchase')
                ->where('reference_id', $purchase->id)
                ->delete();

            $this->voucherService->deleteByReference('Purchase', $purchase->id);

            $purchase->items()->delete();
            $purchase->delete();
        });
    }

    // Dr Stock in Hand / Cr Accounts Payable (tagged to the vendor)
    private function postVoucher(Purchase $purchase, ?int $userId): void
    {
        $stockAccountId = $this->mappingService->accountId('stock_in_hand');
        $apAccountId    = $this->mappingService->accountId('accounts_payable');

        $lines = [
            [
                'account_id' => $stockAccountId,
                'debit'      => $purchase->subtotal,
                'credit'     => 0,
            ],
        ];

        if ($purchase->tax_amount > 0) {
            $taxAccountId = $this->mappingService->accountId('purchase_tax');
            $lines[] = [
                'account_id' => $taxAccountId,
                'debit'      => $purchase->tax_amount,
                'credit'     => 0,
            ];
        }

        $lines[] = [
            'account_id' => $apAccountId,
            'debit'      => 0,
            'credit'     => $purchase->total_amount,
            'party_type' => 'vendor',
            'party_id'   => $purchase->vendor_id,
        ];

        $this->voucherService->post(
            'system',
            $purchase->purchase_date->format('Y-m-d'),
            $lines,
            "Purchase {$purchase->purchase_no}",
            'Purchase',
            $purchase->id,
            $userId
        );
    }

    private function generatePurchaseNo(): string
    {
        $last = Purchase::orderByDesc('id')->value('purchase_no');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return 'PUR-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}