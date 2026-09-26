<?php

namespace Modules\Library\App\Repositories;

use Modules\Library\App\Models\Category;

class CategoryRepository
{
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::where('is_active', true)
            ->withCount('books')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $category = Category::findOrFail($id);
        return $category->update($data);
    }

    public function delete(int $id): bool
    {
        $category = Category::findOrFail($id);
        return $category->delete();
    }
}
