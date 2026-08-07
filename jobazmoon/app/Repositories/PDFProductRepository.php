<?php

namespace App\Repositories;

use App\Models\PdfProduct;
use App\Models\PdfPurchase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PDFProductRepository
{
    public function getActive(array $filters): LengthAwarePaginator
    {
        $query = PdfProduct::query()->where('is_active', true);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['price_min']) && $filters['price_min'] !== '') {
            $query->where('price', '>=', (int) $filters['price_min']);
        }

        if (isset($filters['price_max']) && $filters['price_max'] !== '') {
            $query->where('price', '<=', (int) $filters['price_max']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function findActive(int $id): ?PdfProduct
    {
        return PdfProduct::query()->where('is_active', true)->find($id);
    }

    public function findById(int $id): ?PdfProduct
    {
        return PdfProduct::query()->find($id);
    }

    public function getPurchasedByUser(User $user): Collection
    {
        return PdfProduct::query()
            ->whereHas('purchases', fn ($q) => $q->where('user_id', $user->id))
            ->with(['purchases' => fn ($q) => $q->where('user_id', $user->id)])
            ->latest()
            ->get();
    }

    public function getPurchase(User $user, PdfProduct $pdf): ?PdfPurchase
    {
        return PdfPurchase::query()
            ->where('user_id', $user->id)
            ->where('pdf_product_id', $pdf->id)
            ->first();
    }

    public function getAdminList(array $filters): LengthAwarePaginator
    {
        $query = PdfProduct::query();

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }
}
