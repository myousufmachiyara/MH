<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Product;
use App\Models\WarehouseStockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    public function create(array $data, array $items, ?int $userId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
            }
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $total     = $subtotal + $taxAmount;

            $return = PurchaseReturn::create(array_merge($data, [
                'return_no'     => $this->generateReturnNo(),
                'subtotal'      => $subtotal,
                'tax_amount'    => $taxAmount,
                'total_amount'  => $total,
                'created_by'    => $userId,
                'updated_by'    => $userId,
            ]));

            foreach ($items as $item) {
                $amount = (float) $item['quantity'] * (float) $item['unit_price'];

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'purchase_item_id'   => $item['purchase_item_id'],
                    'product_id'         => $item['product_id'],
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'amount'             => $amount,
                ]);

                WarehouseStockMovement::create([
                    'product_id'      => $item['product_id'],
                    'movement_type'   => 'PurchaseReturn',
                    'quantity'        => -$item['quantity'],
                    'amount'          => -$amount,
                    'reference_type'  => 'PurchaseReturn',
                    'reference_id'    => $return->id,
                    'movement_date'   => $return->return_date,
                ]);
            }

            $this->postVoucher($return, $items, $userId);

            return $return->load('items.product', 'vendor', 'purchase');
        });
    }

    public function update(PurchaseReturn $return, array $data, array $items, ?int $userId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($return, $data, $items, $userId) {

            WarehouseStockMovement::where('reference_type', 'PurchaseReturn')
                ->where('reference_id', $return->id)
                ->delete();

            $this->voucherService->deleteByReference('PurchaseReturn', $return->id);

            $return->items()->delete();

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
            }
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $total     = $subtotal + $taxAmount;

            $return->update(array_merge($data, [
                'subtotal'      => $subtotal,
                'tax_amount'    => $taxAmount,
                'total_amount'  => $total,
                'updated_by'    => $userId,
            ]));

            foreach ($items as $item) {
                $amount = (float) $item['quantity'] * (float) $item['unit_price'];

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'purchase_item_id'   => $item['purchase_item_id'],
                    'product_id'         => $item['product_id'],
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'amount'             => $amount,
                ]);

                WarehouseStockMovement::create([
                    'product_id'      => $item['product_id'],
                    'movement_type'   => 'PurchaseReturn',
                    'quantity'        => -$item['quantity'],
                    'amount'          => -$amount,
                    'reference_type'  => 'PurchaseReturn',
                    'reference_id'    => $return->id,
                    'movement_date'   => $return->return_date,
                ]);
            }

            $this->postVoucher($return, $items, $userId);

            return $return->load('items.product', 'vendor', 'purchase');
        });
    }

    public function delete(PurchaseReturn $return): void
    {
        DB::transaction(function () use ($return) {
            WarehouseStockMovement::where('reference_type', 'PurchaseReturn')
                ->where('reference_id', $return->id)
                ->delete();

            $this->voucherService->deleteByReference('PurchaseReturn', $return->id);

            $return->items()->delete();
            $return->delete();
        });
    }

    // Reverse of a purchase: Dr Accounts Payable / Cr Stock in Hand (per category)
    private function postVoucher(PurchaseReturn $return, array $items, ?int $userId): void
    {
        $productIds = collect($items)->pluck('product_id')->unique();
        $products = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

        $stockTotalsByAccount = [];

        foreach ($items as $item) {
            $amount = (float) $item['quantity'] * (float) $item['unit_price'];
            $product = $products->get($item['product_id']);
            $category = $product?->category;

            $stockAccountId = $category?->stock_account_id
                ?? $this->mappingService->accountId('stock_in_hand');

            $stockTotalsByAccount[$stockAccountId] =
                ($stockTotalsByAccount[$stockAccountId] ?? 0) + $amount;
        }

        $apAccountId = $this->mappingService->accountId('accounts_payable');

        $lines = [
            [
                'account_id' => $apAccountId,
                'debit'      => $return->total_amount,
                'credit'     => 0,
                'party_type' => 'vendor',
                'party_id'   => $return->vendor_id,
            ],
        ];

        foreach ($stockTotalsByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'debit'      => 0,
                'credit'     => $amount,
            ];
        }

        if ($return->tax_amount > 0) {
            $taxAccountId = $this->mappingService->accountId('purchase_tax');
            $lines[] = [
                'account_id' => $taxAccountId,
                'debit'      => 0,
                'credit'     => $return->tax_amount,
            ];
        }

        $this->voucherService->post(
            'system',
            $return->return_date->format('Y-m-d'),
            $lines,
            "Purchase Return {$return->return_no}",
            'PurchaseReturn',
            $return->id,
            $userId
        );
    }

    private function generateReturnNo(): string
    {
        $last = PurchaseReturn::orderByDesc('id')->value('return_no');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return 'PR-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}