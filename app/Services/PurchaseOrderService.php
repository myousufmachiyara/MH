<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function create(array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['quantity'] * (float) ($item['estimated_price'] ?? 0);
            }

            $order = PurchaseOrder::create(array_merge($data, [
                'order_no'   => $this->generateOrderNo(),
                'status'     => $data['status'] ?? 'Pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));

            foreach ($items as $item) {
                $amount = (float) $item['quantity'] * (float) ($item['estimated_price'] ?? 0);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'estimated_price'   => $item['estimated_price'] ?? 0,
                    'amount'            => $amount,
                ]);
            }

            return $order->load('items.product', 'vendor');
        });
    }

    public function update(PurchaseOrder $order, array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data, $items, $userId) {
            $order->items()->delete();

            $order->update(array_merge($data, ['updated_by' => $userId]));

            foreach ($items as $item) {
                $amount = (float) $item['quantity'] * (float) ($item['estimated_price'] ?? 0);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'estimated_price'   => $item['estimated_price'] ?? 0,
                    'amount'            => $amount,
                ]);
            }

            return $order->load('items.product', 'vendor');
        });
    }

    public function delete(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            if ($order->purchases()->exists()) {
                throw new \Exception('Cannot delete — this purchase order has already been converted to an invoice.');
            }
            $order->items()->delete();
            $order->delete();
        });
    }

    private function generateOrderNo(): string
    {
        $last = PurchaseOrder::orderByDesc('id')->value('order_no');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return 'PO-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}