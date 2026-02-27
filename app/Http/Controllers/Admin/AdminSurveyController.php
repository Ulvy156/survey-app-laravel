<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SurveyListResource;
use App\Http\Resources\SurveyResource;
use App\Services\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSurveyController extends Controller
{
    public function __construct(
        protected SurveyService $surveyService
    ) {
    }

    public function deleted(Request $request): JsonResponse
    {
        $paginator = $this->surveyService->listDeleted($request->all());

        $data = SurveyListResource::collection($paginator->getCollection())->toArray($request);

        return response()->json([
            'success' => true,
            'message' => 'Deleted surveys retrieved successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function restore(int $survey): JsonResponse
    {
        $survey = $this->surveyService->restoreSurvey($survey);

        return response()->json([
            'success' => true,
            'message' => 'Survey restored successfully',
            'data' => new SurveyResource($survey),
        ]);
    }
}
