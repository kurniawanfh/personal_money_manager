<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class VoiceQuotaExceededException extends Exception
{
    protected $message = 'Voice logging quota exceeded for this month. Upgrade to Premium for unlimited voice logs.';

    public function render(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
        ], 429);
    }
}
