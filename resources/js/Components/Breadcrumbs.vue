<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-6">
        <ol class="flex flex-wrap items-center gap-2 text-sm text-gray-400">
            <li>
                <Link href="/" class="hover:text-white transition">Inicio</Link>
            </li>
            <li v-for="(item, index) in items" :key="index" class="flex items-center gap-2">
                <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <Link v-if="item.href" :href="item.href" class="hover:text-white transition">{{ item.label }}</Link>
                <span v-else class="text-white" aria-current="page">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
