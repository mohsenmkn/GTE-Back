<?php


namespace Modules\Finance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\App\Models\User;

class UserListController extends Controller
{
    /**
     * لیست ساده کاربران برای Dropdown
     */
    public function index(Request $request)
    {
        $query = User::query();

        // اگر search فرستاده شد
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('personnel_code', 'like', "%{$search}%");
            });
        }

        $users = $query->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}
