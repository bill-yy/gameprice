<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    popularGenres: {
        type: Array,
        default: () => ['Action', 'Adventure', 'RPG', 'Strategy', 'Shooter', 'Indie', 'Simulation', 'Sports'],
    },
});
</script>

<template>
    <footer class="border-t border-gray-700 bg-gray-800 mt-12">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <h3 class="text-lg font-bold text-white">Game<span class="text-blue-500">Price</span><span class="text-gray-400">.es</span></h3>
                    <p class="mt-2 text-sm text-gray-400">
                        Compara precios de videojuegos en las principales tiendas y encuentra las mejores ofertas.
                    </p>
                </div>
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-300">Categorías populares</h4>
                    <ul class="mt-4 flex flex-wrap gap-2">
                        <li v-for="genre in popularGenres" :key="genre">
                            <Link
                                :href="route('genre.show', genre)"
                                class="rounded-full bg-gray-700 px-3 py-1 text-xs text-gray-300 transition hover:bg-gray-600 hover:text-white"
                            >
                                {{ genre }}
                            </Link>
                        </li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-300">Legal</h4>
                    <ul class="mt-4 space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition">Política de privacidad</a></li>
                        <li><a href="#" class="hover:text-white transition">Términos de uso</a></li>
                        <li><a href="#" class="hover:text-white transition">Contacto</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 border-t border-gray-700 pt-6 text-center text-xs text-gray-500">
                &copy; {{ new Date().getFullYear() }} GamePrice.es. Todos los derechos reservados.
            </div>
        </div>
    </footer>
</template>
