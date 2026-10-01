<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    private const DISTRICTS = [
        'Batam Kota',
        'Batu Aji',
        'Batu Ampar',
        'Belakang Padang',
        'Bengkong',
        'Bulang',
        'Galang',
        'Lubuk Baja',
        'Nongsa',
        'Sagulung',
        'Sei Beduk',
        'Sekupang',
    ];

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $user
            ? response()->json(['data' => $this->publicUser($user)])
            : response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
    }

    public function handle(Request $request): JsonResponse
    {
        return match ($request->input('action')) {
            'login' => $this->login($request),
            'register' => $this->register($request),
            'logout' => $this->logout($request),
            default => response()->json(['error' => 'Aksi autentikasi tidak valid.'], 422),
        };
    }

    private function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $credentials = $validator->validated();
        $credentials['email'] = mb_strtolower($credentials['email'], 'UTF-8');
        if (! Auth::attempt($credentials)) {
            return response()->json(['error' => 'Email atau password tidak sesuai.'], 401);
        }

        $request->session()->regenerate();

        return response()->json(['data' => $this->publicUser($request->user())]);
    }

    private function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+() -]{8,20}$/'],
            'district' => ['required', Rule::in(self::DISTRICTS)],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $data = $validator->validated();
        $data['email'] = mb_strtolower($data['email'], 'UTF-8');
        if (strlen($data['password']) > 72) {
            return response()->json(['error' => 'Password maksimal 72 byte.'], 422);
        }
        $data['password_hash'] = $data['password'];
        unset($data['password']);

        $user = User::create($data);

        return response()->json(['data' => $this->publicUser($user)], 201);
    }

    private function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['loggedOut' => true]]);
    }

    private function publicUser(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'district' => $user->district,
            'role' => $user->role,
        ];
    }

    private function invalid($validator): JsonResponse
    {
        return response()->json([
            'error' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }
}
