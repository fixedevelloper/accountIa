<?php


namespace App\Services;


use App\Models\Account;
use App\Models\Document;
use App\Models\Journal;
use App\Models\JournalEntry;

class AccountingService
{
    public function createEntryFromDocument(Document $document)
    {
        $extraction = $document->extractions()->latest()->first();

        if (!$extraction) {
            logger("❌ Pas d'extraction");
            return;
        }

        $companyId = $document->company_id;

        // 🔒 éviter doublon
        if (JournalEntry::where('document_id', $document->id)->exists()) {
            logger("⚠️ Écriture déjà existante pour document: " . $document->id);
            return;
        }

        // 🔹 Type document
        $journalType = $this->resolveJournalType($extraction->type_document);
        $journal = $this->getOrCreateJournal($companyId, $journalType);

        // 🔹 Montants sécurisés
        $total = (float) ($extraction->total_amount ?? 0);
        $vat   = (float) ($extraction->vat_amount ?? 0);
        $ht    = (float) ($extraction->amount_ht ?? ($total - $vat));

        // fallback sécurité
        if ($total > 0 && $ht <= 0) {
            $vat = round($total * 0.1925, 2);
            $ht  = $total - $vat;
        }

        // 🔹 Comptes principaux dynamiques
        $accounts = $this->resolveAccounts($companyId, $journalType, $extraction);

        // 🔹 Compte auxiliaire
        $auxAccount = $this->resolveAuxAccount($companyId, $journalType, $extraction);

        // 🔹 Entry
        $entry = JournalEntry::create([
            'company_id' => $companyId,
            'journal_id' => $journal->id,
            'document_id' => $document->id,
            'entry_date' => $extraction->invoice_date ?? now(),
            'reference' => $extraction->invoice_number ?? 'AUTO-' . $document->id,
            'status' => 'draft'
        ]);

        // 🔥 LIGNES
        if (in_array($journalType, ['purchase', 'expense'])) {

            // Charge
            $this->addLine($entry, $accounts['charge'], $ht, 0, 'Charge');

            // TVA déductible
            if ($vat > 0) {
                $this->addLine($entry, $accounts['vat_deductible'], $vat, 0, 'TVA déductible');
            }

            // Fournisseur
            $this->addLine(
                $entry,
                $auxAccount,
                0,
                $total,
                $extraction->supplier_name ?? 'Fournisseur'
            );

        } else {

            // Client
            $this->addLine(
                $entry,
                $auxAccount,
                $total,
                0,
                $extraction->client_name ?? 'Client'
            );

            // Vente
            $this->addLine($entry, $accounts['sale'], 0, $ht, 'Vente');

            // TVA collectée
            if ($vat > 0) {
                $this->addLine($entry, $accounts['vat_collected'], 0, $vat, 'TVA collectée');
            }
        }


        logger("✅ Écriture comptable générée: " . $entry->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers métier
    |--------------------------------------------------------------------------
    */

    private function resolveJournalType($type)
    {
        $type = strtolower($type ?? '');

        return match ($type) {
        'sale', 'vente' => 'sale',
            'expense', 'frais' => 'expense',
            default => 'purchase'
        };
    }

    private function getOrCreateJournal($companyId, $type)
    {
        $code = match ($type) {
        'sale' => 'VEN',
            'expense' => 'OD',
            default => 'ACH'
        };

        return Journal::firstOrCreate(
            ['company_id' => $companyId, 'code' => $code],
            [
                'name' => match ($type) {
                'sale' => 'Journal des ventes',
                    'expense' => 'Opérations diverses',
                    default => 'Journal des achats'
                },
                'type' => $type
            ]
        );
    }

    private function resolveAccounts($companyId, $journalType, $extraction)
    {
        // 🔥 mapping intelligent par catégorie
        $category = strtolower($extraction->category ?? '');

        $chargeCode = match ($category) {
        'telecom' => '626000',
            'transport' => '625100',
            'restaurant' => '625600',
            default => '601000'
        };

        return [
            'charge' => $this->getOrCreateAccount($companyId, $chargeCode, 'Charges'),
            'sale' => $this->getOrCreateAccount($companyId, '706000', 'Ventes'),
            'vat_deductible' => $this->getOrCreateAccount($companyId, '445660', 'TVA déductible'),
            'vat_collected' => $this->getOrCreateAccount($companyId, '445710', 'TVA collectée'),
        ];
    }

    private function resolveAuxAccount($companyId, $journalType, $extraction)
    {
        return $journalType === 'sale'
            ? $this->generateAuxAccount($companyId, '411', $extraction->client_name ?? 'Client')
            : $this->generateAuxAccount($companyId, '401', $extraction->supplier_name ?? 'Fournisseur');
    }

    private function addLine($entry, $accountId, $debit, $credit, $label)
    {
        $entry->lines()->create([
            'account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $label
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    */

    private function generateAuxAccount($companyId, $prefix, $name)
    {
        $existing = Account::where('company_id', $companyId)
            ->where('name', $name)
            ->where('code', 'like', $prefix . '%')
            ->first();

        if ($existing) return $existing->id;

        $last = Account::where('company_id', $companyId)
            ->where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        $next = $last ? intval(substr($last->code, strlen($prefix))) + 1 : 1;

        $code = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);

        return Account::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'type' => 'auxiliary'
        ])->id;
    }

    private function getOrCreateAccount($companyId, $code, $name)
    {
        return Account::firstOrCreate(
            [
                'company_id' => $companyId,
                'code' => $code
            ],
            [
                'name' => $name
            ]
        )->id;
    }
}
