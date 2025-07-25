<?php

namespace App\Admin\Category\Action;

use App\Models\Category;
use Illuminate\Support\Facades\Validator;

class CreateAction
{
    public function handle(array $data)
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        return Category::create($data);
    }
}
