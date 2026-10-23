<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
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

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+() -]{8,20}$/'],
            'district' => ['required', Rule::in(self::DISTRICTS)],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'currentPassword' => ['required_with:password', 'current_password'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['password']) && strlen($data['password']) > 72) {
            return response()->json([
                'error' => 'Password maksimal 72 byte.',
                'errors' => ['password' => ['Password maksimal 72 byte.']],
            ], 422);
        }

        $data['email'] = mb_strtolower($data['email'], 'UTF-8');
        unset($data['currentPassword']);

        if (filled($data['password'] ?? null)) {
            $data['password_hash'] = $data['password'];
        }
        unset($data['password']);

        $user->update($data);

        return response()->json(['data' => [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'district' => $user->district,
            'role' => $user->role,
        ]]);
    }
}
