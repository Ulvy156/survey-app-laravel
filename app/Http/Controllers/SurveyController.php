<?php

namespace App\Http\Controllers;

use App\Http\Requests\Survey\StoreSurveyRequest;
use App\Http\Resources\SurveyListResource;
use App\Http\Resources\SurveyResource;
use App\Models\Survey;
use App\Services\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SurveyController extends Controller
{
    public function __construct(
        protected SurveyService $surveyService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->surveyService->listForUser($request->user(), $request->all());

        $resource = SurveyListResource::collection($paginator->getCollection());

        return response()->json([
            'success' => true,
            'message' => 'Surveys retrieved successfully',
            'data' => $resource->toArray($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreSurveyRequest $request): JsonResponse
    {
        $survey = $this->surveyService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Survey created successfully',
            'data' => new SurveyResource($survey),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Survey $survey): JsonResponse
    {
        $this->surveyService->deleteSurvey($survey, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Survey deleted successfully.',
        ]);
    }

    public function close(Request $request, Survey $survey): JsonResponse
    {
        $survey = $this->surveyService->closeSurvey($survey, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Survey closed successfully.',
            'data' => new SurveyResource($survey),
        ]);
    }

    public function reopen(Request $request, Survey $survey): JsonResponse
    {
        $survey = $this->surveyService->reopenSurvey($survey, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Survey reopened successfully.',
            'data' => new SurveyResource($survey),
        ]);
    }
}
