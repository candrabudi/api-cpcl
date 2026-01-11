<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\PlenaryMeeting;
use App\Models\Procurement;
use App\Models\CpclDocument;
use App\Models\Cooperative;
use App\Models\Item;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for Admin Web with filtering
     */
    public function index(Request $request)
    {
        try {
            $year = $request->get('year');
            $month = $request->get('month');

            $stats = [
                [
                    'label' => 'Total Rapat Pleno',
                    'value' => $this->applyFilters(PlenaryMeeting::query(), 'meeting_date', $year, $month)->count(),
                ],
                [
                    'label' => 'Total Pengadaan',
                    'value' => $this->applyFilters(Procurement::query(), 'procurement_date', $year, $month)->count(),
                ],
                [
                    'label' => 'Total Dokumen CPCL',
                    'value' => $this->applyFilters(CpclDocument::query(), 'cpcl_date', $year, $month)->count(),
                ],
                [
                    'label' => 'Total Koperasi',
                    'value' => $this->applyFilters(Cooperative::query(), 'created_at', $year, $month)->count(),
                ],
                [
                    'label' => 'Total Barang',
                    'value' => $this->applyFilters(Item::query(), 'created_at', $year, $month)->count(),
                ],
            ];

            return ApiResponse::success('Dashboard statistics retrieved', $stats);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve dashboard statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper to apply year and month filters to a query
     */
    private function applyFilters($query, $dateColumn, $year, $month)
    {
        if ($year) {
            $query->whereYear($dateColumn, $year);
        }
        
        if ($month) {
            $query->whereMonth($dateColumn, $month);
        }

        return $query;
    }
}
