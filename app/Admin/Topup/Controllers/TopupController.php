<?php

namespace App\Admin\Topup\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\Balance::with('user')->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('email')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', 'like', '%'.$request->email.'%');
            });
        }

        $perPage = $request->get('per_page', 10);
        $topups = $query->paginate($perPage);

        return response()->json([
            'data' => $topups->getCollection()->map(function ($topup) {
                return [
                    'order_id' => $topup->order_id,
                    'user' => [
                        'id' => $topup->user->id,
                        'name' => $topup->user->name,
                        'email' => $topup->user->email,
                    ],
                    'amount' => $topup->amount,
                    'status' => $topup->status,
                    'payment_type' => $topup->payment_type,
                    'va_number' => $topup->va_number,
                    'vendor' => $topup->vendor,
                    'created_at' => $topup->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $topups->currentPage(),
                'last_page' => $topups->lastPage(),
                'total' => $topups->total(),
                'per_page' => $topups->perPage(),
            ],
        ]);
    }
}
