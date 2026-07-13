<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'job_no'      => $this->job_no,
            'vendor_id'   => $this->vendor_id,
            'vendor_name' => $this->vendor?->name,
            'sale_id'     => $this->sale_id,
            'job_type'    => $this->job_type,
            'status'      => $this->status,
            'issue_date'  => optional($this->issue_date)->toDateString(),
            'remarks'     => $this->remarks,
            'items'       => $this->whenLoaded('items', fn () =>
                $this->items->map(fn($i) => [
                    'product_id'    => $i->product_id,
                    'product_name'  => $i->product?->name,
                    'quantity'      => (float) $i->quantity,
                    'source_status' => $i->source_status,
                ])
            ),
            'comments_count' => $this->comments_count ?? $this->comments()->count(),
        ];
    }
}