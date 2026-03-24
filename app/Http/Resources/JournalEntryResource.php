<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'document_id' => $this->document_id,
            'journal_id' => $this->journal_id,
            'reference' => $this->reference,
            'entry_date' => $this->entry_date,
            'status' => $this->status,

            'company' => new CompanyResource($this->whenLoaded('company')),
            'journal' => new JournalResource($this->whenLoaded('journal')),
            'document' => new DocumentResource($this->whenLoaded('document')),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
