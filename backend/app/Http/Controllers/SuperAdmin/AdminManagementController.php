<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Hash;

class AdminManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Admin::query();
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%");
                $q->orWhere('email', 'like', "%$search%");
            });
        }
        if (!is_null($request->get('is_active'))) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }
        $admins = $query->select([
            'id',
            'name',
            'email',
            'role',
            'is_active',
            'created_at',
            'updated_at',
            'created_by',
            'created_by_name',
            'updated_by',
            'updated_by_name'
        ])->orderBy('name')->paginate(20);
        return response()->json($admins);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:admin,super_admin',
            'created_by' => 'nullable|integer',
            'created_by_name' => 'nullable|string|max:255',
        ]);
        $admin = new Admin();
        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->role = $data['role'];
        if (!empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        } else {
            $admin->password = Hash::make(str()->random(16));
        }
        $admin->is_active = true;
        
        // Add tracking fields
        $admin->created_by = $data['created_by'] ?? null;
        $admin->created_by_name = $data['created_by_name'] ?? null;
        
        $admin->save();
        // Audit
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_created',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => ['role' => $admin->role],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json($admin, 201);
    }

    public function update(Request $request, Admin $admin)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:admins,email,' . $admin->id,
            'role' => 'sometimes|required|in:admin,super_admin',
            'is_active' => 'sometimes|boolean',
            'updated_by' => 'nullable|integer',
            'updated_by_name' => 'nullable|string|max:255',
        ]);
        // Prevent lockout: avoid demoting or deactivating the last active superadmin
        $currentIsSuper = $admin->role === 'super_admin';
        $desiredRole = array_key_exists('role', $data) ? $data['role'] : $admin->role;
        $desiredIsActive = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : (bool)$admin->is_active;
        if ($currentIsSuper) {
            $activeSupers = Admin::where('role', 'super_admin')->where('is_active', true)->count();
            $isLastActiveSuper = $activeSupers <= 1 && (bool)$admin->is_active;
            if ($isLastActiveSuper && ($desiredRole !== 'super_admin' || $desiredIsActive === false)) {
                return response()->json([
                    'message' => 'Action blocked: at least one active superadmin must remain.'
                ], 422);
            }
        }
        $admin->fill($data);
        
        // Add tracking fields for updates
        if (isset($data['updated_by'])) {
            $admin->updated_by = $data['updated_by'];
        }
        if (isset($data['updated_by_name'])) {
            $admin->updated_by_name = $data['updated_by_name'];
        }
        
        $admin->save();
        // Audit
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_updated',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json($admin);
    }

    public function toggleActive(Request $request, Admin $admin)
    {
        // Block deactivating the last active superadmin
        if ($admin->role === 'super_admin' && (bool)$admin->is_active === true) {
            $activeSupers = Admin::where('role', 'super_admin')->where('is_active', true)->count();
            if ($activeSupers <= 1) {
                return response()->json([
                    'message' => 'Cannot deactivate the last active superadmin.'
                ], 422);
            }
        }
        $admin->forceFill(['is_active' => !$admin->is_active])->save();
        // If deactivated, revoke all tokens to log out everywhere
        if (!$admin->is_active) {
            try { $admin->tokens()->delete(); } catch (\Throwable $e) {}
        }
        // Audit
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => $admin->is_active ? 'admin_reactivated' : 'admin_deactivated',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'target_name' => $admin->email,
            'details' => ['is_active' => (bool)$admin->is_active],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json(['is_active' => (bool) $admin->is_active]);
    }

    public function destroy(Request $request, Admin $admin)
    {
        // Safeguards
        if ($admin->role === 'super_admin') {
            return response()->json(['message' => 'Cannot delete a superadmin account. Deactivate or demote first.'], 422);
        }
        if ((bool)$admin->is_active) {
            return response()->json(['message' => 'Account must be deactivated before deletion.'], 422);
        }
        if ($request->user() && $request->user()->id === $admin->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // Revoke tokens
        try { $admin->tokens()->delete(); } catch (\Throwable $e) {}

        $email = $admin->email;
        $id = $admin->id;
        $admin->delete();

        // Audit
        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'user_name' => $request->user()->name ?? 'Superadmin',
            'action' => 'admin_deleted',
            'target_type' => 'admin',
            'target_id' => $id,
            'target_name' => $email,
            'details' => [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }
}


