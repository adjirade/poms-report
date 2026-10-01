<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ValidationRule;

class ValidationRuleController extends Controller
{
    /**
     * Display validation rules for management
     */
    public function index()
    {
        $user = auth()->user();
        
        $rules = ValidationRule::where('plant_id', $user->plant_id)
            ->orderBy('station_name')
            ->orderBy('parameter_name')
            ->get()
            ->groupBy('station_name');

        return view('settings.validation-rules', compact('rules'));
    }

    /**
     * Update validation rule
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'min_value' => 'required|numeric',
            'max_value' => 'required|numeric|gt:min_value',
        ]);

        $rule = ValidationRule::findOrFail($id);
        
        // Check if user has permission to edit this plant's rules
        if ($rule->plant_id !== auth()->user()->plant_id && !auth()->user()->hasRole('developer')) {
            abort(403, 'Unauthorized to edit this validation rule.');
        }

        $rule->update([
            'min_value' => $request->min_value,
            'max_value' => $request->max_value,
        ]);

        return back()->with('success', 'Validation rule updated successfully.');
    }
}
