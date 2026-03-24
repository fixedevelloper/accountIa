<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AiClassificationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'predicted_account' => $this->predicted_account,
            'confidence' => $this->confidence,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

