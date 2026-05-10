<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Panel de administración" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Panel de administración
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div class="p-6">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total juegos</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.total_games }}</p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div class="p-6">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total productos</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.total_products }}</p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div class="p-6">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tiendas activas</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.active_stores }}</p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div class="p-6">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Alertas activas</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.active_alerts }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
