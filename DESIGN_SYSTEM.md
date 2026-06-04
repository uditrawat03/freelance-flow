# FreelanceFlow Design System Components

This document outlines all available UI components and their usage.

## Form Components

### Input
Text input with validation, hints, and error support.
```blade
<x-input 
    label="Email" 
    name="email" 
    type="email"
    placeholder="user@example.com"
    hint="We'll never share your email"
/>
```

### Textarea
Multi-line text input with character count support.
```blade
<x-textarea 
    label="Notes" 
    name="notes" 
    rows="4"
    placeholder="Add your notes here..."
/>
```

### Select
Dropdown select with options.
```blade
<x-select 
    label="Status" 
    name="status"
    :options="['active' => 'Active', 'inactive' => 'Inactive']"
/>
```

### Checkbox
Single or group checkboxes.
```blade
<x-checkbox label="I agree to terms" name="terms" />
```

### Radio
Radio button group.
```blade
<x-radio label="Option A" name="choice" value="a" />
<x-radio label="Option B" name="choice" value="b" />
```

## Status & Display Components

### Status Badge
Display status with color coding.
```blade
<x-status-badge status="active" />
<x-status-badge status="paid" />
<x-status-badge status="overdue" />
```

### Badge
Small label badge.
```blade
<x-badge variant="success">Completed</x-badge>
<x-badge variant="warning">In Progress</x-badge>
```

### Alert
Alert message with icon and dismiss button.
```blade
<x-alert type="success">Client saved successfully!</x-alert>
<x-alert type="danger" dismissible="false">Something went wrong</x-alert>
```

## Layout Components

### Card
Container for grouped content.
```blade
<x-card>
    <h3 class="font-semibold">Revenue</h3>
    <p class="text-2xl font-bold">₹125,000</p>
</x-card>
```

### Page Header
Consistent page title and action area.
```blade
<x-page-header title="Clients" subtitle="Manage your clients">
    <x-slot name="actions">
        <x-button>New Client</x-button>
    </x-slot>
</x-page-header>
```

### Modal
Dialog with backdrop and actions.
```blade
<x-modal id="delete-modal" title="Delete Client" size="md">
    Are you sure?
    
    <x-slot name="footer">
        <x-button variant="secondary">Cancel</x-button>
        <x-button>Delete</x-button>
    </x-slot>
</x-modal>
```

## Table Components

### Table
Responsive data table with striping.
```blade
<x-table>
    <x-table-head>
        <x-table-th>Name</x-table-th>
        <x-table-th>Email</x-table-th>
        <x-table-th>Status</x-table-th>
    </x-table-head>
    
    <x-table-body>
        @foreach($clients as $client)
            <x-table-tr>
                <x-table-td>{{ $client->name }}</x-table-td>
                <x-table-td>{{ $client->email }}</x-table-td>
                <x-table-td>
                    <x-status-badge :status="$client->status" />
                </x-table-td>
            </x-table-tr>
        @endforeach
    </x-table-body>
</x-table>
```

## Interaction Components

### Button
Call-to-action button with variants.
```blade
<x-button>Primary</x-button>
<x-button variant="secondary">Secondary</x-button>
<x-button variant="ghost">Ghost</x-button>
```

### Dropdown
Action dropdown menu.
```blade
<x-dropdown label="Actions">
    <x-dropdown-item href="/clients/1/edit">Edit</x-dropdown-item>
    <x-dropdown-item href="/clients/1" destructive>Delete</x-dropdown-item>
</x-dropdown>
```

## Utility Components

### Empty State
Placeholder for empty data.
```blade
<x-empty-state 
    title="No clients yet" 
    description="Start by creating your first client"
    icon="inbox"
>
    <x-button>New Client</x-button>
</x-empty-state>
```

### Skeleton Loader
Loading placeholder.
```blade
<x-skeleton count="5" height="h-12" />
```

## Color System

- **Primary:** Indigo (indigo-600)
- **Success:** Green (green-600)
- **Warning:** Amber (amber-600)
- **Danger:** Red (red-600)
- **Info:** Blue (blue-600)

## Typography Scale

- **h1:** text-3xl font-bold
- **h2:** text-2xl font-semibold
- **h3:** text-lg font-semibold
- **body:** text-sm font-medium
- **small:** text-xs font-medium
