<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    stores: {
        type: Array,
        default: () => [],
    },
    regions: {
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
    onDemandSearchUrl: {
        type: String,
        default: '',
    },
});

const search = ref(props.filters.search || '');
const loading = ref(false);
const filtersVisible = ref(false);
const searchingOnDemand = ref(false);
const onDemandError = ref('');

const page = usePage();
watch(() => page.props.flash?.error, (msg) => {
    if (msg) {
        onDemandError.value = msg;
        searchingOnDemand.value = false;
    }
}, { immediate: true });

const form = ref({
    price_min: props.filters.price_min || '',
    price_max: props.filters.price_max || '',
    discount_min: props.filters.discount_min || '',
    region: props.filters.region || '',
    store: props.filters.store ? props.filters.store.split(',') : [],
    sort: props.filters.sort || '',
});

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

const hasActiveFilters = computed(() => {
    return form.value.price_min || form.value.price_max || form.value.discount_min
        || form.value.region || form.value.store.length || form.value.sort;
});

function applyFilters() {
    const params = {};
    if (search.value) params.search = search.value;
    if (form.value.price_min) params.price_min = form.value.price_min;
    if (form.value.price_max) params.price_max = form.value.price_max;
    if (form.value.discount_min) params.discount_min = form.value.discount_min;
    if (form.value.region) params.region = form.value.region;
    if (form.value.store.length) params.store = form.value.store.join(',');
    if (form.value.sort) params.sort = form.value.sort;
    router.get(route('home'), params, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
    form.value = { price_min: '', price_max: '', discount_min: '', region: '', store: [], sort: '' };
    router.get(route('home'), {}, { preserveState: true, preserveScroll: true });
}

function toggleStore(slug) {
    const idx = form.value.store.indexOf(slug);
    if (idx === -1) form.value.store.push(slug);
    else form.value.store.splice(idx, 1);
}

function paginationParams(page) {
    const params = { page };
    if (props.filters.search) params.search = props.filters.search;
    if (props.filters.price_min) params.price_min = props.filters.price_min;
    if (props.filters.price_max) params.price_max = props.filters.price_max;
    if (props.filters.discount_min) params.discount_min = props.filters.discount_min;
    if (props.filters.region) params.region = props.filters.region;
    if (props.filters.store) params.store = props.filters.store;
    if (props.filters.sort) params.sort = props.filters.sort;
    return params;
}

function searchOnSteam() {
    searchingOnDemand.value = true;
    onDemandError.value = '';
    router.post(props.onDemandSearchUrl, { query: search.value }, {
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => {
            onDemandError.value = errors.query || 'No se encontró el juego en Steam';
            searchingOnDemand.value = false;
        },
        onFinish: () => {
            searchingOnDemand.value = false;
        },
    });
}
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
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-white">Todos los juegos</h2>
                    <button
                        type="button"
                        @click="filtersVisible = !filtersVisible"
                        class="flex items-center gap-2 rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-gray-300 transition hover:bg-gray-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filtros
                        <span v-if="hasActiveFilters" class="h-2 w-2 rounded-full bg-blue-500"></span>
                    </button>
                </div>

                <!-- Filter Panel -->
                <div v-if="filtersVisible" class="mb-6 rounded-lg border border-gray-700 bg-gray-800 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-400">Precio mínimo (€)</label>
                            <input
                                v-model="form.price_min"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0"
                                class="w-full rounded-md border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-400">Precio máximo (€)</label>
                            <input
                                v-model="form.price_max"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="100"
                                class="w-full rounded-md border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-400">Descuento mínimo (%)</label>
                            <input
                                v-model="form.discount_min"
                                type="number"
                                min="0"
                                max="100"
                                placeholder="0"
                                class="w-full rounded-md border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-400">Región</label>
                            <select
                                v-model="form.region"
                                class="w-full rounded-md border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Todas</option>
                                <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-gray-400">Ordenar por</label>
                        <select
                            v-model="form.sort"
                            class="w-full rounded-md border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none sm:w-auto"
                        >
                            <option value="">Relevancia</option>
                            <option value="price_asc">Precio: menor a mayor</option>
                            <option value="price_desc">Precio: mayor a menor</option>
                            <option value="discount_desc">Mayor descuento</option>
                            <option value="release_desc">Más recientes</option>
                            <option value="name_asc">Nombre A-Z</option>
                        </select>
                    </div>

                    <div v-if="stores.length" class="mt-4">
                        <label class="mb-2 block text-xs font-medium text-gray-400">Tiendas</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="store in stores"
                                :key="store.slug"
                                type="button"
                                @click="toggleStore(store.slug)"
                                :class="[
                                    'rounded-md border px-3 py-1.5 text-xs font-medium transition',
                                    form.store.includes(store.slug)
                                        ? 'border-blue-500 bg-blue-600 text-white'
                                        : 'border-gray-600 bg-gray-700 text-gray-300 hover:bg-gray-600',
                                ]"
                            >
                                {{ store.name }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-3">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                        >
                            Aplicar
                        </button>
                        <button
                            type="button"
                            @click="resetFilters"
                            class="rounded-lg bg-gray-700 px-5 py-2 text-sm font-medium text-gray-300 transition hover:bg-gray-600"
                        >
                            Resetear
                        </button>
                    </div>
                </div>

                <p class="mb-4 text-sm text-gray-400">
                    Mostrando {{ games.total ?? games.data.length }} juego{{ (games.total ?? games.data.length) !== 1 ? 's' : '' }}
                </p>

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
                    <template v-if="search">
                        <p class="mt-2 text-sm text-gray-500">
                            No encontramos '{{ search }}' en nuestra base de datos
                        </p>
                        <button
                            v-if="!searchingOnDemand"
                            type="button"
                            @click="searchOnSteam"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700"
                        >
                            🔍 Buscar en Steam
                        </button>
                        <div v-else class="mt-4 flex items-center justify-center gap-2 text-sm text-gray-400">
                            <svg class="h-5 w-5 animate-spin text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Buscando en Steam...
                        </div>
                        <p v-if="onDemandError" class="mt-3 text-sm text-red-400">{{ onDemandError }}</p>
                    </template>
                </div>

                <div v-if="games.last_page > 1" class="mt-8 flex justify-center gap-2">
                    <Link
                        v-for="page in games.last_page"
                        :key="page"
                        :href="route('home', paginationParams(page))"
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
