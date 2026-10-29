<?php

namespace App\Http\Controllers\Storefront\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration form.
     */
    public function create(): View
    {
        return view('storefront.auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $customerRole = Role::query()->where('code', 'customer')->firstOrFail();

        $user = User::query()->create([
            'role_id' => $customerRole->id,
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        event(new Registered($user));

        // No email verification: the new customer is signed in and can shop
        // right away, back where they were headed (cart, checkout) if anywhere.
        Auth::login($user);

        return redirect()->intended(route('home'))->with('status', 'Đăng ký thành công. Chào mừng bạn đến với cửa hàng!');
    }
}
