<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'partner_id' => $this->partner_id,
            'type' => $this->type,
            'file_path' => $this->file_path,
            'status' => $this->status,

            'company' => new CompanyResource($this->whenLoaded('company')),
            'partner' => new PartnerResource($this->whenLoaded('partner')),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'extractions' => DocumentExtractionResource::collection($this->whenLoaded('extractions')),
            'latest_extraction' => new DocumentExtractionResource($this->latest_extraction),
            'ai_classifications' => AiClassificationResource::collection($this->whenLoaded('aiClassifications')),
            'journal_entries' => JournalEntryResource::collection($this->whenLoaded('journalEntries')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
