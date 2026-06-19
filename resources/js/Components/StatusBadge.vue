<script setup lang="ts">
import { useI18n } from '../lib/i18n';
import { statusTone } from '../lib/format';

defineProps<{
    status: string;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize" :class="statusTone(status)">
        {{ t(`statuses.${status}`, status.replaceAll('_', ' ')) }}
    </span>
</template>
