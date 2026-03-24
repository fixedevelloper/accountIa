<?php


namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentExtraction;
use App\Http\Resources\DocumentExtractionResource;
use Illuminate\Http\Request;

class DocumentExtractionController extends Controller
{
    /**
     * Liste des extractions
     */
    public function index(Request $request)
    {
        $query = DocumentExtraction::query();

        // 🔎 Filtres utiles
        if ($request->document_id) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->supplier_name) {
            $query->where('supplier_name', 'like', '%' . $request->supplier_name . '%');
        }

        if ($request->date_from) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        $extractions = $query->latest()->paginate(10);

        return DocumentExtractionResource::collection($extractions);
    }

    /**
     * Détail d’une extraction
     */
    public function show($id)
    {
        $extraction = DocumentExtraction::with('document')->findOrFail($id);

        return new DocumentExtractionResource($extraction);
    }

    /**
     * Création manuelle (optionnel, souvent via OCR)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'supplier_name' => 'nullable|string',
            'invoice_number' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
            'raw_json' => 'nullable|array',
        ]);

        $extraction = DocumentExtraction::create($validated);

        return new DocumentExtractionResource($extraction);
    }

    /**
     * Mise à jour (validation humaine OCR)
     */
    public function update(Request $request, $id)
    {
        $extraction = DocumentExtraction::findOrFail($id);

        $validated = $request->validate([
            'supplier_name' => 'nullable|string',
            'invoice_number' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
        ]);

        $extraction->update($validated);

        return new DocumentExtractionResource($extraction);
    }

    /**
     * Suppression
     */
    public function destroy($id)
    {
        $extraction = DocumentExtraction::findOrFail($id);
        $extraction->delete();

        return response()->json([
            'message' => 'Extraction supprimée'
        ]);
    }
}
