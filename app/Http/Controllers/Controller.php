<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function ValidatorErrors($validator){
        $errors_val = array();
        foreach ($validator->errors()->toArray() as $key => $value) {
            $errors_val = array_merge($errors_val, $value);
        }
        return $errors_val;
    }
}
