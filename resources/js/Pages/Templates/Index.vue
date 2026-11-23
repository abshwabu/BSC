<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    templates: {
        type: Array,
        required: true,
    },
});

const { t } = useI18n();

const selectedPosition = ref('all');
const searchQuery = ref('');
const isModalOpen = ref(false);
const editingTemplate = ref(null);

const form = useForm({
    position_key: 'sales',
    title: '',
    activity_text: '',
    kpi_text: '',
    default_target_value: '',
    default_target_label: '',
    direction: 'higher_better',
    perspective: 'customers',
    sort_order: '',
});

const filteredTemplates = computed(() => {
    return props.templates.filter((tpl) => {
        const matchesPos = selectedPosition.value === 'all' || tpl.position_key === selectedPosition.value;
        const q = searchQuery.value.toLowerCase().trim();
        const matchesQuery = !q ||
            tpl.title.toLowerCase().includes(q) ||
            tpl.activity_text.toLowerCase().includes(q) ||
            tpl.kpi_text.toLowerCase().includes(q);
        return matchesPos && matchesQuery;
    });
});

function openCreateModal() {
    editingTemplate.value = null;
    form.reset();
    form.position_key = selectedPosition.value !== 'all' ? selectedPosition.value : 'sales';
    form.direction = 'higher_better';
    form.perspective = 'customers';
    isModalOpen.value = true;
}

function openEditModal(tpl) {
    editingTemplate.value = tpl;
    form.position_key = tpl.position_key;
    form.title = tpl.title;
    form.activity_text = tpl.activity_text;
    form.kpi_text = tpl.kpi_text;
    form.default_target_value = tpl.default_target_value;
    form.default_target_label = tpl.default_target_label;
    form.direction = tpl.direction;
    form.perspective = tpl.perspective;
    form.sort_order = tpl.sort_order;
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editingTemplate.value = null;
    form.reset();
}

function submitForm() {
    if (editingTemplate.value) {
        form.put(route('templates.update', editingTemplate.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('templates.store'), {
            onSuccess: () => closeModal(),
        });
    }
}

function deleteTemplate(tpl) {
    if (confirm('እርግጠኛ ነዎት ይህን ቴምፕሌት መሰረዝ ይፈልጋሉ? (Are you sure you want to delete this template?)')) {
        router.delete(route('templates.destroy', tpl.id));
    }
}

function getRoleBadge(pos) {
    switch (pos) {
        case 'sales':
            return { label: 'ሽያጭና ንግድ (Sales)', color: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' };
        case 'marketing':
            return { label: 'ማርኬቲንግ (Marketing)', color: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800' };
        case 'tech':
            return { label: 'ቴክኖሎጂ (Technology)', color: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800' };
        default:
            return { label: 'አጠቃላይ (Custom)', color: 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700' };
    }
}
</script>

<template>
    <Head :title="$t('templates_page.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span class="text-2xl">📋</span>
                        <span>{{ $t('templates_page.title') }}</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('templates_page.subtitle') }}
                    </p>
                </div>
                <div>
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors"
                    >
                        <span>➕</span>
                        <span>{{ $t('templates_page.new_template') }}</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <!-- Filters & Search Bar -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex flex-col md:flex-row items-center justify-between gap-4">
                    <!-- Position tabs -->
                    <div class="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
                        <button
                            type="button"
                            @click="selectedPosition = 'all'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border"
                            :class="selectedPosition === 'all'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-gray-50 hover:bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600'"
                        >
                            {{ $t('templates_page.all_roles') }} ({{ templates.length }})
                        </button>
                        <button
                            type="button"
                            @click="selectedPosition = 'sales'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border"
                            :class="selectedPosition === 'sales'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-gray-50 hover:bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600'"
                        >
                            💼 ሽያጭና ንግድ (Sales)
                        </button>
                        <button
                            type="button"
                            @click="selectedPosition = 'marketing'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border"
                            :class="selectedPosition === 'marketing'
                                ? 'bg-purple-600 text-white border-purple-600 shadow-xs'
                                : 'bg-gray-50 hover:bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600'"
                        >
                            📢 ማርኬቲንግ (Marketing)
                        </button>
                        <button
                            type="button"
                            @click="selectedPosition = 'tech'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border"
                            :class="selectedPosition === 'tech'
                                ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                                : 'bg-gray-50 hover:bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600'"
                        >
                            ⚙️ ቴክኖሎጂ (Technology)
                        </button>
                    </div>

                    <!-- Search Box -->
                    <div class="w-full md:w-72 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">🔍</span>
                        <input
                            type="text"
                            v-model="searchQuery"
                            :placeholder="$t('templates_page.search_placeholder')"
                            class="block w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <!-- Templates Grid -->
                <div v-if="filteredTemplates.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="tpl in filteredTemplates"
                        :key="tpl.id"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex flex-col justify-between hover:border-indigo-300 dark:hover:border-indigo-600 transition-all hover:shadow-md"
                    >
                        <div class="space-y-2">
                            <!-- Role & Perspective Header -->
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                    :class="getRoleBadge(tpl.position_key).color"
                                >
                                    {{ getRoleBadge(tpl.position_key).label }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $t(`perspectives.${tpl.perspective}`) }}
                                </span>
                            </div>

                            <!-- Title -->
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white leading-snug">
                                {{ tpl.title }}
                            </h4>

                            <!-- Activity text -->
                            <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed font-sans line-clamp-3">
                                {{ tpl.activity_text }}
                            </p>

                            <!-- KPI Metric -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700/60 space-y-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">
                                    {{ $t('templates_page.fields.kpi') }}:
                                </span>
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                    {{ tpl.kpi_text }}
                                </p>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/80 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ tpl.default_target_value ?? '' }} {{ tpl.default_target_label ?? '' }}
                                </span>
                                <span
                                    class="text-[10px] px-1.5 py-0.5 rounded font-semibold border"
                                    :class="tpl.direction === 'higher_better'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800'
                                        : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800'"
                                >
                                    {{ tpl.direction === 'higher_better' ? '📈 ' + $t('table.higher_short') : '📉 ' + $t('table.lower_short') }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    @click="openEditModal(tpl)"
                                    class="p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                    :title="$t('templates_page.edit_template')"
                                >
                                    ✏️
                                </button>
                                <button
                                    type="button"
                                    @click="deleteTemplate(tpl)"
                                    class="p-1 text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors"
                                    title="ሰርዝ"
                                >
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center space-y-3">
                    <span class="text-4xl block">🔍</span>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">
                        {{ $t('templates_page.no_templates') }}
                    </h3>
                    <p class="text-xs text-gray-500 max-w-sm mx-auto">
                        የፍለጋ ቃላትን ይቀይሩ ወይም አዲስ ቴምፕሌት በማከል ይጀምሩ።
                    </p>
                </div>
            </div>
        </div>

        <!-- Create / Edit Template Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full p-6 space-y-4 border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>{{ editingTemplate ? '✏️' : '➕' }}</span>
                        <span>{{ editingTemplate ? $t('templates_page.edit_template') : $t('templates_page.new_template') }}</span>
                    </h3>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('templates_page.fields.role') }}
                            </label>
                            <select
                                v-model="form.position_key"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                                required
                            >
                                <option value="sales">ሽያጭና ንግድ (Sales)</option>
                                <option value="marketing">ማርኬቲንግ (Marketing)</option>
                                <option value="tech">ቴክኖሎጂ (Technology)</option>
                                <option value="custom">አጠቃላይ (Custom)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('templates_page.fields.perspective') }}
                            </label>
                            <select
                                v-model="form.perspective"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                                required
                            >
                                <option value="financial">{{ $t('perspectives.financial') }}</option>
                                <option value="customers">{{ $t('perspectives.customers') }}</option>
                                <option value="internal">{{ $t('perspectives.internal') }}</option>
                                <option value="learning">{{ $t('perspectives.learning') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('templates_page.fields.title') }}
                        </label>
                        <input
                            type="text"
                            v-model="form.title"
                            placeholder="ለምሳሌ፡ የደንበኞች ማሳያ (Product Demos)"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('templates_page.fields.activity') }}
                        </label>
                        <textarea
                            v-model="form.activity_text"
                            rows="2"
                            placeholder="የተግባር ዝርዝር መግለጫ..."
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                            required
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('templates_page.fields.kpi') }}
                        </label>
                        <input
                            type="text"
                            v-model="form.kpi_text"
                            placeholder="የመለኪያ ስም..."
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('templates_page.fields.default_target') }}
                            </label>
                            <input
                                type="number"
                                step="any"
                                v-model="form.default_target_value"
                                placeholder="60"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('templates_page.fields.default_unit') }}
                            </label>
                            <input
                                type="text"
                                v-model="form.default_target_label"
                                placeholder="ማሳያዎች"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('templates_page.fields.direction') }}
                            </label>
                            <select
                                v-model="form.direction"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                            >
                                <option value="higher_better">📈 {{ $t('table.higher_short') }}</option>
                                <option value="lower_better">📉 {{ $t('table.lower_short') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                        >
                            {{ $t('new_report_modal.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-50"
                        >
                            {{ form.processing ? 'በመመዝገብ ላይ...' : 'መዝግብ' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
