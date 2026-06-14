<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold" :class="badgeClass">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    type: { type: String, default: 'project' },
});

const badgeMap = {
    project: {
        draft: ['bg-surface-muted text-muted', 'Draft'],
        active: ['bg-primary-soft text-primary-hover', 'Active'],
        on_hold: ['bg-yellow-100 text-yellow-800', 'On hold'],
        completed: ['bg-green-100 text-green-800', 'Completed'],
        cancelled: ['bg-red-100 text-red-700', 'Cancelled'],
    },
    invoice: {
        draft: ['bg-surface-muted text-muted', 'Draft'],
        sent: ['bg-primary-soft text-primary-hover', 'Sent'],
        paid: ['bg-green-100 text-green-800', 'Paid'],
        overdue: ['bg-red-100 text-red-700', 'Overdue'],
        cancelled: ['bg-red-100 text-red-700', 'Cancelled'],
    },
};

const badgeClass = computed(() => badgeMap[props.type]?.[props.status]?.[0] ?? 'bg-surface-muted text-muted');
const label = computed(() => badgeMap[props.type]?.[props.status]?.[1] ?? props.status);
</script>
