<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class FileTypeValidate implements Rule
{
    protected $extensions;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($extensions)
    {

        $this->extensions = $extensions;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_object($value) || !method_exists($value, 'getClientOriginalExtension')) {
            return false;
        }

        $extension          = strtolower((string) $value->getClientOriginalExtension());
        $allowedExtensions  = array_map(function ($item) {
            return strtolower((string) $item);
        }, (array) $this->extensions);

        return in_array($extension, $allowedExtensions, true);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute file type is not supported.';
    }
}
