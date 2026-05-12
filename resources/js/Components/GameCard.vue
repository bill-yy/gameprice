<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    game: {
        type: Object,
        required: true,
    },
    isBestPrice: {
        type: Boolean,
        default: false,
    },
    showDiscount: {
        type: Boolean,
        default: false,
    },
    showReleaseDate: {
        type: Boolean,
        default: false,
    },
});

const realPriceProducts = (products) => {
    if (!products?.length) return [];
    return products.filter((p) => p.is_real_price);
};

const lowestPrice = (products) => {
    const real = realPriceProducts(products);
    if (!real.length) return null;
    return Math.min(...real.map((p) => Number(p.current_price)));
};

const maxDiscount = (products) => {
    const real = realPriceProducts(products);
    if (!real.length) return 0;
    return Math.max(...real.map((p) => Number(p.discount_percent)));
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <Link
        :href="route('game.show', game.slug)"
        class="group relative block overflow-hidden rounded-lg bg-gray-800 transition-all duration-200 hover:ring-2 hover:ring-blue-500"
    >
        <!-- Discount badge corner -->
        <div
            v-if="showDiscount && maxDiscount(game.products) > 0"
            class="absolute right-0 top-0 z-10 rounded-bl-lg bg-red-600 px-2 py-1 text-xs font-bold text-white shadow-md"
        >
            -{{ maxDiscount(game.products) }}%
        </div>

        <!-- Best price badge -->
        <div
            v-if="isBestPrice"
            class="absolute left-0 top-0 z-10 rounded-br-lg bg-green-600 px-2 py-1 text-xs font-bold text-white shadow-md"
        >
            Mejor precio
        </div>

        <img
            :src="game.cover_image"
            :alt="game.title"
            class="h-40 w-full object-cover"
        />
        <div class="p-4">
            <h3 class="truncate text-sm font-semibold text-white">
                {{ game.title }}
            </h3>
            <p class="mt-1 text-xs text-gray-400">{{ game.developer }}</p>
            <div v-if="showReleaseDate && game.release_date" class="mt-1 text-xs text-gray-500">
                {{ formatDate(game.release_date) }}
            </div>
            <div class="mt-2 flex items-center justify-between">
                <span v-if="lowestPrice(game.products) !== null" class="text-sm font-bold text-green-400">
                    desde {{ lowestPrice(game.products).toFixed(2) }}&euro;
                </span>
                <span v-else class="text-xs text-gray-500">Sin precios</span>
                <span
                    v-if="maxDiscount(game.products) > 0"
                    class="rounded bg-red-600/20 px-2 py-0.5 text-xs font-bold text-red-400"
                >
                    -{{ maxDiscount(game.products) }}%
                </span>
            </div>
        </div>
    </Link>
</template>
