<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaSearchController extends Controller
{
    public function search(Request $request)
    {
        $rawQuery = trim($request->get('q'));

        if (!$rawQuery || strlen($rawQuery) < 2) {
            return ApiResponse::success('Area search', []);
        }

        $normalized = strtolower($rawQuery);
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized);
        $keywords = array_values(array_filter(explode(' ', $normalized)));

        $query = DB::table('areas');

        foreach ($keywords as $word) {
            $query->where(function ($q) use ($word) {
                $like = "%{$word}%";
                $q->where('province_name', 'LIKE', $like)
                    ->orWhere('city_name', 'LIKE', $like)
                    ->orWhere('district_name', 'LIKE', $like)
                    ->orWhere('sub_district_name', 'LIKE', $like)
                    ->orWhere('zip_code', 'LIKE', $like);
            });
        }

        $areas = $query
            ->limit(20)
            ->get();

        $data = $areas->map(function ($row) {
            return [
                'id' => $row->id,
                'province' => $row->province_name,
                'city' => $row->city_name,
                'district' => $row->district_name,
                'sub_district' => $row->sub_district_name,
                'zip_code' => $row->zip_code,
                'value' => trim(
                    implode(', ', array_filter([
                        $row->province_name,
                        $row->city_name,
                        $row->district_name,
                        $row->sub_district_name,
                        $row->zip_code,
                    ]))
                ),
            ];
        });

        return ApiResponse::success('Area search', $data);
    }
}
