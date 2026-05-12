<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $users = User::where('company_id', $request->user()->company_id)
            ->with('roles')
            ->paginate(15);

        return $this->paginatedResponse($users);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,accountant,staff',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);

        $user->assignRole($request->role);

        return $this->successResponse($user->load('roles'), 'User created.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'role' => 'sometimes|in:admin,accountant,staff',
        ]);

        $user->update($request->only('name', 'status'));

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return $this->successResponse($user->fresh('roles'), 'User updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->errorResponse('Cannot delete your own account.');
        }

        $user->delete();
        return $this->successResponse(null, 'User deleted.');
    }
}
