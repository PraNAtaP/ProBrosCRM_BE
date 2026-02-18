<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deal_id' => $this->deal_id,
            'contact_id' => $this->contact_id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'deal' => $this->whenLoaded('deal', fn() => [
                'id' => $this->deal->id,
                'title' => $this->deal->title,
            ]),
            'contact' => $this->whenLoaded('contact', fn() => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'email' => $this->contact->email,
                'phone' => $this->contact->phone,
            ]),
            'company' => $this->whenLoaded('company', fn() => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'address' => $this->company->address,
            ]),
            'activity_type' => $this->activity_type,
            'meeting_type' => $this->meeting_type,
            'start_time' => $this->start_time?->toIso8601String(),
            'duration' => $this->duration,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
