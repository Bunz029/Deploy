<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogExportController extends Controller
{
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        $logs = ActivityLog::orderByDesc('created_at')->limit(1000)->get();

        if ($format === 'json') {
            return response()->json($logs);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activity_logs.csv"',
        ];

        $callback = function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id','user_name','action','target_type','target_id','target_name','ip_address','user_agent','created_at']);
            foreach ($logs as $l) {
                fputcsv($out, [
                    $l->id,
                    $l->user_name,
                    $l->action,
                    $l->target_type,
                    $l->target_id,
                    $l->target_name,
                    $l->ip_address,
                    substr($l->user_agent ?? '', 0, 120),
                    $l->created_at,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}


