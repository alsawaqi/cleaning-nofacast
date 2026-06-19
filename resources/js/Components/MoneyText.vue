<script setup lang="ts">
import { money } from '../lib/format';

defineProps<{
    value?: number | null;
}>();
</script>

<template>
    <span>{{ money(value) }}</span>
</template>
