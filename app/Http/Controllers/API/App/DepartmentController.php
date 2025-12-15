<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $departments = Department::all();

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully',
            'data' => $departments,
        ], 200);
    }

    /**
     * Store a newly created department in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $department = Department::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => $department,
        ], 201);
    }

    /**
     * Display the specified department.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Department retrieved successfully',
            'data' => $department,
        ], 200);
    }

    /**
     * Update the specified department in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $department->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully',
            'data' => $department,
        ], 200);
    }

    /**
     * Remove the specified department from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ], 200);
    }
}