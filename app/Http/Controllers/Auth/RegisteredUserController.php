<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'phone' => $request->input('phone'),
                'password' => $request->string('password'),
                'role' => UserRole::CUSTOMER,
            ]);

            CustomerProfile::create([
                'user_id' => $user->id,
                'full_name' => $user->name,
                'phone' => $user->phone,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('home'));
    }
}
