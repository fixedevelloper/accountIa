<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class DocumentExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',

        // 📄 Infos document
        'type_document',
        'supplier_name',
        'client_name',

        // 🔢 Facture
        'category',
        'invoice_number',
        'invoice_date',
        'due_date',
        'payment_status',

        // 💰 Montants
        'amount_ht',
        'vat_amount',
        'total_amount',

        // 🌍 Devise
        'currency',

        // 🤖 OCR
        'confidence',
        'status',

        // 🧾 Données brutes
        'raw_json',

        // 🧠 Validation
        'is_validated',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',

        'amount_ht'    => 'decimal:2',
        'vat_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',

        'confidence'   => 'float',
        'is_validated' => 'boolean',

        'raw_json'     => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (🔥 très utiles)
    |--------------------------------------------------------------------------
    */

    public function isComplete(): bool
    {
        return !empty($this->supplier_name)
            && !empty($this->invoice_number)
            && !empty($this->total_amount);
    }

    public function isValid(): bool
    {
        return $this->is_validated === true;
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_amount ?? 0, 2, ',', ' ') . ' ' . $this->currency;
    }

    public function getVatRateAttribute(): ?float
    {
        $amountHt = (float) $this->amount_ht;
        $vatAmount = (float) $this->vat_amount;

        if ($amountHt <= 0) {
            return null;
        }

        return round(($vatAmount / $amountHt) * 100, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
        'extracted' => 'Extrait',
            'corrected' => 'Corrigé',
            'validated' => 'Validé',
            default => 'Inconnu'
        };
    }
}
