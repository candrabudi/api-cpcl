<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\GroupField;
use App\Models\GroupFieldRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class GroupFieldController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->get('per_page', 10);

            $documents = GroupField::withCount('allRows')
                ->orderByDesc('id')
                ->paginate($perPage);

            $documents->getCollection()->transform(function ($doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'prepared_by' => $doc->prepared_by,
                    'total_rows' => $doc->all_rows_count,
                ];
            });
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve documents '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Documents retrieved', $documents);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        try {
            $document = GroupField::with(['rows.allChildren'])
                ->where('id', $id)
                ->first();

            if (!$document) {
                return ApiResponse::error('Document not found', 400);
            }
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve document '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Document detail', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'location' => $document->location,
                'document_date' => $document->document_date,
                'prepared_by' => $document->prepared_by,
            ],
            'rows' => $this->mapTree($document->rows),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'title' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'document_date' => 'required|date',
                'prepared_by' => 'required|string|max:255',
                'rows' => 'required|array|max:3000',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        try {
            DB::beginTransaction();

            $document = GroupField::create([
                'title' => trim($request->title),
                'location' => $request->location ? trim($request->location) : null,
                'document_date' => Carbon::parse($request->document_date),
                'prepared_by' => trim($request->prepared_by),
            ]);

            $this->storeRowsRecursive(
                $request->rows,
                $document->id,
                null
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create document', 500);
        }

        return ApiResponse::success(
            'Document created',
            ['id' => $document->id],
            201
        );
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        try {
            $this->validate($request, [
                'rows' => 'required|array|max:3000',
                'rows.*.id' => 'required|integer',
                'rows.*.value' => 'nullable',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        try {
            DB::beginTransaction();

            $document = GroupField::where('id', $id)->first();
            if (!$document) {
                DB::rollBack();

                return ApiResponse::error('Document not found', 400);
            }

            foreach ($request->rows as $row) {
                GroupFieldRow::where('id', $row['id'])
                    ->where('document_id', $document->id)
                    ->update([
                        'value' => $row['value'] ?? null,
                    ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update document', 500);
        }

        return ApiResponse::success('Document updated');
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        try {
            $document = GroupField::where('id', $id)->first();
            if (!$document) {
                return ApiResponse::error('Document not found', 400);
            }

            $document->delete();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete document', 500);
        }

        return ApiResponse::success('Document deleted');
    }

    protected function storeRowsRecursive(array $rows, int $documentId, ?int $parentId)
    {
        foreach ($rows as $index => $row) {
            $created = GroupFieldRow::create([
                'document_id' => $documentId,
                'parent_id' => $parentId,
                'label' => trim($row['label']),
                'row_type' => trim($row['row_type']),
                'order_no' => $row['order_no'] ?? $index,
                'meta' => $row['meta'] ?? null,
                'value' => null,
            ]);

            if (!empty($row['children']) && is_array($row['children'])) {
                $this->storeRowsRecursive(
                    $row['children'],
                    $documentId,
                    $created->id
                );
            }
        }
    }

    protected function mapTree($rows, $level = 0)
    {
        return $rows->map(function ($row) use ($level) {
            return [
                'id' => $row->id,
                'label' => $row->label,
                'row_type' => $row->row_type,
                'value' => $row->value,
                'meta' => $row->meta,
                'parent_id' => $row->parent_id,
                'depth' => $level,
                'children_count' => $row->children->count(),
                'children' => $row->children->isNotEmpty()
                    ? $this->mapTree($row->children, $level + 1)
                    : [],
            ];
        });
    }
}
