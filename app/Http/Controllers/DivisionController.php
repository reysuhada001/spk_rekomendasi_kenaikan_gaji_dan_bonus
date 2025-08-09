<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    public function __construct()
    {
        // akses hanya role hr
        $this->middleware('role:hr');
    }

    public function index(Request $request)
    {
        $search  = $request->input('search', '');
        $perPage = (int) $request->input('per_page', 10);

        $divisions = Division::when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->appends(['search' => $search, 'per_page' => $perPage]);

        return view('divisions.index', compact('divisions', 'search', 'perPage'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:divisions,name',
        ]);

        Division::create($request->only('name'));

        return redirect()->route('divisions.index')->with('success', 'Divisi berhasil ditambahkan.');
    }

    public function update(Request $request, Division $division)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:divisions,name,' . $division->id,
        ]);

        $division->update($request->only('name'));

        return redirect()->route('divisions.index')->with('success', 'Divisi berhasil diperbarui.');
    }

    public function destroy(Division $division)
    {
        $division->delete();
        return redirect()->route('divisions.index')->with('success', 'Divisi berhasil dihapus.');
    }
}