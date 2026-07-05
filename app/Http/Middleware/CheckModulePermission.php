<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CheckPermission middleware
 * ──────────────────────────
 * Usage in routes (always pass the FULL permission string):
 *   ->middleware('check.permission:module.action')
 *
 * Examples:
 *   ->middleware('check.permission:projects.index')
 *   ->middleware('check.permission:vouchers.create')
 *   ->middleware('check.permission:reports.inventory')
 *
 * WHY we always pass the full string — not just the module:
 * The original CheckModulePermission tried to infer the action from
 * the route method name (store→create, update→edit, destroy→delete).
 * This breaks for:
 *   - Custom actions (dispatch, receive, status, print)
 *   - AJAX helper routes that don't follow CRUD naming
 *   - Routes with the same method name but different permissions
 * Passing the full permission string is explicit, readable, and safe.
 *
 * FIX: returns JSON 403 when the request expects JSON.
 * Without this, fetch() AJAX calls that fail permission checks receive
 * an HTML redirect page → JSON.parse() throws → confusing "network error"
 * in the catch block instead of a clear "Access denied" message.
 */
class CheckModulePermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        // ── Not authenticated ────────────────────────────────────────
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }
            return redirect()->route('login');
        }

        // ── Check the permission ─────────────────────────────────────
        if (!auth()->user()->can($permission)) {
            Log::channel('daily')->warning('[Permission] Denied', [
                'user_id'    => auth()->id(),
                'permission' => $permission,
                'url'        => $request->fullUrl(),
                'method'     => $request->method(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }

            return redirect()->back()
                ->with('error', 'Access denied. You do not have permission to perform this action.');
        }

        return $next($request);
    }
}