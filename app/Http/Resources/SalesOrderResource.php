<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_color' => \App\Models\SalesOrder::STATUS_COLORS[$this->status] ?? '#64748b',
            'total_amount' => (float) $this->total_amount,
            'is_favorite' => (bool) $this->is_favorite,
            'is_second_run' => (bool) $this->is_second_run,
            'needs_price_sync' => (bool) $this->needs_price_sync,
            'additional_notes' => $this->additional_notes,
            'delivery_date' => $this->delivery_date?->toDateString(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn() => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'trading_name' => $this->company?->trading_name,
                'display_name' => $this->company?->display_name,
            ]),
            'contact' => $this->whenLoaded('contact', fn() => [
                'id' => $this->contact?->id,
                'name' => $this->contact?->name,
                'email' => $this->contact?->email,
            ]),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'user_id' => $this->user_id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
