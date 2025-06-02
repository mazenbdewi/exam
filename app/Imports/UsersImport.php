<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Spatie\Permission\Models\Role;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // تنقية البيانات من القيم الفارغة
        $filteredRow = array_filter($row, function ($value) {
            return $value !== null;
        });

        // إذا لم يكن هناك بيانات كافية، تخطى الصف
        if (count($filteredRow) < 4) {
            return null;
        }

        // تعيين كلمة مرور مشفرة
        $user = User::create([
            'name' => $filteredRow['name'],
            'email' => $filteredRow['email'],
            'password' => Hash::make($filteredRow['email']), // استخدام التشفير
            'max_observers' => $filteredRow['max_observers'],
        ]);

        if ($role = Role::where('name', $filteredRow['role'])->first()) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required',
            'role' => 'required|string|exists:roles,name',
            'max_observers' => 'required|integer',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'حقل الاسم مطلوب',
            'email.required' => 'حقل البريد الإلكتروني مطلوب',
            'email.email' => 'يجب إدخال بريد إلكتروني صحيح',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل',
            'role.required' => 'حقل الدور مطلوب',
            'role.exists' => 'الدور المحدد غير موجود',
            'max_observers.required' => 'حقل عدد المراقبين مطلوب',
            'max_observers.integer' => 'يجب أن يكون عدداً صحيحاً',
        ];
    }
}
