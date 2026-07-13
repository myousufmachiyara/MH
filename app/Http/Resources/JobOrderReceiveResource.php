<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobOrderReceiveResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'receive_no'         => $this->receive_no,
            'job_order_id'       => $this->job_order_id,
            'job_no'             => $this->jobOrder?->job_no,
            'vendor_name'        => $this->jobOrder?->vendor?->name,
            'receive_date'       => optional($this->receive_date)->toDateString(),
            'processing_charge'  => (float) $this->processing_charge,
            'remarks'            => $this->remarks,
            'items'              => $this->whenLoaded('items', fn () =>
                $this->items->map(fn($i) => [
                    'raw_product_id'      => $i->raw_product_id,
                    'raw_product_name'    => $i->rawProduct?->name,
                    'quantity_consumed'   => (float) $i->quantity_consumed,
                    'quantity_leftover'   => (float) $i->quantity_leftover,
                    'output_product_id'   => $i->output_product_id,
                    'output_product_name' => $i->outputProduct?->name,
                    'quantity_output'     => (float) $i->quantity_output,
                ])
            ),
        ];
    }
}