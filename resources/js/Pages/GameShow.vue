<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import ReviewList from '@/Components/ReviewList.vue';
import ReviewForm from '@/Components/ReviewForm.vue';

const props = defineProps({
    game: {
        type: Object,
        required: true,
    },
    products: {
        type: Array,
        required: true,
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
    reviews: {
        type: Array,
        default: () => [],
    },
});

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
};

const genres = computed(() => {
    const g = props.game.genres;
    if (Array.isArray(g)) return g;
    if (typeof g === 'string') {
        try {
            const parsed = JSON.parse(g);
            return Array.isArray(parsed) ? parsed : [g];
        } catch {
            return [g];
        }
    }
    return [];
});

const schemaScript = computed(() => {
    if (!props.seo.schema) return null;
    return JSON.stringify(props.seo.schema);
});
</script>

<template>
    <Head>
        <title>{{ seo.title || game.title }}</title>
        <meta name="description" :content="seo.description" />
        <link rel="canonical" :href="seo.canonical" />
        <meta property="og:title" :content="seo.og?.title" />
        <meta property="og:description" :content="seo.og?.description" />
        <meta property="og:image" :content="seo.og?.image" />
        <meta property="og:type" :content="seo.og?.type" />
        <meta property="og:url" :content="seo.canonical" />
        <script v-if="schemaScript" type="application/ld+json">{{ schemaScript }}</script>
    </Head>

    <div class="min-h-screen bg-gray-900 text-white">
        <header class="border-b border-gray-700 bg-gray-800">
            <div class="mx-auto flex max-w-7xl items-center px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="text-2xl font-bold text-white">
                    Game<span class="text-blue-500">Price</span><span class="text-gray-400">.es</span>
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <Breadcrumbs :items="[
                { label: 'Inicio', href: '/' },
                { label: game.title },
            ]" />

            <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <img
                        :src="game.cover_image"
                        :alt="game.title"
                        class="w-full rounded-lg shadow-lg"
                    />
                </div>

                <div class="lg:col-span-2">
                    <h1 class="text-4xl font-bold">{{ game.title }}</h1>
                    <p class="mt-2 text-gray-400">
                        {{ game.developer }}
                        <span v-if="game.release_date" class="ml-2">&middot; {{ formatDate(game.release_date) }}</span>
                    </p>

                    <div v-if="genres.length" class="mt-4 flex flex-wrap gap-2">
                        <Link
                            v-for="genre in genres"
                            :key="genre"
                            :href="route('genre.show', genre)"
                            class="rounded-full bg-gray-700 px-3 py-1 text-xs text-gray-300 transition hover:bg-gray-600 hover:text-white"
                        >
                            {{ genre }}
                        </Link>
                    </div>

                    <p v-if="game.description" class="mt-6 leading-relaxed text-gray-300">
                        {{ game.description }}
                    </p>

                    <div v-if="game.metacritic_score" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-700 px-3 py-1">
                        <span class="text-xs text-gray-400">Metacritic</span>
                        <span class="text-sm font-bold" :class="game.metacritic_score >= 75 ? 'text-green-400' : game.metacritic_score >= 50 ? 'text-yellow-400' : 'text-red-400'">
                            {{ game.metacritic_score }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <h2 class="mb-4 text-2xl font-bold">Comparar precios</h2>

                <div v-if="products.length > 0 && products.some(p => p.is_real_price)" class="overflow-x-auto rounded-lg border border-gray-700">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Tienda</th>
                                <th class="px-4 py-3">Plataforma</th>
                                <th class="px-4 py-3">Regi&oacute;n</th>
                                <th class="px-4 py-3">
                                    <div class="inline-flex items-center gap-1">
                                        Precio
                                        <svg class="h-3 w-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-4 py-3">Descuento</th>
                                <th class="px-4 py-3">Ahorro</th>
                                <th class="px-4 py-3">Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <tr v-for="(product, index) in products.filter(p => p.is_real_price)" :key="product.id" class="bg-gray-800 hover:bg-gray-750">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="product.store?.logo_url"
                                            :src="product.store.logo_url"
                                            :alt="product.store.name"
                                            class="h-6 w-6 rounded object-contain"
                                        />
                                        <span class="font-medium">{{ product.store?.name }}</span>
                                        <span
                                            v-if="index === 0"
                                            class="ml-1 rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                        >
                                            Mejor oferta
                                        </span>
                                        <span
                                            v-if="product.is_real_price"
                                            class="ml-1 rounded bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                            title="Precio verificado de tienda oficial"
                                        >
                                            Precio real
                                        </span>
                                        <span
                                            v-else
                                            class="ml-1 rounded bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                            title="Precio estimado, puede no ser exacto"
                                        >
                                            Estimado
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ product.platform || 'Steam' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ product.region || 'Global' }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-bold text-green-400">{{ Number(product.current_price).toFixed(2) }}&euro;</span>
                                    <span v-if="product.original_price && Number(product.original_price) > Number(product.current_price)" class="ml-2 text-xs text-gray-500 line-through">
                                        {{ Number(product.original_price).toFixed(2) }}&euro;
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="Number(product.discount_percentage) > 0" class="font-bold text-red-400">
                                        -{{ product.discount_percentage }}%
                                    </span>
                                    <span v-else class="text-gray-500">&mdash;</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="product.original_price && Number(product.original_price) > Number(product.current_price)" class="font-semibold text-blue-400">
                                        {{ (Number(product.original_price) - Number(product.current_price)).toFixed(2) }}&euro;
                                    </span>
                                    <span v-else class="text-gray-500">&mdash;</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a
                                        :href="product.affiliate_url || product.url"
                                        target="_blank"
                                        rel="nofollow"
                                        class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                                    >
                                        Ver oferta
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="rounded-lg bg-gray-800 p-8 text-center text-gray-400">
                    No hay precios disponibles para este juego.
                </p>
            </div>

            <div class="mt-10 space-y-6">
                <ReviewList :reviews="reviews" />
                <ReviewForm :game-slug="game.slug" />
            </div>
        </main>
    </div>
</template>
