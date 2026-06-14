<?php

namespace Tests\Feature;

use App\Jobs\RefreshDashboardCache;
use App\Jobs\SendProjectNotification;
use App\Mail\InvoicePaymentReminder;
use App\Mail\MonthlyRevenueReport;
use App\Mail\ProjectCreated as ProjectCreatedMail;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Workspace;
use App\Notifications\InvoiceOverdue;
use App\Notifications\ProjectStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HorizonConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_defines_isolated_horizon_supervisors_for_scalable_queues(): void
    {
        $this->assertSame(['default'], config('horizon.defaults.supervisor-default.queue'));
        $this->assertSame(['emails'], config('horizon.defaults.supervisor-emails.queue'));
        $this->assertSame(['notifications'], config('horizon.defaults.supervisor-notifications.queue'));
        $this->assertSame(['low'], config('horizon.defaults.supervisor-low.queue'));
        $this->assertSame('auto', config('horizon.defaults.supervisor-default.balance'));
        $this->assertSame('auto', config('horizon.defaults.supervisor-emails.balance'));
        $this->assertSame('auto', config('horizon.defaults.supervisor-notifications.balance'));
        $this->assertSame(10, config('horizon.defaults.supervisor-low.nice'));
    }

    public function test_it_routes_jobs_to_their_dedicated_queues(): void
    {
        $project = Project::factory()->create();

        $this->assertSame('emails', (new SendProjectNotification($project))->queue);
        $this->assertSame('low', (new RefreshDashboardCache((int) $project->workspace_id))->queue);
    }

    public function test_it_routes_queued_mail_and_notifications_to_dedicated_queues(): void
    {
        $project = Project::factory()->create();
        $invoice = Invoice::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->assertSame('emails', (new ProjectCreatedMail($project))->queue);
        $this->assertSame('emails', (new InvoicePaymentReminder($invoice))->queue);
        $this->assertSame('emails', (new MonthlyRevenueReport($workspace, [], Carbon::now()))->queue);
        $this->assertSame('notifications', (new ProjectStatusChanged($project, 'pending'))->queue);
        $this->assertSame('notifications', (new InvoiceOverdue($invoice))->queue);
    }
}
