<script setup>
const props = defineProps({
    format: {
        type: String,
        default: 'leaderboard', // 'leaderboard' | 'medium-rectangle'
    },
});
</script>

<template>
    <div class="mx-auto flex items-center justify-center">
        <div
            class="flex items-center justify-center rounded bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400"
            :class="{
                'w-[728px] h-[90px] max-w-full': format === 'leaderboard',
                'w-[300px] h-[250px] max-w-full': format === 'medium-rectangle',
            }"
        >
            <span class="text-sm font-medium">Publicidad</span>
        </div>
    </div>
</template>
