<?php

namespace Modules\Document\App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Contract\App\Models\Contract;
use Modules\Document\App\Models\Document;
use Modules\Document\Transformers\DocumentResource;
use Modules\PettyCash\App\Models\PettyCashTransaction;
use Modules\Project\App\Models\Project;
use Modules\WBS\App\Models\WbsItem;

class DocumentController extends Controller
{
    // آپلود فایل به هر موجودیت (polymorphic)
    public function upload(Request $request)
    {
        $request->validate([
            'file'              => 'required|file|max:20480',
            'documentable_type' => 'required|string|in:project,wbs_item,contract,petty_cash_transaction',
            'documentable_id'   => 'required|integer',
            'title'             => 'required|string|max:255',
            'type'              => 'required|in:drawing,invoice,contract,report,photo,other',  // ✅ اضافه کنید
            'description'       => 'nullable|string',
            'company_id'        => 'required|exists:companies,id',
        ]);

        $file = $request->file('file');
        $morphMap = [
            'project' => Project::class,
            'wbs_item' => WbsItem::class,
            'contract' => Contract::class,
            'petty_cash_transaction' => PettyCashTransaction::class,
        ];

        $modelClass = $morphMap[$request->documentable_type];
        $model = $modelClass::findOrFail($request->documentable_id);

        $folder = "documents/{$request->company_id}/{$request->documentable_type}/{$request->documentable_id}";
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // ✅ استفاده از دیسک public
        $path = $file->storeAs($folder, $fileName, 'public');

        $document = Document::create([
            'company_id' => $request->company_id,
            'documentable_type' => $model::class,
            'documentable_id' => $model->id,
            'title' => $request->title,
            'type' => $request->type,  // ✅ ذخیره type
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'description' => $request->description,
            'uploaded_by' => $request->user()->id,
        ]);

        return new DocumentResource($document->load('uploader'));
    }

    public function index(Request $request)
    {
        $query = Document::query()
            ->with('uploader')
            ->when($request->company_id, fn($q) => $q->where('company_id', $request->company_id))
            ->when($request->documentable_type, fn($q) => $q->where('documentable_type', 'like', "%{$request->documentable_type}%"))
            ->when($request->documentable_id, fn($q) => $q->where('documentable_id', $request->documentable_id))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"));

        return DocumentResource::collection($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show(Document $document)
    {
        return new DocumentResource($document->load('uploader'));
    }

    public function download(Document $document)
    {
        // ✅ استفاده از دیسک public
        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'فایل یافت نشد.'], 404);
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->file_name
        );
    }

    public function destroy(Document $document)
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
        return response()->json(['message' => 'فایل حذف شد.'], 200);
    }
}
