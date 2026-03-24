<?php

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;

class DocumentExtractionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,

            // Infos document
            'type_document' => $this->type_document,
            'supplier_name' => $this->supplier_name,
            'client_name' => $this->client_name,

            // Facture
            'category' => $this->category,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'payment_status' => $this->payment_status,

            // Montants
            'amount_ht' => $this->amount_ht,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'formatted_total' => $this->formatted_total,

            // Devise
            'currency' => $this->currency,

            // OCR
            'confidence' => $this->confidence,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // Données brutes
            'raw_json' => $this->raw_json,

            // Validation
            'is_validated' => $this->is_validated,
            'is_complete' => $this->isComplete(),
            'is_valid' => $this->isValid(),
            'vat_rate' => $this->vat_rate,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

