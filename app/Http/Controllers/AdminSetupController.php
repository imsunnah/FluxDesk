<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAssignment;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminSetupController extends Controller
{
    private function checkAdmin()
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }

    // ==========================================
    // 1. DEPARTMENTS
    // ==========================================
    public function storeDepartment(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:departments,code',
            'name' => 'required|string|max:100|unique:departments,name',
            'description' => 'nullable|string',
            'hod_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $dept = Department::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'hod_id' => $validated['hod_id'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()->back()->with('success', "Department '{$dept->name}' created successfully.");
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:departments,code,' . $department->id,
            'name' => 'required|string|max:100|unique:departments,name,' . $department->id,
            'description' => 'nullable|string',
            'hod_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $department->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'hod_id' => $validated['hod_id'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()->back()->with('success', "Department '{$department->name}' updated successfully.");
    }

    public function destroyDepartment(Department $department)
    {
        $this->checkAdmin();

        $staffCount = User::where('department', $department->name)->count();
        if ($staffCount > 0) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete department '{$department->name}' because {$staffCount} staff members are assigned to it."
            ]);
        }

        $department->delete();

        return redirect()->back()->with('success', 'Department deleted successfully.');
    }

    // ==========================================
    // 2. DESIGNATIONS
    // ==========================================
    public function storeDesignation(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'department_name' => 'nullable|string|max:100',
            'band' => 'required|string|in:Entry,Mid,Senior,Lead,Executive',
            'responsibilities' => 'nullable|string',
        ]);

        $deptName = $validated['department_name'] ?? null;
        if (!$deptName && !empty($validated['department_id'])) {
            $dept = Department::find($validated['department_id']);
            $deptName = $dept ? $dept->name : null;
        }

        $desig = Designation::create([
            'title' => $validated['title'],
            'department_id' => $validated['department_id'] ?? null,
            'department_name' => $deptName ?? 'General',
            'band' => $validated['band'] ?? 'Mid',
            'responsibilities' => $validated['responsibilities'] ?? null,
        ]);

        return redirect()->back()->with('success', "Designation '{$desig->title}' created successfully.");
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'department_name' => 'nullable|string|max:100',
            'band' => 'required|string|in:Entry,Mid,Senior,Lead,Executive',
            'responsibilities' => 'nullable|string',
        ]);

        $deptName = $validated['department_name'] ?? $designation->department_name;
        if (!empty($validated['department_id'])) {
            $dept = Department::find($validated['department_id']);
            if ($dept) $deptName = $dept->name;
        }

        $designation->update([
            'title' => $validated['title'],
            'department_id' => $validated['department_id'] ?? $designation->department_id,
            'department_name' => $deptName,
            'band' => $validated['band'],
            'responsibilities' => $validated['responsibilities'] ?? null,
        ]);

        return redirect()->back()->with('success', "Designation '{$designation->title}' updated successfully.");
    }

    public function destroyDesignation(Designation $designation)
    {
        $this->checkAdmin();

        $designation->delete();

        return redirect()->back()->with('success', 'Designation deleted successfully.');
    }

    // ==========================================
    // 3. CLIENTS
    // ==========================================
    public function storeClient(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'org_name' => 'required|string|max:150',
            'reg_number' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'billing_address' => 'nullable|string',
            'poc_name' => 'nullable|string|max:100',
            'poc_email' => 'required|email|max:150',
            'poc_phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        // Create or find matching user for login/tickets
        $clientUser = User::where('email', $validated['poc_email'])->first();
        if (!$clientUser) {
            $clientUser = User::create([
                'member_id' => 'CUST-' . str_pad((string)(User::count() + 101), 3, '0', STR_PAD_LEFT),
                'name' => $validated['org_name'],
                'email' => $validated['poc_email'],
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&h=120&fit=crop',
                'role' => 'customer',
                'department' => 'Client Accounts',
                'designation' => 'Client Representative',
                'is_admin' => false,
                'is_customer' => true,
                'is_team_lead' => false,
            ]);
        }

        $client = Client::create([
            'user_id' => $clientUser->id,
            'org_name' => $validated['org_name'],
            'reg_number' => $validated['reg_number'] ?? 'REG-' . mt_rand(100000, 999999),
            'industry' => $validated['industry'] ?? 'Enterprise SaaS',
            'billing_address' => $validated['billing_address'] ?? null,
            'poc_name' => $validated['poc_name'] ?? $validated['org_name'],
            'poc_email' => $validated['poc_email'],
            'poc_phone' => $validated['poc_phone'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()->back()->with('success', "Client '{$client->org_name}' created successfully.");
    }

    public function updateClient(Request $request, Client $client)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'org_name' => 'required|string|max:150',
            'reg_number' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'billing_address' => 'nullable|string',
            'poc_name' => 'nullable|string|max:100',
            'poc_email' => 'required|email|max:150',
            'poc_phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $client->update($validated);

        if ($client->user) {
            $client->user->update([
                'name' => $validated['org_name'],
                'email' => $validated['poc_email'],
            ]);
        }

        return redirect()->back()->with('success', "Client '{$client->org_name}' updated successfully.");
    }

    public function destroyClient(Client $client)
    {
        $this->checkAdmin();

        $activeProjects = Project::where('client_id', $client->id)
            ->orWhere('client_id', $client->user_id)
            ->count();

        if ($activeProjects > 0) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete client '{$client->org_name}' because {$activeProjects} project(s) are linked to it."
            ]);
        }

        $client->delete();

        return redirect()->back()->with('success', 'Client deleted successfully.');
    }

    // ==========================================
    // 4. CLIENT ASSIGNMENTS (ASSIGN TABLE)
    // ==========================================
    public function storeAssignment(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'client_id' => 'required',
            'primary_manager_id' => 'nullable|exists:users,id',
            'backup_escalation_id' => 'nullable|exists:users,id',
            'selected_departments' => 'nullable|array',
            'assigned_member_ids' => 'nullable|array',
            'sla_level' => 'nullable|string',
            'assignment_date' => 'nullable|date',
            'communication_preference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Resolve client ID (can be Client.id or User.id of customer)
        $clientId = $validated['client_id'];
        $client = Client::find($clientId);
        if (!$client) {
            // Check if passed user_id
            $client = Client::where('user_id', $clientId)->first();
            if (!$client) {
                // Find user and auto-create client record
                $u = User::find($clientId);
                if ($u) {
                    $client = Client::create([
                        'user_id' => $u->id,
                        'org_name' => $u->name,
                        'poc_email' => $u->email,
                        'poc_name' => $u->name,
                        'industry' => 'Enterprise SaaS',
                        'status' => 'active',
                    ]);
                } else {
                    $client = Client::firstOrCreate(
                        ['org_name' => 'General Client'],
                        ['poc_email' => 'client@fluxdesk.io', 'status' => 'active']
                    );
                }
            }
        }

        // Check if assignment already exists for this client (UPDATE rather than duplicate)
        $assignment = ClientAssignment::where('client_id', $client->id)->first();

        $payload = [
            'client_id' => $client->id,
            'primary_manager_id' => $validated['primary_manager_id'] ?? null,
            'backup_escalation_id' => $validated['backup_escalation_id'] ?? null,
            'selected_departments' => $validated['selected_departments'] ?? [],
            'assigned_member_ids' => $validated['assigned_member_ids'] ?? [],
            'sla_level' => $validated['sla_level'] ?? 'Gold',
            'assignment_date' => $validated['assignment_date'] ?? now()->toDateString(),
            'communication_preference' => $validated['communication_preference'] ?? 'Dedicated Slack Connect + Weekly Sync',
            'notes' => $validated['notes'] ?? null,
        ];

        if ($assignment) {
            $assignment->update($payload);
            $msg = "Client handling team for '{$client->org_name}' updated successfully.";
        } else {
            $assignment = ClientAssignment::create($payload);
            $msg = "Client handling team for '{$client->org_name}' established successfully.";
        }

        return redirect()->back()->with('success', $msg);
    }

    public function updateAssignment(Request $request, ClientAssignment $clientAssignment)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'primary_manager_id' => 'nullable|exists:users,id',
            'backup_escalation_id' => 'nullable|exists:users,id',
            'selected_departments' => 'nullable|array',
            'assigned_member_ids' => 'nullable|array',
            'sla_level' => 'sometimes|required|string|in:Standard,Gold,VIP',
            'assignment_date' => 'nullable|date',
            'communication_preference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $clientAssignment->update($validated);

        return redirect()->back()->with('success', 'Client assignment updated successfully.');
    }

    public function destroyAssignment(ClientAssignment $clientAssignment)
    {
        $this->checkAdmin();

        $clientAssignment->delete();

        return redirect()->back()->with('success', 'Client assignment removed.');
    }
}
