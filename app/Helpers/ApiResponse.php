<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;

class ApiResponse
{
    protected static function meta()
    {
        return [
            'timestamp' => Carbon::now()->toIso8601String(),
            'request_id' => (string) Str::uuid(),
        ];
    }

    protected static function formatValidationErrors(array $errors)
    {
        $result = [];

        foreach ($errors as $field => $messages) {
            $result[] = [
                'field' => $field,
                'message' => $messages[0],
            ];
        }

        return $result;
    }

    public static function success($message, $data = [], $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'meta' => self::meta(),
        ], $code);
    }

    public static function validationError($errors, $code = 422)
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => self::formatValidationErrors($errors),
            'meta' => self::meta(),
        ], $code);
    }

    public static function error($message, $code = 400)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'meta' => self::meta(),
        ], $code);
    }
}
