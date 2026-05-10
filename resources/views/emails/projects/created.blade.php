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