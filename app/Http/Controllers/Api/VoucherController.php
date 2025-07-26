<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = Voucher::where('status', 'available');

        // Filter search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan category_id
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Ambil semua voucher yang sesuai (tanpa paginate, karena kita akan group manual)
        $vouchers = $query->get();

        // Group by (name, description, size, price)
        $grouped = $vouchers->groupBy(function ($item) {
            return md5(json_encode([
                $item->name,
                $item->description,
                $item->size,
                $item->price,
                $item->category_id,
            ]));
        });

        // Transform menjadi entitas yang ditumpuk
        $transformed = $grouped->map(function ($group) {
            $first = $group->first();

            return [
                'name' => $first->name,
                'description' => $first->description,
                'size' => $first->size,
                'price' => $first->price,
                'category_id' => $first->category_id,
                'category' => $first->category,
                'stock' => $group->count(),
                'voucher_ids' => $group->pluck('id'),
            ];
        })->values(); // reset keys

        return response()->json([
            'data' => $transformed,
        ]);
    }
}
