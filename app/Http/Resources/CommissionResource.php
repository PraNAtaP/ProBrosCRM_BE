<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deal_id' => $this->deal_id,
            'deal' => $this->whenLoaded('deal', fn() => [
                'id' => $this->deal->id,
                'title' => $this->deal->title,
                'value' => (float) $this->deal->value,
                'company' => $this->deal->contact?->company?->name,
            ]),
            'user' => $this->whenLoaded('deal', fn() => 
                $this->deal->user ? [
                    'id' => $this->deal->user->id,
                    'name' => $this->deal->user->name,
                ] : null
            ),
            'amount' => (float) $this->amount,
            'calculation_date' => $this->calculation_date?->toDateString(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
