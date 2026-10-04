<?php

namespace App\Http\Controllers;

/**
 * Halaman manajemen user (developer only). Logika CRUD ada di komponen
 * Livewire App\Livewire\UserManager; controller ini hanya merender halaman.
 */
class UserController extends Controller
{
    public function index()
    {
        return view('settings.users');
    }
}
