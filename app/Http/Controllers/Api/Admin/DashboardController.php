<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalRevenue = Transaction::where('status', 'success')->sum('total');

        $successTransactions = Transaction::where('status', 'success')->count();
        $pendingTransactions = Transaction::where('status', 'pending')->count();

        $totalUsers = User::count();

        $monthlyRevenue = Transaction::select(
            DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
            DB::raw('SUM(total) as total')
        )
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"))
            ->orderBy('month', 'asc')
            ->get();

        $chartData = [];
        $period = now()->subMonths(11)->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $monthKey = $period->format('Y-m');
            $monthlyTotal = $monthlyRevenue->firstWhere('month', $monthKey)->total ?? 0;
            $chartData[] = [
                'month' => $monthKey,
                'total' => (int) $monthlyTotal,
            ];
            $period->addMonth();
        }

        return response()->json([
            'total_revenue' => $totalRevenue,
            'success_transactions' => $successTransactions,
            'pending_transactions' => $pendingTransactions,
            'total_users' => $totalUsers,
            'revenue_chart' => $chartData,
        ]);
    }
}
