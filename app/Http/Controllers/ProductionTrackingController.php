<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ProcurementItem;
use Illuminate\Http\Request;

class ProductionTrackingController extends Controller
{
    /**
     * Get production timeline/history for a specific procurement item
     * Shows all status changes with timestamps, attributes, and percentages
     */
    public function getProductionTimeline(Request $request, $procurementItemId)
    {
        if (!is_numeric($procurementItemId)) {
            return ApiResponse::error('Invalid procurement item ID', 400);
        }

        $item = ProcurementItem::with([
            'item.type',
            'plenaryMeetingItem.item.type',
            'plenaryMeetingItem.cooperative',
            'procurement.vendor',
            'processStatuses' => function($query) {
                $query->with(['productionAttribute', 'user', 'area'])
                      ->orderBy('created_at', 'asc');
            }
        ])->find($procurementItemId);

        if (!$item) {
            return ApiResponse::error('Procurement item not found', 404);
        }

        // Build timeline data
        $timeline = $item->processStatuses->map(function($status) {
            return [
                'id' => $status->id,
                'status' => $status->status,
                'production_attribute' => $status->productionAttribute ? [
                    'id' => $status->productionAttribute->id,
                    'name' => $status->productionAttribute->name,
                    'slug' => $status->productionAttribute->slug,
                ] : null,
                'percentage' => $status->percentage,
                'production_start_date' => $status->production_start_date,
                'production_end_date' => $status->production_end_date,
                'area' => $status->area ? [
                    'id' => $status->area->id,
                    'name' => $status->area->name,
                ] : null,
                'changed_by' => $status->user ? [
                    'id' => $status->user->id,
                    'name' => $status->user->name,
                    'role' => $status->user->role,
                ] : null,
                'notes' => $status->notes,
                'status_date' => $status->status_date,
                'created_at' => $status->created_at,
            ];
        });

        // Calculate progress summary
        $latestStatus = $item->processStatuses->last();
        $progressSummary = [
            'current_status' => $item->process_status,
            'current_percentage' => $latestStatus ? $latestStatus->percentage : 0,
            'current_attribute' => $latestStatus && $latestStatus->productionAttribute 
                ? $latestStatus->productionAttribute->name 
                : null,
            'total_updates' => $item->processStatuses->count(),
            'started_at' => $item->processStatuses->first()?->created_at,
            'last_updated_at' => $latestStatus?->created_at,
        ];

        return ApiResponse::success('Production timeline retrieved', [
            'item' => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'item_type_name' => $item->item_type_name,
                'process_type' => $item->process_type,
                'quantity' => $item->quantity,
                'cooperative' => $item->plenaryMeetingItem?->cooperative?->name,
                'vendor' => $item->procurement?->vendor?->name,
            ],
            'progress_summary' => $progressSummary,
            'timeline' => $timeline,
        ]);
    }

    /**
     * Get production progress statistics for admin dashboard
     * Groups items by status and calculates averages
     */
    public function getProductionStatistics(Request $request)
    {
        $query = ProcurementItem::with(['item', 'processStatuses'])
            ->whereHas('plenaryMeetingItem.item', function($q) {
                $q->where('process_type', 'production');
            });

        // Filter by vendor if specified
        if ($request->filled('vendor_id')) {
            $query->whereHas('procurement', function($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereHas('processStatuses', function($q) use ($request) {
                $q->where('created_at', '>=', $request->date_from);
            });
        }

        $items = $query->get();

        // Group by status
        $byStatus = $items->groupBy('process_status')->map(function($group) {
            return [
                'count' => $group->count(),
                'avg_percentage' => round($group->avg(function($item) {
                    return $item->current_percentage;
                }), 2),
            ];
        });

        // Calculate overall statistics
        $statistics = [
            'total_production_items' => $items->count(),
            'by_status' => $byStatus,
            'overall_avg_percentage' => round($items->avg(function($item) {
                return $item->current_percentage;
            }), 2),
            'pending_count' => $items->where('process_status', 'pending')->count(),
            'in_production_count' => $items->where('process_status', 'production')->count(),
            'completed_count' => $items->where('process_status', 'completed')->count(),
        ];

        return ApiResponse::success('Production statistics retrieved', $statistics);
    }

    /**
     * Get production items grouped by attribute/stage
     * Useful for monitoring which stage has the most items
     */
    public function getProductionByStage(Request $request)
    {
        $items = ProcurementItem::with([
            'item',
            'processStatuses' => function($query) {
                $query->with('productionAttribute')
                      ->orderBy('created_at', 'desc')
                      ->limit(1);
            }
        ])
        ->whereHas('plenaryMeetingItem.item', function($q) {
            $q->where('process_type', 'production');
        })
        ->where('process_status', 'production')
        ->get();

        // Group by current attribute
        $byStage = $items->groupBy(function($item) {
            $latestStatus = $item->processStatuses->first();
            return $latestStatus && $latestStatus->productionAttribute 
                ? $latestStatus->productionAttribute->name 
                : 'Unknown';
        })->map(function($group, $stageName) {
            return [
                'stage_name' => $stageName,
                'count' => $group->count(),
                'avg_percentage' => round($group->avg(function($item) {
                    return $item->current_percentage;
                }), 2),
                'items' => $group->map(function($item) {
                    return [
                        'id' => $item->id,
                        'item_name' => $item->item_name,
                        'percentage' => $item->current_percentage,
                    ];
                })->values(),
            ];
        })->values();

        return ApiResponse::success('Production by stage retrieved', $byStage);
    }
}
