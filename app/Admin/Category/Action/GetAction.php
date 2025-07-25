<?php

namespace App\Admin\Category\Action;

use App\Models\Category;

class GetAction
{
    public function handle()
    {
        return Category::all();
    }
}
