<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'area_id' => $this->area_id,
            'area' => $this->whenLoaded('area', fn() => [
                'id' => $this->area->id,
                'name' => $this->area->name,
            ]),
            'name' => $this->name,
            'address' => $this->address,
            'industry' => $this->industry,
            'phone' => $this->phone,
            'contacts_count' => $this->contacts_count ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
