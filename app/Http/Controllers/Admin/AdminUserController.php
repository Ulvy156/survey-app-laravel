<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($scope) use ($search): void {
                $scope->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->string('role')->toString()) {
            $query->where('role', $role);
        }

        $perPage = max(1, (int) $request->integer('per_page', 15));
        $paginator = $query->latest('created_at')->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => UserResource::collection($paginator->getCollection())->toArray($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ((int) $request->user()->id === (int) $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You cannot delete your own account.');
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }
}
