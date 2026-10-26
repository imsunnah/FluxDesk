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

        $isOwnerOrAssignee = $task->assigned_to === $user->id 
            || $task->created_by === $user->id 
            || $task->assignees()->where('users.id', $user->id)->exists();

        if (!$isLeadOrAdmin && !$isOwnerOrAssignee) {
            abort(403, 'You do not have permission to update this task.');
        }

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

        $validated = $request->validate($rules);

        // If marking as done via drag-and-drop or modal, stamp completed_at and default remark if missing
        if ($request->input('status') === 'done') {
            $validated['completed_at'] = now();
            if (empty($validated['completion_remarks'])) {
                $validated['completion_remarks'] = $task->completion_remarks ?: 'Completed successfully.';
            }
        }

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

    /**
     * Delay Handling & Hierarchical Reason Lock
     */
    public function updateDelayReason(Request $request, Task $task)
    {
        $user = Auth::user();
        $isLeadOrAdmin = $user->is_team_lead || $user->is_admin;

        $request->validate([
            'delay_category' => 'required|string|max:100',
            'delay_reason' => 'required|string|min:5',
            'action' => 'nullable|string|in:submit,finalize',
        ]);

        // Hierarchy Rule:
        // If a Team Lead updates/confirms the delay reason, the field becomes permanently locked for the Employee.
        if (!$isLeadOrAdmin && $task->delay_status === 'finalized_by_lead') {
            return back()->withErrors(['delay_reason' => 'This delay explanation has been finalized and permanently locked by the Team Lead.']);
        }

        $category = $request->input('delay_category');
        $note = $request->input('delay_reason');
        $actionType = $request->input('action', 'submit');

        $revisions = $task->delay_revisions ?: [];

        if ($isLeadOrAdmin) {
            $isFinalizing = $actionType === 'finalize';
            $newStatus = $isFinalizing ? 'finalized_by_lead' : 'awaiting_lead_review';

            $revisions[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->is_admin ? 'Admin' : 'Team Lead',
                'action' => $isFinalizing ? 'Finalized & Locked by Lead' : 'Reviewed & Updated by Lead',
                'category' => $category,
                'note' => $note,
                'timestamp' => now()->toIso8601String(),
            ];

            $task->update([
                'delay_category' => $category,
                'delay_reason' => $note,
                'delay_status' => $newStatus,
                'delay_locked_by' => $isFinalizing ? $user->id : $task->delay_locked_by,
                'delay_locked_at' => $isFinalizing ? now() : $task->delay_locked_at,
                'delay_revisions' => $revisions,
            ]);
        } else {
            // Employee updating delay reason
            $revisions[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => 'Employee',
                'action' => 'Submitted by Employee',
                'category' => $category,
                'note' => $note,
                'timestamp' => now()->toIso8601String(),
            ];

            $task->update([
                'delay_category' => $category,
                'delay_reason' => $note,
                'delay_status' => 'awaiting_lead_review',
                'delay_revisions' => $revisions,
            ]);
        }

        return back()->with('success', 'Delay reason saved successfully.');
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
