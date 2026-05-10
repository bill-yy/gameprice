<script setup>
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    game: {
        type: Object,
        required: true,
    },
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    game_id: props.game.id,
    email: '',
    target_price: '',
});

const submit = () => {
    form.post(route('alerts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Alerta de precio para {{ game.title }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Te avisaremos por email cuando el precio baje de tu objetivo.
            </p>

            <form @submit.prevent="submit" class="mt-6 space-y-4">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="target_price" value="Precio objetivo (€)" />
                    <TextInput
                        id="target_price"
                        v-model="form.target_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.target_price" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="emit('close')">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        Crear alerta
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
