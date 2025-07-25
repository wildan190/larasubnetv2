<?php

namespace App\Admin\Category\Controllers;

use App\Admin\Category\Action\CreateAction;
use App\Admin\Category\Action\DeleteAction;
use App\Admin\Category\Action\GetAction;
use App\Admin\Category\Action\UpdateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(GetAction $getAction)
    {
        $categories = $getAction->handle();

        return response()->json($categories);
    }

    public function store(Request $request, CreateAction $createAction)
    {
        try {
            $category = $createAction->handle($request->all());

            return response()->json($category, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id, UpdateAction $updateAction)
    {
        try {
            $category = $updateAction->handle($id, $request->all());

            return response()->json($category);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Category not found.'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy($id, DeleteAction $deleteAction)
    {
        try {
            return $deleteAction->handle($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Category not found.'], 404);
        }
    }
}
