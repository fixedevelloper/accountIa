<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryLineResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'entry_id' => $this->entry_id,
            'account_id' => $this->account_id,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'description' => $this->description,

            'account' => new AccountResource($this->whenLoaded('account')),
            'tax_lines' => TaxLineResource::collection($this->whenLoaded('taxLines')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
