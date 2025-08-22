<?php
namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        return Vendor::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string|unique:vendors,phone',
            'location' => 'nullable|string',
            'email' => 'nullable|email|unique:vendors,email',
        ]);

        return Vendor::create($validated);
    }

    public function show(Vendor $vendor)
    {
        return $vendor;
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name' => 'string',
            'phone' => 'string|unique:vendors,phone,' . $vendor->id,
            'location' => 'nullable|string',
            'email' => 'nullable|email|unique:vendors,email,' . $vendor->id,
        ]);

        $vendor->update($validated);
        return $vendor;
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
?>
