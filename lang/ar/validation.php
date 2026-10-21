<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'date' => ':attribute ليس تاريخاً صحيحاً.',
    'date_format' => 'صيغة :attribute غير صحيحة.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'in' => 'قيمة :attribute غير صالحة.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'file' => 'يجب أن يكون :attribute ملفاً.',
    'mimetypes' => 'نوع الملف :attribute غير مدعوم.',
    'lte' => [
        'numeric' => 'يجب أن يكون :attribute أصغر من أو يساوي :value.',
    ],
    'min' => [
        'numeric' => 'يجب أن يكون :attribute :min على الأقل.',
        'string' => 'يجب أن يحتوي :attribute على :min أحرف على الأقل.',
    ],
    'max' => [
        'numeric' => 'يجب ألا يتجاوز :attribute :max.',
        'string' => 'يجب ألا يتجاوز :attribute :max حرفاً.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
    ],
    'attributes' => [],
];
