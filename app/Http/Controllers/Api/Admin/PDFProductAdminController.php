<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\PdfProductResource;
use App\Repositories\PDFProductRepository;
use App\Services\PDFProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PDFProductAdminController extends BaseController
{
    public function __construct(
        protected PDFProductService $pdfProductService,
        protected PDFProductRepository $pdfProductRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->pdfProductRepository->getAdminList(
            $request->only(['is_active', 'search', 'category', 'per_page'])
        );

        return $this->successResponse([
            'data' => PdfProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'job_post_id' => ['nullable', 'exists:job_posts,id'],
            'job_classification_id' => ['nullable', 'exists:job_classifications,id'],
            'is_active' => ['sometimes', 'boolean'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'thumbnail' => ['nullable', 'image', 'max:2048'],
        ]);

        $product = $this->pdfProductService->storeUploaded(
            $data,
            $request->file('file'),
            $request->file('thumbnail')
        );

        return $this->successResponse(new PdfProductResource($product), 'محصول PDF ایجاد شد.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->pdfProductRepository->findById($id);

        if (! $product) {
            return $this->errorResponse('محصول یافت نشد.', 404);
        }

        return $this->successResponse(new PdfProductResource($product));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = $this->pdfProductRepository->findById($id);

        if (! $product) {
            return $this->errorResponse('محصول یافت نشد.', 404);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'job_post_id' => ['nullable', 'exists:job_posts,id'],
            'job_classification_id' => ['nullable', 'exists:job_classifications,id'],
            'is_active' => ['sometimes', 'boolean'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'thumbnail' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['file'], $data['thumbnail']);

        if ($request->hasFile('file')) {
            $uuid = (string) \Illuminate\Support\Str::uuid();
            $data['file_path'] = $request->file('file')->storeAs('pdfs', $uuid.'.pdf', 'local');
        }

        if ($request->hasFile('thumbnail')) {
            $uuid = (string) \Illuminate\Support\Str::uuid();
            $ext = $request->file('thumbnail')->getClientOriginalExtension() ?: 'jpg';
            $data['thumbnail'] = $request->file('thumbnail')->storeAs('pdf_thumbnails', $uuid.'.'.$ext, 'public');
        }

        $product->update($data);

        return $this->successResponse(new PdfProductResource($product->fresh()), 'محصول به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $product = $this->pdfProductRepository->findById($id);

        if (! $product) {
            return $this->errorResponse('محصول یافت نشد.', 404);
        }

        $product->delete();

        return $this->successResponse(null, 'محصول حذف شد.');
    }
}
