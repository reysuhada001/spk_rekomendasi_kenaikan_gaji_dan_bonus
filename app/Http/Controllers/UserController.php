<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function __construct()
    {
        // index boleh owner/hr/leader, selain itu hr saja
        $this->middleware('role:owner hr leader')->only('index');
        $this->middleware('role:hr')->except('index');
    }

    public function index(Request $request)
    {
        $search  = $request->input('search', '');
        $perPage = (int) $request->input('per_page', 10);

        $query = User::with('division')
            ->when($search, function ($q) use ($search) {
                $q->where(function($qq) use ($search) {
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%")
                       ->orWhere('nik','like',"%{$search}%")
                       ->orWhere('role','like',"%{$search}%");
                });
            })
            ->orderBy('full_name');

        // Leader hanya lihat user di divisinya
        $me = Auth::user();
        if ($me->role === 'leader') {
            $query->where('division_id', $me->division_id);
        }

        $users = $query->paginate($perPage)->appends(['search'=>$search,'per_page'=>$perPage]);
        $divisions = Division::orderBy('name')->get();

        return view('users.index', compact('users','divisions','search','perPage','me'));
    }

    public function store(Request $request)
    {
        // hanya hr (sudah dibatasi middleware)
        $validated = $request->validate([
            'full_name'  => 'required|string|max:255',
            'nik'        => 'required|string|max:50|unique:users,nik',
            'email'      => 'required|email|max:255|unique:users,email',
            'username'   => 'required|string|max:50|unique:users,username',
            'password'   => 'required|string|min:6',
            'role'       => 'required|in:owner,hr,leader,karyawan',
            'division_id'=> 'nullable|exists:divisions,id',
            'photo'      => 'nullable|image|max:2048',
        ]);

        // Rule: division wajib jika role leader/karyawan
        if (in_array($validated['role'], ['leader','karyawan']) && empty($validated['division_id'])) {
            return back()->with('error','Divisi wajib diisi untuk role leader/karyawan.')->withInput();
        }
        if (in_array($validated['role'], ['owner','hr'])) {
            $validated['division_id'] = null;
        }

        // handle foto
        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('users','public');
        }

        User::create([
            'full_name'      => $validated['full_name'],
            'nik'            => $validated['nik'],
            'email'          => $validated['email'],
            'username'       => $validated['username'],
            'password'       => Hash::make($validated['password']),
            'plain_password' => $validated['password'], // sesuai permintaan
            'role'           => $validated['role'],
            'division_id'    => $validated['division_id'] ?? null,
            'photo'          => $path,
        ]);

        return redirect()->route('users.index')->with('success','User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'full_name'  => 'required|string|max:255',
            'nik'        => 'required|string|max:50|unique:users,nik,' . $user->id,
            'email'      => 'required|email|max:255|unique:users,email,' . $user->id,
            'username'   => 'required|string|max:50|unique:users,username,' . $user->id,
            'password'   => 'nullable|string|min:6',
            'role'       => 'required|in:owner,hr,leader,karyawan',
            'division_id'=> 'nullable|exists:divisions,id',
            'photo'      => 'nullable|image|max:2048',
        ]);

        // Rule divisi
        if (in_array($validated['role'], ['leader','karyawan']) && empty($validated['division_id'])) {
            return back()->with('error','Divisi wajib diisi untuk role leader/karyawan.')->withInput();
        }
        if (in_array($validated['role'], ['owner','hr'])) {
            $validated['division_id'] = null;
        }

        // Foto
        $path = $user->photo;
        if ($request->hasFile('photo')) {
            if ($path) Storage::disk('public')->delete($path);
            $path = $request->file('photo')->store('users','public');
        }

        // Password opsional
        $data = [
            'full_name'   => $validated['full_name'],
            'nik'         => $validated['nik'],
            'email'       => $validated['email'],
            'username'    => $validated['username'],
            'role'        => $validated['role'],
            'division_id' => $validated['division_id'] ?? null,
            'photo'       => $path,
        ];
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
            $data['plain_password'] = $validated['password'];
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success','User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $user->delete();
        return redirect()->route('users.index')->with('success','User berhasil dihapus.');
    }
}