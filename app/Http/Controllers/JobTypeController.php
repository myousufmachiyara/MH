<?php

namespace App\Http\Controllers;

use App\Models\JobType;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;

class JobTypeController extends Controller
{
    public function index()
    {
        $jobTypes = JobType::with('serviceCostAccount')->orderBy('name')->get();
        return view('job_types.index', compact('jobTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                     => 'required|string|max:100|unique:job_types,name',
            'service_cost_account_id'  => 'nullable|exists:chart_of_accounts,id',
        ]);

        JobType::create($request->only(['name', 'service_cost_account_id']));

        return back()->with('success', 'Job type created.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                     => 'required|string|max:100|unique:job_types,name,' . $id,
            'service_cost_account_id'  => 'nullable|exists:chart_of_accounts,id',
            'is_active'                => 'nullable|boolean',
        ]);

        $jobType = JobType::findOrFail($id);
        $jobType->update([
            'name'                    => $request->name,
            'service_cost_account_id' => $request->service_cost_account_id,
            'is_active'               => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Job type updated.');
    }

    public function destroy($id)
    {
        try {
            $jobType = JobType::findOrFail($id);
            if ($jobType->jobOrders()->exists()) {
                return back()->with('error', 'Cannot delete — this job type is used by existing job orders.');
            }
            $jobType->delete();
            return back()->with('success', 'Job type deleted.');
        } catch (\Exception $e) {
            return back()->with('error', 'Could not delete job type.');
        }
    }
}