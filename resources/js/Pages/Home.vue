<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import GameCard from '@/Components/GameCard.vue';
import SearchBar from '@/Components/SearchBar.vue';
import SkeletonCard from '@/Components/SkeletonCard.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import AppFooter from '@/Components/AppFooter.vue';

const props = defineProps({
    games: {
        type: Object,
        required: true,
    },
    trendingGames: {
        type: Array,
        default: () => [],
    },
    bestDeals: {
        type: Array,
        default: () => [],
    },
    newReleases: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref('');
const loading = ref(false);

router.on('start', () => { loading.value = true; });
router.on('finish', () => { loading.value = false; });

const bestPriceSlug = computed(() => {
    let bestSlug = null;
    let bestPrice = Infinity;
    props.games.data.forEach((game) => {
        if (!game.products?.length) return;
        const min = Math.min(...game.products.map((p) => Number(p.current_price)));
        if (min < bestPrice) {
            bestPrice = min;
            bestSlug = game.slug;
        }
    });
    return bestSlug;
});
</script>

<template>
    <Head>
        <title>{{ seo.title || 'GamePrice.es' }}</title>
        <meta name="description" :content="seo.description" />
        <link rel="canonical" :href="seo.canonical" />
        <meta property="og:title" :content="seo.og?.title" />
        <meta property="og:description" :content="seo.og?.description" />
        <meta property="og:image" :content="seo.og?.image" />
        <meta property="og:type" :content="seo.og?.type" />
        <meta property="og:url" :content="seo.canonical" />
    </Head>

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
            <Breadcrumbs :items="[{ label: 'Inicio' }]" />

            <!-- Hero Section -->
            <section class="py-12 text-center">
                <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
                    Encuentra los mejores precios de videojuegos
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-400">
                    Compara ofertas de tiendas oficiales y grey market en segundos
                </p>
                <div class="mx-auto mt-8 max-w-lg">
                    <SearchBar v-model="search" />
                </div>
            </section>

            <!-- Trending Section -->
            <section v-if="trendingGames.length" class="mb-12">
                <h2 class="mb-4 text-2xl font-bold text-white">🔥 Juegos Trending</h2>
                <div class="flex gap-4 overflow-x-auto pb-4">
                    <div v-for="game in trendingGames" :key="game.slug" class="w-60 flex-shrink-0">
                        <GameCard :game="game" />
                    </div>
                </div>
            </section>

            <!-- Best Deals Section -->
            <section v-if="bestDeals.length" class="mb-12">
                <h2 class="mb-4 text-2xl font-bold text-white">💎 Mejores Descuentos</h2>
                <div class="flex gap-4 overflow-x-auto pb-4">
                    <div v-for="game in bestDeals" :key="game.slug" class="w-60 flex-shrink-0">
                        <GameCard :game="game" :show-discount="true" />
                    </div>
                </div>
            </section>

            <!-- New Releases Section -->
            <section v-if="newReleases.length" class="mb-12">
                <h2 class="mb-4 text-2xl font-bold text-white">🆕 Últimos Lanzamientos</h2>
                <div class="flex gap-4 overflow-x-auto pb-4">
                    <div v-for="game in newReleases" :key="game.slug" class="w-60 flex-shrink-0">
                        <GameCard :game="game" :show-release-date="true" />
                    </div>
                </div>
            </section>

            <!-- All Games Section -->
            <section class="mb-12">
                <h2 class="mb-4 text-2xl font-bold text-white">Todos los juegos</h2>

                <div v-if="loading" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <SkeletonCard v-for="n in 8" :key="n" />
                </div>

                <div v-else-if="games.data.length > 0" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <GameCard
                        v-for="game in games.data"
                        :key="game.slug"
                        :game="game"
                        :is-best-price="game.slug === bestPriceSlug"
                    />
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
            </section>
        </main>

        <AppFooter />
    </div>
</template>
