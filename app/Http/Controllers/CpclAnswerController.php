<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\CpclAnswer;
use App\Models\CpclApplicant;
use App\Models\GroupFieldRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CpclAnswerController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cpcl_document_id' => 'required|integer|exists:cpcl_documents,id',
            'answers' => 'required|array|min:1',
            'answers.*.group_field_row_id' => 'required|integer|exists:group_field_rows,id',
            'answers.*.value' => 'nullable',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $cpclApplicant = CpclApplicant::where('cpcl_document_id', $request->cpcl_document_id)
            ->first();

        if (!$cpclApplicant) {
            return ApiResponse::error('Please insert CPCL applicant first.', 400);
        }

        try {
            DB::beginTransaction();

            foreach ($request->answers as $answer) {
                CpclAnswer::updateOrCreate(
                    [
                        'cpcl_document_id' => $request->cpcl_document_id,
                        'group_field_row_id' => $answer['group_field_row_id'],
                    ],
                    [
                        'answer_value' => $answer['value'],
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error(
                'Failed to save CPCL answers '.$e->getMessage(),
                500
            );
        }

        return ApiResponse::success('CPCL answers saved');
    }

    public function show($cpclDocumentId)
    {
        if (!is_numeric($cpclDocumentId)) {
            return ApiResponse::error('Invalid parameter', 400);
        }

        $rows = GroupFieldRow::with([
            'children' => function ($q) {
                $q->orderBy('order_no');
            },
        ])
            ->whereNull('parent_id')
            ->orderBy('order_no')
            ->get();

        $answers = CpclAnswer::where('cpcl_document_id', $cpclDocumentId)
            ->get()
            ->keyBy('group_field_row_id');

        $result = $rows->map(function ($row) use ($answers) {
            return $this->mapRowWithAnswer($row, $answers);
        });

        return ApiResponse::success('CPCL schema with answers', $result);
    }

    protected function mapRowWithAnswer($row, $answers)
    {
        $data = [
            'id' => $row->id,
            'label' => $row->label,
            'row_type' => $row->row_type,
            'is_required' => $row->is_required,
            'meta' => $row->meta,
            'value' => $answers[$row->id]->answer_value ?? null,
            'children' => [],
        ];

        if ($row->children && $row->children->count()) {
            $data['children'] = $row->children->map(function ($child) use ($answers) {
                return $this->mapRowWithAnswer($child, $answers);
            })->values();
        }

        return $data;
    }
}
