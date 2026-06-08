<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProjectCommentController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // GET /projects/{project}/comments
    // Returns JSON list — called by show page on load and after new comment
    public function index($projectId)
    {
        try {
            $comments = ProjectComment::with('user:id,name')
                ->where('project_id', $projectId)
                ->latest()
                ->get()
                ->map(fn($c) => [
                    'id'              => $c->id,
                    'comment'         => $c->comment,
                    'attachment_path' => $c->attachment_path,
                    'user'            => $c->user->name ?? 'Unknown',
                    'created_at'      => $c->created_at->format('d M Y, h:i A'),
                    'can_edit'        => auth()->id() === $c->user_id,
                ]);

            return response()->json(['success' => true, 'comments' => $comments]);

        } catch (\Exception $e) {
            Log::error('[ProjectComment] index failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'comments' => []], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /projects/{project}/comments
    public function store(Request $request, $projectId)
    {
        $request->validate([
            'comment'    => 'required|string|max:2000',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,zip,doc,docx',
        ]);

        DB::beginTransaction();
        try {
            Project::findOrFail($projectId); // verify project exists

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')
                    ->store('project-comments/' . $projectId, 'public');
            }

            $comment = ProjectComment::create([
                'project_id'      => $projectId,
                'user_id'         => auth()->id(),
                'comment'         => $request->comment,
                'attachment_path' => $attachmentPath,
            ]);

            DB::commit();

            Log::info('[ProjectComment] Created', [
                'id'         => $comment->id,
                'project_id' => $projectId,
                'by'         => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'comment' => [
                    'id'              => $comment->id,
                    'comment'         => $comment->comment,
                    'attachment_path' => $comment->attachment_path,
                    'user'            => auth()->user()->name,
                    'created_at'      => $comment->created_at->format('d M Y, h:i A'),
                    'can_edit'        => true,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectComment] Store failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not save comment.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // PUT /projects/{project}/comments/{id}
    public function update(Request $request, $projectId, $id)
    {
        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            $comment = ProjectComment::where('project_id', $projectId)
                ->where('id', $id)
                ->where('user_id', auth()->id()) // only own comments
                ->firstOrFail();

            $comment->update(['comment' => $request->comment]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Comment updated.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectComment] Update failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not update comment.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DELETE /projects/{project}/comments/{id}
    public function destroy($projectId, $id)
    {
        DB::beginTransaction();
        try {
            $comment = ProjectComment::where('project_id', $projectId)
                ->where('id', $id)
                ->where('user_id', auth()->id()) // only own comments
                ->firstOrFail();

            // Delete attachment if exists
            if ($comment->attachment_path) {
                Storage::disk('public')->delete($comment->attachment_path);
            }

            $comment->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Comment deleted.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectComment] Destroy failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not delete comment.'], 500);
        }
    }
}