<?php
namespace App\Http\Controllers;

use App\Models\attendance;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;

class attendanceController extends Controller
{
    public function presensi(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id_user' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 422);
        }

        $save = Attendance::create([
            'id_user' => $req->get('id_user'),
            'date' => $req->get('date'),
            'time' => now()->format('H:i:s'),
            'status' => $req->get('status'),
        ]);

        if ($save) {
            return response()->json([
                'status' => true,
                'message' => 'Presensi berhasil dicatat',
                'data' => $save
            ]);
        } else {
            return response()->json(['status' => false, 'message' => 'Presensi gagal dicatat'], 500);
        }
    }

    // Metode lainnya tetap sama...



    public function show1($id_user)
    {
        $attendance = Attendance::where('id_user', $id_user)->get();

        return response()->json(['status' => true, 'data' => $attendance]);
    }

    public function summary($id_user)
    {
        $userRecords = Attendance::where('id_user', $id_user)->get();
        $userGroupedByMonth = $userRecords->groupBy(function ($date) {
            return Carbon::parse($date->date)->format('m-Y');
        });

        $summary = [];

        foreach ($userGroupedByMonth as $monthYear => $records) {
            $hadir = $records->where('status', 'hadir')->count();
            $izin = $records->where('status', 'izin')->count();
            $sakit = $records->where('status', 'sakit')->count();

            $summary[] = [
                'month' => $monthYear,
                'attendance_summary' => [
                    'hadir' => $hadir,
                    'izin' => $izin,
                    'sakit' => $sakit,
                ],
            ];
        }

        return response()->json([ 
            'status' => 'success',
            'data' => [
                'id_user' => $id_user,
                'attendance_summary_by_month' => $summary
            ]
        ]);
    }

    public function analysis(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'group_by' => 'required|string',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $users = User::where('role', $validated['group_by'])->get();

        $groupedAnalysis = [];

        foreach ($users as $user) {
            $attendanceRecords = Attendance::where('id_user', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $hadir = $attendanceRecords->where('status', 'hadir')->count();
            $izin = $attendanceRecords->where('status', 'izin')->count();
            $sakit = $attendanceRecords->where('status', 'sakit')->count();
            $alpha = $attendanceRecords->where('status', 'alpha')->count();

            $totalAttendance = $hadir + $izin + $sakit + $alpha;
            $hadirPercentage = $totalAttendance > 0 ? ($hadir / $totalAttendance) * 100 : 0;
            $izinPercentage = $totalAttendance > 0 ? ($izin / $totalAttendance) * 100 : 0;
            $sakitPercentage = $totalAttendance > 0 ? ($sakit / $totalAttendance) * 100 : 0;
            $alphaPercentage = $totalAttendance > 0 ? ($alpha / $totalAttendance) * 100 : 0;

            $groupedAnalysis[] = [
                'group' => $user->role,
                'total_users' => $users->count(),
                'attendance_rate' => [
                    'hadir_percentage' => round($hadirPercentage, 2),
                    'izin_percentage' => round($izinPercentage, 2),
                    'sakit_percentage' => round($sakitPercentage, 2),
                    'alpha_percentage' => round($alphaPercentage, 2),
                ],
                'total_attendance' => [
                    'hadir' => $hadir,
                    'izin' => $izin,
                    'sakit' => $sakit,
                    'alpha' => $alpha,
                ],
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'analysis_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'grouped_analysis' => $groupedAnalysis,
            ]
        ], 200);
    }
}