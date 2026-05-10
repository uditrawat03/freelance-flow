# Day 20 — Sending Emails with Laravel Mail

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"FreelanceFlow can manage clients and projects — but it cannot communicate. When a new project is created, the client hears nothing. When an invoice is ready, there is no notification. Email bridges that gap. Today FreelanceFlow sends its first email — a project notification to the client — using Laravel Mailable classes, markdown templates, and Mailpit for local testing."*

---

## Where We Are

FreelanceFlow has clients, projects, tags, file attachments, and a polished notification system. What it cannot do yet is reach outside the browser. Today we fix that — FreelanceFlow sends a professional email to a client when a new project is created for them.

---

## What We Are Building Today

1. **Mailpit setup** — a local email testing tool, catches all outgoing mail
2. A **ProjectCreated Mailable** — the email class and its data
3. A **markdown email template** — styled, readable, professional
4. **Sending from a Livewire action** — triggered on project creation
5. **Mail configuration** — `.env` settings for different environments
6. **Testing the full flow** in the browser

---

## Step 1 — Set Up Mailpit for Local Testing

Mailpit is a local SMTP server that catches every email your application sends and displays it in a web UI. Nothing actually goes to a real inbox during development — Mailpit intercepts everything.

If you are using Laravel Herd or a Sail-based setup, Mailpit is included. For a standard local setup, install it:

```bash
# macOS with Homebrew
brew install mailpit
mailpit

# Or run via Docker
docker run -d -p 1025:1025 -p 8025:8025 axllent/mailpit
```

Mailpit listens on port `1025` for SMTP and serves its web UI at `http://localhost:8025`.

Update your `.env` to send mail through Mailpit:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@freelanceflow.test"
MAIL_FROM_NAME="FreelanceFlow"
```

Now every email FreelanceFlow sends will appear in Mailpit's web UI at `http://localhost:8025` — with full HTML rendering, headers, and raw source view.

---

## Step 2 — Create the Mailable Class

A Mailable is a PHP class that represents one email. It holds the data the email needs, defines the template it uses, and configures the headers, subject, and recipients.

```bash
php artisan make:mail ProjectCreated --markdown=emails.projects.created
```

This creates two files:
- `app/Mail/ProjectCreated.php` — the Mailable class
- `resources/views/emails/projects/created.blade.php` — the markdown template

Open `app/Mail/ProjectCreated.php`:

```php
<?php

namespace App\Mail;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Project $project,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New project: {$this->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.projects.created',
            with: [
                'project'    => $this->project,
                'client'     => $this->project->client,
                'projectUrl' => route('clients.show', $this->project->client_id),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
```

**What each part does:**

- `use Queueable` — the Mailable is ready to be queued. We send it synchronously today and queue it on Day 21
- `use SerializesModels` — safely serialises Eloquent models for the queue. The model is re-fetched from the database when the queued job runs
- `public readonly Project $project` — passes the project to the template as a public property. Anything `public` on the Mailable is automatically available in the view
- `envelope()` — configures the email subject. Can also set `from`, `replyTo`, `cc`, `bcc`
- `content()` — specifies which template to use and passes extra data beyond the public properties

---

## Step 3 — Build the Email Template

Open `resources/views/emails/projects/created.blade.php`:

```blade
<x-mail::message>

# New Project Started

Hi {{ $client->name }},

We have created a new project for you on **FreelanceFlow**.

Here are the details:

<x-mail::panel>
**{{ $project->name }}**

@if ($project->description)
{{ $project->description }}
@endif

| | |
|---|---|
| **Status** | {{ $project->status_label }} |
@if ($project->budget)
| **Budget** | {{ $project->formatted_budget }} |
@endif
@if ($project->deadline)
| **Deadline** | {{ $project->deadline->format('F j, Y') }} |
@endif
</x-mail::panel>

You can view the full project details and track its progress using the button below.

<x-mail::button :url="$projectUrl" color="primary">
View Project
</x-mail::button>

If you have any questions, simply reply to this email.

Thanks,
**FreelanceFlow**

---

<small>You are receiving this because a project was created for you on FreelanceFlow.</small>

</x-mail::message>
```

**Markdown mail components:**

- `<x-mail::message>` — the outer wrapper. Applies the base layout, logo, and footer
- `<x-mail::panel>` — a highlighted box inside the email
- `<x-mail::button :url="$url">` — a styled CTA button
- `<x-mail::table>` — a table with alternating row colours

Standard Markdown syntax works inside these components — `**bold**`, `# Heading`, `- list items`.

---

## Step 4 — Send the Email from the Livewire Create Component

Open `app/Livewire/Projects/Create.php` and update the `save()` method:

```php
use App\Mail\ProjectCreated;
use Illuminate\Support\Facades\Mail;

public function save(): void
{
    $this->validate();

    $project = Project::create([
        'client_id'   => $this->selectedClientId,
        'name'        => $this->name,
        'description' => $this->description,
        'status'      => $this->status,
        'budget'      => $this->budget ?: null,
        'deadline'    => $this->deadline ?: null,
    ]);

    $project->tags()->sync($this->selectedTags);

    // Load the client relationship before passing to the Mailable
    $project->load('client');

    // Send the notification email to the client
    Mail::to($project->client->email)
        ->send(new ProjectCreated($project));

    session()->flash('success', 'Project created and client notified.');

    $this->redirect(
        route('clients.show', $this->selectedClientId),
        navigate: true
    );
}
```

Visit `http://localhost:8000`, create a new project, and then open Mailpit at `http://localhost:8025`. The email is there — rendered with full HTML, the project details panel, and the View Project button.

---

## Step 5 — Customise the Mail Theme

Laravel's markdown mail uses a default blue theme. Customise it to match FreelanceFlow's indigo brand.

Publish the mail views and config:

```bash
php artisan vendor:publish --tag=laravel-mail
```

This copies the mail templates to `resources/views/vendor/mail/`. Open `resources/views/vendor/mail/html/themes/default.css` and update the primary colour:

```css
/* Find these variables and update them */
.button-primary {
    background-color: #6366f1; /* indigo-500 */
}

a {
    color: #6366f1;
}
```

Or create a custom theme file. In `config/mail.php`:

```php
'markdown' => [
    'theme' => 'freelanceflow', // custom theme name
    'paths' => [
        resource_path('views/vendor/mail'),
    ],
],
```

Copy `resources/views/vendor/mail/html/themes/default.css` to `freelanceflow.css` and customise freely.

---

## Step 6 — Additional Mail Scenarios for FreelanceFlow

Here are the other emails FreelanceFlow will send as Phase 2 progresses. Create Mailable stubs for them now so they are ready:

```bash
php artisan make:mail InvoiceSent --markdown=emails.invoices.sent
php artisan make:mail PaymentReceived --markdown=emails.payments.received
php artisan make:mail ProjectStatusChanged --markdown=emails.projects.status-changed
```

Each follows the same pattern as `ProjectCreated` — constructor receives the Eloquent model, template renders the data.

---

## Step 7 — Mail Configuration for Different Environments

```env
# Local development — Mailpit catches everything
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025

# Production — use a transactional mail service
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=your-postmark-api-key
MAIL_PASSWORD=your-postmark-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@yourapp.com
MAIL_FROM_NAME="FreelanceFlow"

# Alternative: use Mailgun
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=your-mailgun-key

# Alternative: use SES (AWS)
MAIL_MAILER=ses
```

Laravel supports multiple mail drivers natively — SMTP, Mailgun, Postmark, SES, Resend, Sendmail, and `log` (writes to the log file instead of sending). The `log` driver is useful for CI testing:

```env
# In .env.testing — mail goes to the log, not an inbox
MAIL_MAILER=log
```

---

## Mail Facade Reference

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\ProjectCreated;

// Send to one recipient
Mail::to('client@example.com')->send(new ProjectCreated($project));

// Send to a model — uses $model->email automatically
Mail::to($client)->send(new ProjectCreated($project));

// Send to multiple recipients
Mail::to([$client, 'manager@freelanceflow.test'])->send(new ProjectCreated($project));

// CC and BCC
Mail::to($client)
    ->cc('manager@freelanceflow.test')
    ->bcc('archive@freelanceflow.test')
    ->send(new ProjectCreated($project));

// Queue for background processing (Day 21)
Mail::to($client)->queue(new ProjectCreated($project));

// Delay a queued mail
Mail::to($client)->later(now()->addMinutes(5), new ProjectCreated($project));

// Send to a specific address regardless of environment
// (useful for testing specific email addresses)
Mail::to('test@example.com')->send((new ProjectCreated($project))->to('test@example.com'));

// Check in tests
Mail::fake();
Mail::assertSent(ProjectCreated::class);
Mail::assertSent(ProjectCreated::class, fn ($mail) => $mail->hasTo($client->email));
Mail::assertNotSent(ProjectCreated::class);
```

---

## A Note on Performance

Right now `Mail::send()` is synchronous — it blocks the request until the email is sent. If the mail server is slow or temporarily unavailable, the user's form submission hangs.

On Day 21 we fix this with queues — `Mail::queue()` instead of `Mail::send()`. The email is handed off to a background worker immediately and the response returns to the user in milliseconds. For today, synchronous sending is fine on local development. Never use it in production for transactional mail.

---

## What We Learned Today

- **Mailpit** — a local SMTP catcher. Every outgoing email appears in the web UI at `http://localhost:8025`. Never reaches a real inbox during development
- **Mailable class** — `php artisan make:mail ClassName --markdown=template`. Represents one email with its data, subject, and template
- **`use Queueable, SerializesModels`** — standard traits on every Mailable. Queueable enables background sending. SerializesModels safely handles Eloquent models in queued jobs
- **`public readonly` constructor properties** — automatically available in the email template. No need to pass them explicitly in `content()`
- **`envelope()`** — subject, from, replyTo, CC, BCC configuration
- **`content()`** — template path and additional view data via `with: []`
- **Markdown mail components** — `<x-mail::message>`, `<x-mail::panel>`, `<x-mail::button>`, `<x-mail::table>`
- **`Mail::to($model)->send(new Mailable())`** — sends synchronously. `::queue()` sends in the background
- **`Mail::fake()` and `Mail::assertSent()`** — testing mail without actually sending
- **`$project->load('client')`** — always eager-load relationships before passing a model to a Mailable

---

## Day 21 — Queues & Jobs

Today's email send is synchronous — the user waits for the mail server to respond before the page redirects. Tomorrow we fix that. We will set up Laravel queues, convert `Mail::send()` to `Mail::queue()`, build a custom Job class, and run the queue worker so emails go out in the background without the user ever waiting.

See you on Day 21.