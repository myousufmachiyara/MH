<?php

namespace App\Http\Controllers;

use App\Models\Sample;
use App\Models\SampleCost;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SamplingController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index($projectId)
    {
        $project = Project::findOrFail($projectId);
        $samples = Sample::with('costs')
            ->where('project_id', $projectId)
            ->latest()
            ->get();

        return view('sampling.index', compact('project', 'samples'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function create($projectId)
    {
        $project = Project::findOrFail($projectId);
        return view('sampling.create', compact('project'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request, $projectId)
    {
        $request->validate([
            'courier_name'               => 'nullable|string|max:255',
            'tracking_no'                => 'nullable|string|max:255',
            'dispatched_at'              => 'nullable|date',
            'received_at'                => 'nullable|date',
            'include_in_project_costing' => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:2000',

            // Cost rows
            'costs'                                => 'nullable|array',
            'costs.*.description'                  => 'required_with:costs|string|max:255',
            'costs.*.amount'                       => 'required_with:costs|numeric|min:0',
            'costs.*.include_in_project_costing'   => 'nullable|boolean',
            'costs.*.borne_by'                     => 'nullable|in:company,customer',
        ]);

        DB::beginTransaction();
        try {
            $project   = Project::findOrFail($projectId);
            $sampleNo  = Sample::generateSampleNo($project);

            $sample = Sample::create([
                'project_id'                 => $projectId,
                'sample_no'                  => $sampleNo,
                'status'                     => 'pending',
                'include_in_project_costing' => $request->boolean('include_in_project_costing', false),
                'courier_name'               => $request->courier_name,
                'tracking_no'                => $request->tracking_no,
                'dispatched_at'              => $request->dispatched_at,
                'received_at'                => $request->received_at,
                'notes'                      => $request->notes,
                'created_by'                 => auth()->id(),
                'updated_by'                 => auth()->id(),
            ]);

            // Save cost rows
            if ($request->filled('costs')) {
                foreach ($request->costs as $costRow) {
                    if (!empty($costRow['description']) && isset($costRow['amount'])) {
                        SampleCost::create([
                            'sample_id'                  => $sample->id,
                            'description'                => $costRow['description'],
                            'amount'                     => $costRow['amount'],
                            'include_in_project_costing' => !empty($costRow['include_in_project_costing']),
                            'borne_by'                   => $costRow['borne_by'] ?? 'company',
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('[Sample] Created', [
                'id'         => $sample->id,
                'sample_no'  => $sampleNo,
                'project_id' => $projectId,
                'by'         => auth()->id(),
            ]);

            return redirect()->route('projects.show', $projectId)
                ->with('success', 'Sample ' . $sampleNo . ' created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Sample] Store failed', [
                'project_id' => $projectId,
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function show($projectId, $id)
    {
        $project = Project::findOrFail($projectId);
        $sample  = Sample::with('costs')->where('project_id', $projectId)->findOrFail($id);

        return view('sampling.show', compact('project', 'sample'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function edit($projectId, $id)
    {
        $project = Project::findOrFail($projectId);
        $sample  = Sample::with('costs')->where('project_id', $projectId)->findOrFail($id);

        // Approved/dropped samples cannot be edited
        if (in_array($sample->status, ['approved', 'dropped'])) {
            return redirect()->route('projects.sampling.show', [$projectId, $id])
                ->with('error', 'Cannot edit a ' . $sample->getStatusLabel() . ' sample.');
        }

        return view('sampling.edit', compact('project', 'sample'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $projectId, $id)
    {
        $request->validate([
            'courier_name'               => 'nullable|string|max:255',
            'tracking_no'                => 'nullable|string|max:255',
            'dispatched_at'              => 'nullable|date',
            'received_at'                => 'nullable|date',
            'include_in_project_costing' => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:2000',

            'costs'                              => 'nullable|array',
            'costs.*.description'                => 'required_with:costs|string|max:255',
            'costs.*.amount'                     => 'required_with:costs|numeric|min:0',
            'costs.*.include_in_project_costing' => 'nullable|boolean',
            'costs.*.borne_by'                   => 'nullable|in:company,customer',
        ]);

        DB::beginTransaction();
        try {
            $sample = Sample::where('project_id', $projectId)->findOrFail($id);

            $sample->update([
                'include_in_project_costing' => $request->boolean('include_in_project_costing', false),
                'courier_name'               => $request->courier_name,
                'tracking_no'                => $request->tracking_no,
                'dispatched_at'              => $request->dispatched_at,
                'received_at'                => $request->received_at,
                'notes'                      => $request->notes,
                'updated_by'                 => auth()->id(),
            ]);

            // Replace costs: delete existing then re-insert
            $sample->costs()->delete();

            if ($request->filled('costs')) {
                foreach ($request->costs as $costRow) {
                    if (!empty($costRow['description']) && isset($costRow['amount'])) {
                        SampleCost::create([
                            'sample_id'                  => $sample->id,
                            'description'                => $costRow['description'],
                            'amount'                     => $costRow['amount'],
                            'include_in_project_costing' => !empty($costRow['include_in_project_costing']),
                            'borne_by'                   => $costRow['borne_by'] ?? 'company',
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('[Sample] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('projects.sampling.show', [$projectId, $id])
                ->with('success', 'Sample updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Sample] Update failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // PATCH /projects/{project}/sampling/{id}/status
    //
    // Handles all status transitions:
    //   pending  → approved  : project status stays (customer approved sample)
    //   pending  → rejected  : requires rejection_reason
    //   rejected → resampled : marks this sample as resampled, creates new sample
    //   rejected → dropped   : marks sample + project as dropped
    public function updateStatus(Request $request, $projectId, $id)
    {
        $request->validate([
            'status'           => ['required', Rule::in(['approved', 'rejected', 'resampled', 'dropped'])],
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $project = Project::findOrFail($projectId);
            $sample  = Sample::where('project_id', $projectId)->findOrFail($id);

            $newStatus = $request->status;

            // ── Transition: approved ─────────────────────────────────
            if ($newStatus === 'approved') {
                $sample->update([
                    'status'     => 'approved',
                    'updated_by' => auth()->id(),
                ]);

                // Move project to po_received if still in sampling
                if ($project->status === 'sampling') {
                    $project->update(['status' => 'po_received', 'updated_by' => auth()->id()]);
                }
            }

            // ── Transition: rejected ─────────────────────────────────
            elseif ($newStatus === 'rejected') {
                $sample->update([
                    'status'           => 'rejected',
                    'rejection_reason' => $request->rejection_reason,
                    'updated_by'       => auth()->id(),
                ]);
            }

            // ── Transition: resampled (rejected → new sample) ────────
            elseif ($newStatus === 'resampled') {
                if ($sample->status !== 'rejected') {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'Only rejected samples can be resampled.');
                }

                // Mark current sample as resampled
                $sample->update([
                    'status'     => 'resampled',
                    'updated_by' => auth()->id(),
                ]);

                // Create a new pending sample for the same project
                $newSampleNo = Sample::generateSampleNo($project);
                $newSample   = Sample::create([
                    'project_id'                 => $projectId,
                    'sample_no'                  => $newSampleNo,
                    'status'                     => 'pending',
                    'include_in_project_costing' => false,
                    'notes'                      => 'Resample of ' . $sample->sample_no,
                    'created_by'                 => auth()->id(),
                    'updated_by'                 => auth()->id(),
                ]);

                DB::commit();

                Log::info('[Sample] Resampled', [
                    'old_sample_id' => $sample->id,
                    'new_sample_id' => $newSample->id,
                    'project_id'    => $projectId,
                    'by'            => auth()->id(),
                ]);

                return redirect()->route('projects.sampling.edit', [$projectId, $newSample->id])
                    ->with('success', 'New sample ' . $newSampleNo . ' created for resample. Fill in the details below.');
            }

            // ── Transition: dropped ──────────────────────────────────
            elseif ($newStatus === 'dropped') {
                if ($sample->status !== 'rejected') {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'Only rejected samples can be dropped.');
                }

                $sample->update([
                    'status'     => 'dropped',
                    'updated_by' => auth()->id(),
                ]);

                // Mark project as dropped
                $project->update(['status' => 'dropped', 'updated_by' => auth()->id()]);
            }

            DB::commit();

            Log::info('[Sample] Status updated', [
                'id'     => $id,
                'status' => $newStatus,
                'by'     => auth()->id(),
            ]);

            return redirect()->route('projects.sampling.show', [$projectId, $id])
                ->with('success', 'Sample status updated to ' . ucfirst($newStatus) . '.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Sample] Status update failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($projectId, $id)
    {
        DB::beginTransaction();
        try {
            $sample = Sample::where('project_id', $projectId)->findOrFail($id);

            if (in_array($sample->status, ['approved'])) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete an approved sample.');
            }

            $sample->costs()->delete();
            $sample->delete();

            DB::commit();

            Log::info('[Sample] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('projects.show', $projectId)
                ->with('success', 'Sample deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Sample] Destroy failed', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Could not delete sample.');
        }
    }
}