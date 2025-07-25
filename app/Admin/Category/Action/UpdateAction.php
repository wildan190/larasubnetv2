<?php

namespace App\Admin\Category\Action;

use App\Models\Category;
use Illuminate\Support\Facades\Validator;

class UpdateAction
{
    public function handle(int $id, array $data)
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $category->update($data);

        return $category;
    }
}
