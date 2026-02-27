<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Question\StoreQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Survey;
use App\Services\QuestionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class QuestionController extends Controller
{
    public function __construct(
        protected QuestionService $questionService
    ) {
    }

    public function store(StoreQuestionRequest $request, Survey $survey): JsonResponse
    {
        $user = $request->user();
        $this->ensureSurveyManager($user->role, $survey->created_by, $user->id);

        $data = $request->validated();
        $data['survey_id'] = $survey->id;

        $question = $this->questionService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Question created successfully.',
            'data' => new QuestionResource($question),
        ], Response::HTTP_CREATED);
    }

    protected function ensureSurveyManager(string|UserRole $role, int $creatorId, int $userId): void
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        if ($roleValue === UserRole::Admin->value) {
            return;
        }

        if ($creatorId !== $userId) {
            abort(Response::HTTP_FORBIDDEN, 'You cannot modify this survey.');
        }
    }
}
