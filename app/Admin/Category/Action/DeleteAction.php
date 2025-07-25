<?php

namespace App\Admin\Category\Action;

use App\Models\Category;

class DeleteAction
{
    public function handle(int $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
