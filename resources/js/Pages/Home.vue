<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GameCard from '@/Components/GameCard.vue';
import SearchBar from '@/Components/SearchBar.vue';

defineProps({
    games: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref('');
</script>

<template>
    <Head title="GamePrice.es - Compara precios de videojuegos" />

    <div class="min-h-screen bg-gray-900 text-white">
        <header class="border-b border-gray-700 bg-gray-800">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="text-2xl font-bold text-white">
                    Game<span class="text-blue-500">Price</span><span class="text-gray-400">.es</span>
                </Link>
                <div class="w-full max-w-md ml-8">
                    <SearchBar v-model="search" />
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="games.data.length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <GameCard v-for="game in games.data" :key="game.slug" :game="game" />
            </div>

            <div v-else class="py-20 text-center">
                <p class="text-xl text-gray-400">No se encontraron juegos</p>
            </div>

            <div v-if="games.last_page > 1" class="mt-8 flex justify-center gap-2">
                <Link
                    v-for="page in games.last_page"
                    :key="page"
                    :href="route('home', { page, search: filters.search })"
                    :class="[
                        'rounded-lg px-4 py-2 text-sm font-medium transition',
                        page === games.current_page
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-700 text-gray-300 hover:bg-gray-600',
                    ]"
                >
                    {{ page }}
                </Link>
            </div>
        </main>
    </div>
</template>
