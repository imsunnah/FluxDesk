<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'client_id' => 'nullable|exists:users,id',
            'status' => 'required|in:todo,in_progress,review,done',
            'priority' => 'required|in:low,medium,high',
            'approximate_hours' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ];

        $validated = $request->validate($rules);
        $validated['created_by'] = $user->id;

        // Auto-assign project's client if not explicitly specified
        if (!empty($validated['project_id']) && empty($validated['client_id'])) {
            $project = Project::find($validated['project_id']);
            if ($project && $project->client_id) {
                $validated['client_id'] = $project->client_id;
            }
        }

        // Permission check: Team Lead / Admin can assign to anyone. Normal staff can only assign to themselves!
        $isLeadOrAdmin = $user->is_team_lead || $user->is_admin;
        if ($isLeadOrAdmin) {
            $assigneeIds = $request->input('assignee_ids', []);
            // Fallback: if empty, default to creator
            if (empty($assigneeIds)) {
                $assigneeIds = [$user->id];
            }
            $validated['assigned_to'] = $assigneeIds[0] ?? $user->id;
        } else {
            // Normal employee creates their own task and CANNOT assign to others
            $assigneeIds = [$user->id];
            $validated['assigned_to'] = $user->id;
        }

        $task = Task::create($validated);

        // Sync multiple assignees
        $task->assignees()->sync($assigneeIds);

        return back()->with('success', 'Task created successfully.');
    }

    public function update(Request $request, Task $task)
    {
        $user = Auth::user();
        $isLeadOrAdmin = $user->is_team_lead || $user->is_admin;

        $rules = [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'client_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|required|in:todo,in_progress,review,done',
            'priority' => 'sometimes|required|in:low,medium,high',
            'approximate_hours' => 'nullable|numeric|min:0',
            'completed_hours' => 'nullable|numeric|min:0',
            'completion_remarks' => 'nullable|string',
            'due_date' => 'nullable|date',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ];

        // If marking as done, require completion_remarks!
        if ($request->input('status') === 'done') {
            $rules['completion_remarks'] = 'required|string|min:3';
        }

        $validated = $request->validate($rules);

        // Check assignee update permission
        if ($request->has('assignee_ids')) {
            if ($isLeadOrAdmin) {
                $assigneeIds = $request->input('assignee_ids', []);
                $task->assignees()->sync($assigneeIds);
                if (!empty($assigneeIds)) {
                    $validated['assigned_to'] = $assigneeIds[0];
                }
            }
        }

        $task->update($validated);

        return back()->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
