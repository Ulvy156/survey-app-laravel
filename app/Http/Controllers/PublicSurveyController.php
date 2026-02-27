<?php

namespace App\Http\Controllers;

use App\Http\Resources\SurveyResource;
use App\Services\ShareService;
use Illuminate\Http\JsonResponse;

class PublicSurveyController extends Controller
{
    public function __construct(
        protected ShareService $shareService
    ) {
    }

    public function show(string $share_token): JsonResponse
    {
        $survey = $this->shareService->findActivePublicSurvey($share_token);

        return response()->json([
            'success' => true,
            'data' => new SurveyResource($survey),
        ]);
    }
}
