<template>
    <AppLayout>
        <Head :title="`${project.name} analytics`" />

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-muted">{{ project.client.name }}</p>
                <h1 class="mt-1 text-2xl font-bold text-foreground">{{ project.name }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <StatusBadge :status="project.status" type="project" />
                <a :href="project.urls.edit" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm font-bold text-secondary shadow-soft hover:bg-surface-muted">
                    Edit
                </a>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="metric in metrics" :key="metric.label" class="rounded-lg border border-border bg-surface p-5 shadow-soft">
                <p class="text-xs font-bold uppercase tracking-wide text-muted">{{ metric.label }}</p>
                <p class="mt-2 text-2xl font-bold" :class="metric.class">{{ metric.value }}</p>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-border bg-surface shadow-soft">
            <div class="flex flex-col gap-1 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-base font-bold text-foreground">Invoices</h2>
                <p class="text-sm text-muted">
                    Showing {{ invoices.length }} of {{ stats.invoice_count }}
                </p>
            </div>

            <div v-if="invoices.length === 0" class="px-5 py-12 text-center text-sm text-muted">
                No invoices for this project yet.
            </div>

            <div v-else class="divide-y divide-border">
                <div v-for="invoice in invoices" :key="invoice.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-foreground">{{ invoice.number }}</p>
                        <p class="mt-1 text-xs text-muted">
                            {{ invoice.issued_at ? formatDate(invoice.issued_at) : 'No issue date' }}
                            <span v-if="invoice.due_at"> &middot; Due {{ formatDate(invoice.due_at) }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-bold text-secondary">{{ invoice.formatted_total }}</span>
                        <StatusBadge :status="invoice.status" type="invoice" />
                    </div>
                </div>
            </div>

            <div v-if="stats.has_more_invoices" class="border-t border-border px-5 py-3 text-sm font-medium text-muted">
                More invoices exist for this project. Use the invoice list for full filtering and exports.
            </div>
        </section>

        <section v-if="project.tags.length > 0" class="mt-6">
            <h2 class="mb-3 text-sm font-bold text-secondary">Tags</h2>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="tag in project.tags"
                    :key="tag.id"
                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                    :style="{ backgroundColor: `${tag.colour}22`, color: tag.colour }"
                >
                    {{ tag.name }}
                </span>
            </div>
        </section>

        <div class="mt-8">
            <a :href="project.urls.client" class="text-sm font-bold text-primary hover:text-primary-hover">
                Back to {{ project.client.name }}
            </a>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Components/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    project: { type: Object, required: true },
    invoices: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
});

const metrics = computed(() => [
    { label: 'Budget', value: props.project.formatted_budget },
    { label: 'Invoiced', value: props.stats.total_invoiced },
    { label: 'Paid', value: props.stats.total_paid, class: 'text-success' },
    {
        label: 'Outstanding',
        value: props.stats.total_outstanding,
        class: props.stats.outstanding_amount > 0 ? 'text-danger' : 'text-foreground',
    },
]);

function formatDate(dateString) {
    return new Intl.DateTimeFormat('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(dateString));
}
</script>
