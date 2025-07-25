<?php

namespace App\Admin\Voucher\Controllers;

use App\Admin\Voucher\Action\CreateAction;
use App\Admin\Voucher\Action\DeleteAction;
use App\Admin\Voucher\Action\GetAction;
use App\Admin\Voucher\Action\UpdateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(GetAction $getAction)
    {
        $vouchers = $getAction->handle();

        return response()->json($vouchers);
    }

    public function store(Request $request, CreateAction $createAction)
    {
        try {
            $voucher = $createAction->handle($request->all());

            return response()->json($voucher, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id, UpdateAction $updateAction)
    {
        try {
            $voucher = $updateAction->handle($id, $request->all());

            return response()->json($voucher);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Voucher not found.'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy($id, DeleteAction $deleteAction)
    {
        try {
            return $deleteAction->handle($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Voucher not found.'], 404);
        }
    }
}
