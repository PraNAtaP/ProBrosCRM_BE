<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Formatted to match React frontend expectations.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->whenLoaded('contact', fn() => 
                $this->contact->company?->name ?? null
            ),
            'company_id' => $this->whenLoaded('contact', fn() => 
                $this->contact->company_id ?? null
            ),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn() => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'email' => $this->contact->email,
            ]),
            'value' => (float) $this->value,
            'status' => $this->status,
            'color' => $this->status_color,
            'owner' => $this->whenLoaded('user', fn() => $this->user->name),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'description' => $this->description,
            'commission' => $this->whenLoaded('commission', fn() => 
                $this->commission ? [
                    'id' => $this->commission->id,
                    'amount' => (float) $this->commission->amount,
                    'status' => $this->commission->status,
                ] : null
            ),
            'activities' => ActivityLogResource::collection($this->whenLoaded('activityLogs')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
