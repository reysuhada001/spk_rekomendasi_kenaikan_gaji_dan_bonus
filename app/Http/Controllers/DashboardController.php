<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    // Ini adalah entry point / router ke dashboard spesifik role
    public function index()
    {
        $role = Auth::user()->role;

        return match ($role) {
            'owner'    => redirect()->route('dashboard.owner'),
            'hr'       => redirect()->route('dashboard.hr'),
            'leader'   => redirect()->route('dashboard.leader'),
            'karyawan' => redirect()->route('dashboard.karyawan'),
            default    => abort(403, 'Role tidak dikenali'),
        };
    }

    public function owner()    { return view('dashboard.owner'); }
    public function hr()       { return view('dashboard.hr'); }
    public function leader()   { return view('dashboard.leader'); }
    public function karyawan() { return view('dashboard.karyawan'); }
}
