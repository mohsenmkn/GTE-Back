<?php

namespace Modules\VirtualSecretariat\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowStatisticsService
{
    protected $sqlConnection;

    public function __construct()
    {
        $this->sqlConnection = DB::connection('sqlsrv_automation');
    }


    /**
     * آمار بر اساس نوع فرآیند
     */
    /**
     * آمار بر اساس نوع فرآیند (فقط کاربران فعال)
     */
    public function getStatisticsByType(int $userId = null, int $departmentId = null, bool $viewAll = false): array
    {
        $query = $this->sqlConnection->table('WFExecute as e')
            ->join('WFStructure as s', 'e.WFID', '=', 's.WFID')
            ->join('Actors as a', 'e.UserID', '=', 'a.ActorID')
            ->leftJoin('Users as u', 'a.UserID', '=', 'u.User_ID')
            ->where('u.IsActive', 1) // ✅ فقط کاربران فعال
            ->where('s.IsActive', 1);

        if (!$viewAll && $departmentId) {
            $query->where(function($q) use ($departmentId) {
                $q->where('e.UserID', function($sub) use ($departmentId) {
                    $sub->select('a2.ActorID')
                        ->from('Actors as a2')
                        ->join('Roles as r2', 'a2.RoleID', '=', 'r2.Role_ID')
                        ->where('r2.DepartmentID', $departmentId);
                });
            });
        } elseif (!$viewAll && $userId) {
            $query->where('e.UserID', $userId);
        }

        return $query->selectRaw('
        s.WFID,
        s.WFName,
        COUNT(e.ExecuteID) as count,
        SUM(CASE WHEN e.Status = \'FINISH\' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN e.Status IS NULL OR e.Status != \'FINISH\' THEN 1 ELSE 0 END) as in_progress
    ')
            ->groupBy('s.WFID', 's.WFName')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * ✅ آمار بر اساس کاربر - با Join صحیح از طریق Actors
     */
    /**
     * آمار بر اساس کاربر (فقط کاربران فعال)
     */
    public function getStatisticsByUser(int $userId = null, int $departmentId = null, bool $viewAll = false, int $limit = 10): array
    {
        $query = $this->sqlConnection->table('WFExecute as e')
            ->join('Actors as a', 'e.UserID', '=', 'a.ActorID')
            ->join('Roles as r', 'a.RoleID', '=', 'r.Role_ID')
            ->leftJoin('Users as u', 'a.UserID', '=', 'u.User_ID')
            ->where('u.IsActive', 1); // ✅ فقط کاربران فعال

        if (!$viewAll && $departmentId) {
            $query->where('r.DepartmentID', $departmentId);
        } elseif (!$viewAll && $userId) {
            $query->where('e.UserID', $userId);
        }

        return $query->selectRaw('
        a.UserID as RealUserID,
        a.ActorID,
        r.RoleName,
        r.DepartmentID,
        ISNULL(u.FirstName, u.UserName) as FirstName,
        ISNULL(u.LastName, N\'\') as LastName,
        ISNULL(u.UserName, N\'کاربر_\' + CAST(a.UserID as nvarchar)) as UserName,
        COUNT(e.ExecuteID) as count,
        SUM(CASE WHEN e.Status = \'FINISH\' THEN 1 ELSE 0 END) as completed
    ')
            ->groupBy('a.UserID', 'a.ActorID', 'r.RoleName', 'r.DepartmentID', 'u.FirstName', 'u.LastName', 'u.UserName')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'User_ID' => $item->RealUserID,
                    'ActorID' => $item->ActorID,
                    'RoleName' => $item->RoleName ?? 'نقش نامشخص',
                    'DepartmentID' => $item->DepartmentID,
                    'FirstName' => $item->FirstName ?? 'نامشخص',
                    'LastName' => $item->LastName ?? '',
                    'UserName' => $item->UserName ?? 'کاربر_' . $item->RealUserID,
                    'count' => (int) $item->count,
                    'completed' => (int) $item->completed,
                ];
            })
            ->toArray();
    }

    /**
     * ✅ آمار بر اساس واحد سازمانی
     */
    /**
     * آمار بر اساس واحد سازمانی (فقط کاربران فعال)
     */
    public function getStatisticsByDepartment(int $userId = null, int $departmentId = null, bool $viewAll = false): array
    {
        $query = $this->sqlConnection->table('WFExecute as e')
            ->join('Actors as a', 'e.UserID', '=', 'a.ActorID')
            ->join('Roles as r', 'a.RoleID', '=', 'r.Role_ID')
            ->leftJoin('Users as u', 'a.UserID', '=', 'u.User_ID')
            ->where('u.IsActive', 1); // ✅ فقط کاربران فعال

        if (!$viewAll && $departmentId) {
            $query->where('r.DepartmentID', $departmentId);
        } elseif (!$viewAll && $userId) {
            $query->where('e.UserID', $userId);
        }

        return $query->selectRaw('
        r.DepartmentID,
        r.RoleName as department_name,
        COUNT(e.ExecuteID) as count,
        SUM(CASE WHEN e.Status = \'FINISH\' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN e.Status IS NULL OR e.Status != \'FINISH\' THEN 1 ELSE 0 END) as in_progress
    ')
            ->whereNotNull('r.DepartmentID')
            ->groupBy('r.DepartmentID', 'r.RoleName')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'DepartmentID' => $item->DepartmentID,
                    'department_name' => $item->department_name ?? 'واحد نامشخص',
                    'count' => (int) $item->count,
                    'completed' => (int) $item->completed,
                    'in_progress' => (int) $item->in_progress,
                ];
            })
            ->toArray();
    }

    /**
     * روند زمانی فرآیندها (30 روز گذشته)
     */
    /**
     * روند زمانی فرآیندها (فقط کاربران فعال)
     */
    public function getTrends(int $userId = null, int $departmentId = null, bool $viewAll = false): array
    {
        $query = $this->sqlConnection->table('WFExecute as e')
            ->join('Actors as a', 'e.UserID', '=', 'a.ActorID')
            ->leftJoin('Users as u', 'a.UserID', '=', 'u.User_ID')
            ->where('u.IsActive', 1); // ✅ فقط کاربران فعال

        if (!$viewAll && $departmentId) {
            $query->where('e.UserID', function($q) use ($departmentId) {
                $q->select('a2.ActorID')
                    ->from('Actors as a2')
                    ->join('Roles as r2', 'a2.RoleID', '=', 'r2.Role_ID')
                    ->where('r2.DepartmentID', $departmentId);
            });
        } elseif (!$viewAll && $userId) {
            $query->where('e.UserID', $userId);
        }

        return $query->selectRaw('
        CAST(e.ExecutionDate AS DATE) as date,
        COUNT(*) as count,
        SUM(CASE WHEN e.Status = \'FINISH\' THEN 1 ELSE 0 END) as completed
    ')
            ->where('e.ExecutionDate', '>=', DB::raw('DATEADD(DAY, -30, GETDATE())'))
            ->groupBy(DB::raw('CAST(e.ExecutionDate AS DATE)'))
            ->orderBy('date', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * ✅ فرآیندهای معوق - با Join صحیح از طریق Actors
     */
    /**
     * فرآیندهای معوق (فقط کاربران فعال)
     */
    public function getDelayedProcesses(int $userId = null, int $departmentId = null, bool $viewAll = false, int $limit = 20): array
    {
        $query = $this->sqlConnection->table('WFExecute as e')
            ->join('WFStructure as s', 'e.WFID', '=', 's.WFID')
            ->join('Actors as a', 'e.UserID', '=', 'a.ActorID')
            ->join('Roles as r', 'a.RoleID', '=', 'r.Role_ID')
            ->leftJoin('Users as u', 'a.UserID', '=', 'u.User_ID')
            ->where('u.IsActive', 1) // ✅ فقط کاربران فعال
            ->whereRaw('(e.Status IS NULL OR e.Status != \'FINISH\')')
            ->whereRaw('DATEDIFF(DAY, e.ExecutionDate, GETDATE()) > 7');

        if (!$viewAll && $departmentId) {
            $query->where('r.DepartmentID', $departmentId);
        } elseif (!$viewAll && $userId) {
            $query->where('e.UserID', $userId);
        }

        return $query->selectRaw('
        e.ExecuteID,
        s.WFName,
        r.RoleName,
        ISNULL(u.FirstName, u.UserName) as FirstName,
        ISNULL(u.LastName, N\'\') as LastName,
        e.ExecutionDate,
        DATEDIFF(DAY, e.ExecutionDate, GETDATE()) as days_elapsed
    ')
            ->orderBy('days_elapsed', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'ExecuteID' => $item->ExecuteID,
                    'WFName' => $item->WFName ?? 'فرآیند نامشخص',
                    'RoleName' => $item->RoleName ?? 'نقش نامشخص',
                    'FirstName' => $item->FirstName ?? 'نامشخص',
                    'LastName' => $item->LastName ?? '',
                    'ExecutionDate' => $item->ExecutionDate,
                    'days_elapsed' => (int) $item->days_elapsed,
                ];
            })
            ->toArray();
    }

    /**
     * میانگین زمان تکمیل هر نوع فرآیند
     */
    public function getAverageCompletionTime(int $userId = null, int $departmentId = null, bool $viewAll = false): array
    {
        $query = $this->sqlConnection->table('WFExecute as e')
            ->join('WFStructure as s', 'e.WFID', '=', 's.WFID')
            ->where('e.Status', 'FINISH');

        if (!$viewAll && $departmentId) {
            $query->where('e.UserID', function($q) use ($departmentId) {
                $q->select('a.ActorID')
                    ->from('Actors as a')
                    ->join('Roles as r', 'a.RoleID', '=', 'r.Role_ID')
                    ->where('r.DepartmentID', $departmentId);
            });
        } elseif (!$viewAll && $userId) {
            $query->where('e.UserID', $userId);
        }

        return $query->selectRaw('
            s.WFName,
            AVG(DATEDIFF(DAY, e.ExecutionDate, GETDATE())) as avg_days
        ')
            ->groupBy('s.WFName')
            ->orderBy('avg_days', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * آمار کلی فرآیندها (فقط کاربران فعال)
     */
    public function getOverallStatistics(int $userId = null, int $departmentId = null, bool $viewAll = false): array
    {
        $query = $this->sqlConnection->table('WFExecute as e')
            ->join('WFStructure as s', 'e.WFID', '=', 's.WFID')
            ->join('Actors as a', 'e.UserID', '=', 'a.ActorID')
            ->leftJoin('Users as u', 'a.UserID', '=', 'u.User_ID')
            ->where('u.IsActive', 1); // ✅ فقط کاربران فعال

        if (!$viewAll && $departmentId) {
            $query->where(function($q) use ($departmentId) {
                $q->where('e.UserID', function($sub) use ($departmentId) {
                    $sub->select('a2.ActorID')
                        ->from('Actors as a2')
                        ->join('Roles as r2', 'a2.RoleID', '=', 'r2.Role_ID')
                        ->where('r2.DepartmentID', $departmentId);
                });
            });
        } elseif (!$viewAll && $userId) {
            $query->where('e.UserID', $userId);
        }

        $stats = $query->selectRaw('
        COUNT(*) as total,
        SUM(CASE WHEN e.Status = \'FINISH\' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN e.Status IS NULL OR e.Status != \'FINISH\' THEN 1 ELSE 0 END) as in_progress,
        AVG(CASE WHEN e.Status = \'FINISH\' THEN DATEDIFF(DAY, e.ExecutionDate, GETDATE()) ELSE NULL END) as avg_days
    ')->first();

        $todayCount = (clone $query)->whereDate('e.ExecutionDate', DB::raw('CAST(GETDATE() AS DATE)'))->count();
        $weekCount = (clone $query)->where('e.ExecutionDate', '>=', DB::raw('DATEADD(DAY, -7, GETDATE())'))->count();
        $delayedCount = (clone $query)
            ->whereRaw('(e.Status IS NULL OR e.Status != \'FINISH\')')
            ->whereRaw('DATEDIFF(DAY, e.ExecutionDate, GETDATE()) > 7')
            ->count();

        return [
            'total' => $stats->total ?? 0,
            'completed' => $stats->completed ?? 0,
            'in_progress' => $stats->in_progress ?? 0,
            'avg_days' => round($stats->avg_days ?? 0, 1),
            'today' => $todayCount,
            'this_week' => $weekCount,
            'delayed' => $delayedCount,
        ];
    }
}
