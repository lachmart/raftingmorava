<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

// Email templates – Superadmin only (Gate::before). Admin has no access here.
class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, EmailTemplate $template): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EmailTemplate $template): bool
    {
        return false;
    }

    public function delete(User $user, EmailTemplate $template): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
