<?php


namespace App\Http\Controllers\Documents;


use App\Http\Resources\DocumentExtractionResource;
use App\Http\Resources\DocumentResource;
use App\Jobs\AnalyzeDocumentJob;
use App\Models\Document;
use App\Models\DocumentExtraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;


class DocumentController
{
    public function index(Request $request)
    {
        $query = DocumentExtraction::query();

        // 🔎 Filtres utiles
        if ($request->filled('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->filled('supplier_name')) {
            $query->where('supplier_name', 'like', '%' . $request->supplier_name . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        $extractions = $query->latest()->paginate(10);

        return DocumentExtractionResource::collection($extractions);
    }
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
        ]);

        $user = Auth::user();

        $file = $request->file('file');

       // $path = $file->store('documents', 's3');
        $path = $file->store('documents', 'public');
        $document = Document::create([
            'company_id' => $user->companyUser->company_id,
            'file_path' => $path,
            'status' => 'uploaded',
        ]);

        // envoyer vers la queue pour analyse IA
        AnalyzeDocumentJob::dispatch($document);

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'status' => 'processing'
        ]);
    }
    public function show($id)
    {
        $document = Document::with([
            'company',
            'partner',
            'versions',
            'extractions',
            'aiClassifications',
            'journalEntries.lines.account',
        ])->findOrFail($id);

        return response()->json(new DocumentResource($document));
    }

}
