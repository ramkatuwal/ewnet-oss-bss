<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\UploadAvatarRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Update user profile information
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->update($validated);

        AuditService::log('profile.update', 'success', $user, [
            'updated_fields' => array_keys($validated),
        ]);

        $user->load(['roles.permissions', 'company', 'branch.region.company', 'department.branch']);

        return new UserResource($user);
    }

    /**
     * Change user password
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            AuditService::log('profile.password_change', 'failure', $user, [
                'reason' => 'invalid_current_password',
            ]);

            return response()->json([
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->password_changed_at = now();
        $user->save();

        AuditService::log('profile.password_change', 'success', $user);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(UploadAvatarRequest $request)
    {
        $user = $request->user();
        $oldAvatar = $user->avatar;

        // Store new avatar first
        $path = $request->file('avatar')->store('avatars', 'public');

        // Update user record
        $user->avatar = $path;
        $user->save();

        // Delete old avatar after successful save
        if ($oldAvatar) {
            try {
                if (Storage::disk('public')->exists($oldAvatar)) {
                    Storage::disk('public')->delete($oldAvatar);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to delete old avatar: ' . $e->getMessage());
            }
        }

        AuditService::log('profile.avatar_upload', 'success', $user, [
            'avatar_path' => $path,
            'old_avatar_removed' => (bool) $oldAvatar,
        ]);

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Remove user avatar
     */
    public function removeAvatar(Request $request)
    {
        $user = $request->user();
        $avatarPath = $user->avatar;

        // Update user record first
        $user->avatar = null;
        $user->save();

        // Delete the file after successful save
        if ($avatarPath) {
            try {
                if (Storage::disk('public')->exists($avatarPath)) {
                    Storage::disk('public')->delete($avatarPath);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to delete avatar file: ' . $e->getMessage());
            }
        }

        AuditService::log('profile.avatar_remove', 'success', $user, [
            'removed_path' => $avatarPath,
        ]);

        return response()->json([
            'message' => 'Avatar removed successfully',
        ]);
    }
}
