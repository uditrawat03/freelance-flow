# Day 09 — Form Validation Deep Dive

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 15 min · **Level:** Beginner to Intermediate

---

> *"Day 08 gave FreelanceFlow full CRUD. But our validation is still basic — required fields, email format, that's it. Real applications need more. Custom messages, conditional rules, validating across multiple fields, clearing errors programmatically. Today we go deep."*

---

## Where We Are

At the end of Day 08, FreelanceFlow can create, read, update, and soft-delete clients. Validation is working — but it is using the default Laravel error messages and the most basic rule syntax.

Today we level up validation across the whole app:

1. Custom error messages on `#[Rule]` attributes
2. Validating a property individually as the user types
3. Writing a custom validation rule class for business logic
4. Using `$this->resetValidation()` to clear errors programmatically
5. Real-world patterns you will use throughout this series

---

## Quick Recap — How Livewire Validation Works

Before going deeper, let us be clear about what happens under the hood when Livewire validates.

When you call `$this->validate()`, Livewire reads the `#[Rule]` attribute on each property, runs Laravel's standard Validator against the current property values, and if any rule fails it throws a `ValidationException`. Livewire catches that exception, stores the errors in the session, and re-renders the component. The `<flux:error name="field" />` components in the view read those stored errors and display them.

This means **all of Laravel's built-in validation rules work in Livewire**. Every rule you know from Laravel forms — `min`, `max`, `regex`, `exists`, `unique`, `confirmed`, `date_format`, `mimes` — works identically inside a `#[Rule]` attribute.

```php
// These all work exactly the same in Livewire
#[Rule('required|string|min:2|max:255')]
public string $name = '';

#[Rule('required|email|unique:clients,email')]
public string $email = '';

#[Rule('nullable|numeric|min:0')]
public int|null $hourly_rate = null;

#[Rule('required|date|after:today')]
public string $due_date = '';
```

---

## Part 1 — Custom Error Messages

By default, Laravel generates error messages automatically from the field name and rule. `required` on `name` produces "The name field is required." That is fine, but it is generic. Real applications need messages that sound like a human wrote them.

### Inline message on the attribute

The `#[Rule]` attribute accepts a `message` parameter:

```php
#[Rule('required|string|min:2|max:255', message: 'Please enter the client\'s full name.')]
public string $name = '';
```

But this applies the same message to every rule on that property. If `required` and `min` both fail, they both show the same message — which is not ideal.

### Per-rule messages

Pass an array of messages keyed by rule name:

```php
#[Rule(
    rule: 'required|string|min:2|max:255',
    message: [
        'required' => 'The client name cannot be empty.',
        'min'      => 'The name must be at least 2 characters.',
        'max'      => 'The name is too long — maximum 255 characters.',
    ]
)]
public string $name = '';

#[Rule(
    rule: 'required|email|max:255|unique:clients,email',
    message: [
        'required' => 'An email address is required.',
        'email'    => 'That does not look like a valid email address.',
        'unique'   => 'A client with this email already exists in FreelanceFlow.',
    ]
)]
public string $email = '';

#[Rule(
    rule: 'required|in:active,inactive,lead',
    message: [
        'required' => 'Please select a client status.',
        'in'       => 'Status must be active, inactive, or lead.',
    ]
)]
public string $status = 'active';
```

Update the `Create` and `Edit` components in FreelanceFlow with these messages. The difference in user experience is immediate — errors now read like something a product team wrote, not a framework.

---

## Part 2 — Validating a Single Property

When using `wire:model.live`, Livewire re-renders the component on every keystroke. But by default it does not re-validate until `$this->validate()` is called. This means the error for a field can lag behind what the user is typing.

The fix is `validateOnly()` — validate a single property in real time:

```php
// app/Livewire/Clients/Create.php

public function updatedEmail(): void
{
    $this->validateOnly('email');
}

public function updatedName(): void
{
    $this->validateOnly('name');
}
```

Livewire calls `updated{PropertyName}()` automatically whenever that property changes. By calling `validateOnly('email')` inside `updatedEmail()`, the email field validates every time the user types — but only the email field, not the entire form.

This creates a tight feedback loop:

- User types in the email field
- `updatedEmail()` fires
- `validateOnly('email')` runs only the email rules
- `<flux:error name="email" />` updates immediately

The other fields are not touched until the user interacts with them or submits the form.

Add `updatedEmail()` and `updatedName()` to both the `Create` and `Edit` components. The form now feels genuinely reactive — it guides the user as they type rather than dumping errors only on submit.

```php
// Full updated Create component with per-field validation

class Create extends Component
{
    #[Rule(
        rule: 'required|string|min:2|max:255',
        message: [
            'required' => 'The client name cannot be empty.',
            'min'      => 'The name must be at least 2 characters.',
        ]
    )]
    public string $name = '';

    #[Rule(
        rule: 'required|email|max:255|unique:clients,email',
        message: [
            'required' => 'An email address is required.',
            'email'    => 'That does not look like a valid email address.',
            'unique'   => 'A client with this email already exists in FreelanceFlow.',
        ]
    )]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $phone = '';

    #[Rule('nullable|string|max:255')]
    public string $company = '';

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule(
        rule: 'required|in:active,inactive,lead',
        message: ['required' => 'Please select a client status.']
    )]
    public string $status = 'active';

    // Per-field real-time validation
    public function updatedName(): void
    {
        $this->validateOnly('name');
    }

    public function updatedEmail(): void
    {
        $this->validateOnly('email');
    }

    public function save(): void
    {
        $this->validate();

        Client::create([
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'company' => $this->company,
            'notes'   => $this->notes,
            'status'  => $this->status,
        ]);

        session()->flash('success', 'Client added successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.clients.create');
    }
}
```

---

## Part 3 — Resetting Validation Errors

Sometimes you need to clear validation errors programmatically — for example, when the user clicks a Cancel button on a modal, or when you reset a form after a successful save.

```php
// Clear all validation errors
$this->resetValidation();

// Clear validation error for a specific field
$this->resetValidation('email');

// Clear multiple specific fields
$this->resetValidation(['email', 'name']);
```

In FreelanceFlow, add this to the Edit component's `confirmDelete()` method — when the user opens the delete modal, clear the form validation errors so they do not distract from the confirmation:

```php
public function confirmDelete(): void
{
    $this->resetValidation(); // clear any lingering form errors
    $this->confirmingDelete = true;
}
```

Also use it when the user closes the modal without deleting:

```php
public function cancelDelete(): void
{
    $this->confirmingDelete = false;
    // No need to reset validation here — the form is still showing
}
```

And if you ever need to reset the entire form (properties + validation errors) together:

```php
public function resetForm(): void
{
    $this->reset(['name', 'email', 'phone', 'company', 'notes']);
    $this->status = 'active'; // reset to default value
    $this->resetValidation();
}
```

---

## Part 4 — Custom Validation Rule Class

Sometimes a validation rule is too complex for a string. Business logic that cannot be expressed with Laravel's built-in rules belongs in a dedicated Rule class.

In FreelanceFlow, imagine a rule: a client's email domain cannot be a free consumer email service (Gmail, Yahoo, Hotmail) — FreelanceFlow is for business clients only.

Create the rule:

```bash
php artisan make:rule BusinessEmail
```

Open `app/Rules/BusinessEmail.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BusinessEmail implements ValidationRule
{
    // Consumer email domains we do not accept
    private array $blockedDomains = [
        'gmail.com',
        'yahoo.com',
        'hotmail.com',
        'outlook.com',
        'icloud.com',
        'live.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(substr(strrchr($value, '@'), 1));

        if (in_array($domain, $this->blockedDomains)) {
            $fail('Please use a business email address. Consumer email providers are not accepted.');
        }
    }
}
```

Now use it in the `Create` component. The `#[Rule]` attribute accepts a rule class instance using the `rule` parameter as an array:

```php
use App\Rules\BusinessEmail;

#[Rule(
    rule: ['required', 'email', 'max:255', 'unique:clients,email', new BusinessEmail],
    message: [
        'required' => 'An email address is required.',
        'email'    => 'That does not look like a valid email address.',
        'unique'   => 'A client with this email already exists.',
    ]
)]
public string $email = '';
```

> When mixing string rules with a Rule class object, pass them as an **array** rather than a pipe-separated string. Laravel processes both formats but arrays are required when including object instances.

Now if someone tries to register `john@gmail.com` as a client, they see the custom business email message immediately.

---

## Part 5 — Validation Across Multiple Fields

Sometimes a rule depends on the value of another field. Laravel's built-in rules cover many of these cases — `required_if`, `required_with`, `same`, `different` — but you can also handle cross-field validation inside the action method itself using `$this->addError()`:

```php
public function save(): void
{
    $this->validate();

    // Cross-field: if status is 'lead', phone is required
    if ($this->status === 'lead' && empty($this->phone)) {
        $this->addError('phone', 'A phone number is required for leads.');
        return;
    }

    Client::create([...]);

    session()->flash('success', 'Client added successfully.');
    $this->redirect(route('clients.index'), navigate: true);
}
```

`$this->addError('field', 'message')` adds a validation error manually to any field. The `<flux:error name="phone" />` in the view picks it up automatically — same as any other validation error.

---

## Part 6 — Available Validation Rules Reference

A selection of the most useful Laravel validation rules for a SaaS like FreelanceFlow:

```php
// String rules
'required'          // must be present and not empty
'string'            // must be a string
'min:2'             // minimum length 2
'max:255'           // maximum length 255
'alpha'             // letters only
'alpha_num'         // letters and numbers only
'regex:/^[A-Z]/'    // must match regex pattern

// Number rules
'numeric'           // must be a number
'integer'           // must be an integer
'min:0'             // minimum value
'max:9999'          // maximum value
'between:1,100'     // must be between two values
'decimal:0,2'       // up to 2 decimal places

// Date rules
'date'              // valid date
'date_format:Y-m-d' // specific format
'after:today'       // must be a future date
'before:2030-01-01' // must be before a date

// Database rules
'unique:clients,email'              // must not exist in table
'unique:clients,email,'.$this->client->id  // unique except current row
'exists:clients,id'                 // must exist in table

// Conditional rules
'nullable'          // allow null / empty
'sometimes'         // only validate if field is present
'required_if:status,lead'    // required when another field equals a value
'required_with:company'      // required when another field is present
'confirmed'         // must match field_confirmation property

// File rules (for Day 16 - File Uploads)
'file'
'image'
'mimes:jpg,png,pdf'
'max:2048'  // kilobytes
```

---

## What We Learned Today

- **Per-rule custom messages** on `#[Rule]` using the `messages` array parameter
- **`validateOnly('field')`** inside `updated{PropertyName}()` for per-field real-time validation
- **`resetValidation()`** — clear all errors, a specific field, or multiple fields at once
- **Custom Rule classes** with `php artisan make:rule` — for business logic that cannot be expressed in a string
- **`$this->addError()`** — add errors manually for cross-field validation
- **Mixing Rule objects with string rules** using array syntax instead of pipe-separated strings
- The full Laravel validation rule reference that applies inside Livewire

---

## Day 10 — Blade Components & Slots

Tomorrow we clean up the UI. Right now every form in FreelanceFlow repeats the same HTML patterns — page headers, flash messages, form cards, status badges. We will extract these into reusable Blade components with slots so every page in the app stays consistent and maintenance becomes a single-file change.

See you on Day 10.