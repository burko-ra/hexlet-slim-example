<?php

class Validator
{
    public function validate($user)
    {
        $errors = [];

        if (empty($user['name'])) {
            $errors['name'] = "Name can't be blank";
        }

        if (empty($user['email'])) {
            $errors['email'] = "Email can't be blank";
        }

        if (empty($user['password'])) {
            $errors['password'] = "Password can't be blank";
        }

        if ($user['password'] !== $user['passwordConfirmation']) {
            $errors['passwordConfirmation'] = "Password confirmation must be equal to the password";
        }

        return $errors;
    }
}