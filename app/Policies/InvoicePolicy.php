<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    /**
     * Who can see the invoice list.
     * Any authenticated user can list their own invoices.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Who can view a specific invoice.
     * Only the owner can view their invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    /**
     * Any authenticated user can create invoices.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner can update an invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    /**
     * Only the owner can delete an invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    /**
     * Restore a soft-deleted invoice.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    /**
     * Permanently delete.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    // Custom ability — only the owner can send payment links
    public function sendPaymentLink(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            && in_array($invoice->status, ['sent', 'overdue']);
    }
}