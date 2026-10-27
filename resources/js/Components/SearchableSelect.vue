<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Search, ChevronDown, X, Check } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [String, Number, Array, Object],
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: 'Search and select...',
    },
    valueKey: {
        type: String,
        default: 'id',
    },
    labelKey: {
        type: String,
        default: 'label',
    },
    subtextKey: {
        type: String,
        default: 'subtext',
    },
    badgeKey: {
        type: String,
        default: 'badge',
    },
    avatarKey: {
        type: String,
        default: 'avatar',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    allowClear: {
        type: Boolean,
        default: true,
    },
    emptyMessage: {
        type: String,
        default: 'No options found',
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'select', 'remove']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const searchInputRef = ref(null);

// Normalize options to ensure consistent lookup
const normalizedOptions = computed(() => {
    return props.options.map(opt => {
        if (typeof opt === 'string' || typeof opt === 'number') {
            return {
                id: opt,
                label: String(opt),
                subtext: '',
                badge: '',
                avatar: '',
                raw: opt,
            };
        }
        return {
            id: opt[props.valueKey] !== undefined ? opt[props.valueKey] : opt.id,
            label: opt[props.labelKey] !== undefined ? opt[props.labelKey] : (opt.name || opt.title || String(opt.id)),
            subtext: opt[props.subtextKey] !== undefined ? opt[props.subtextKey] : (opt.email || opt.department || opt.role || ''),
            badge: opt[props.badgeKey] !== undefined ? opt[props.badgeKey] : '',
            avatar: opt[props.avatarKey] !== undefined ? opt[props.avatarKey] : (opt.avatar || ''),
            raw: opt,
        };
    });
});

// Filtered options based on search query
const filteredOptions = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return normalizedOptions.value;
    return normalizedOptions.value.filter(opt => {
        const matchLabel = opt.label && String(opt.label).toLowerCase().includes(q);
        const matchSub = opt.subtext && String(opt.subtext).toLowerCase().includes(q);
        const matchBadge = opt.badge && String(opt.badge).toLowerCase().includes(q);
        return matchLabel || matchSub || matchBadge;
    });
});

// Selected items computation
const selectedItems = computed(() => {
    if (props.multiple) {
        if (!Array.isArray(props.modelValue)) return [];
        return props.modelValue.map(val => {
            const found = normalizedOptions.value.find(opt => String(opt.id) === String(val));
            return found || { id: val, label: String(val), subtext: '', badge: '', avatar: '' };
        });
    } else {
        if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return null;
        return normalizedOptions.value.find(opt => String(opt.id) === String(props.modelValue)) || null;
    }
});

function isSelected(optId) {
    if (props.multiple) {
        if (!Array.isArray(props.modelValue)) return false;
        return props.modelValue.some(val => String(val) === String(optId));
    }
    return String(props.modelValue) === String(optId);
}

function toggleDropdown() {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        setTimeout(() => {
            if (searchInputRef.value) searchInputRef.value.focus();
        }, 50);
    }
}

function selectOption(opt) {
    if (props.multiple) {
        const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
        const index = current.findIndex(val => String(val) === String(opt.id));
        if (index > -1) {
            current.splice(index, 1);
            emit('remove', opt.id, opt);
        } else {
            current.push(opt.id);
            emit('select', opt.id, opt);
        }
        emit('update:modelValue', current);
        emit('change', current);
    } else {
        emit('update:modelValue', opt.id);
        emit('change', opt.id);
        emit('select', opt.id, opt);
        isOpen.value = false;
        searchQuery.value = '';
    }
}

function removeItem(optId, e) {
    if (e) e.stopPropagation();
    if (!props.multiple) return;
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    const next = current.filter(val => String(val) !== String(optId));
    emit('update:modelValue', next);
    emit('change', next);
    emit('remove', optId);
}

function clearSelection(e) {
    if (e) e.stopPropagation();
    if (props.multiple) {
        emit('update:modelValue', []);
        emit('change', []);
    } else {
        emit('update:modelValue', '');
        emit('change', '');
    }
    searchQuery.value = '';
}

function handleClickOutside(event) {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        isOpen.value = false;
        searchQuery.value = '';
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative w-full text-xs select-none">
        <!-- Trigger Display Box -->
        <div
            @click="toggleDropdown"
            :class="[
                'min-h-[38px] w-full px-2.5 py-1.5 bg-slate-50 border rounded-xl flex items-center justify-between cursor-pointer transition-all',
                isOpen ? 'border-indigo-600 ring-2 ring-indigo-100 bg-white shadow-2xs' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-100/60',
                disabled ? 'opacity-60 cursor-not-allowed bg-slate-100' : ''
            ]"
        >
            <!-- Content / Selected Chips -->
            <div class="flex-1 flex flex-wrap items-center gap-1.5 min-w-0 pr-1">
                <!-- MULTIPLE MODE: Chips -->
                <template v-if="multiple">
                    <span
                        v-for="item in selectedItems"
                        :key="item.id"
                        class="inline-flex items-center space-x-1 pl-1.5 pr-1 py-0.5 rounded-lg text-xs bg-indigo-50 border border-indigo-200 text-indigo-900 font-medium"
                    >
                        <img v-if="item.avatar" :src="item.avatar" class="w-3.5 h-3.5 rounded-full object-cover" />
                        <span class="truncate max-w-[140px]">{{ item.label }}</span>
                        <button
                            type="button"
                            @click="removeItem(item.id, $event)"
                            class="p-0.5 rounded hover:bg-indigo-200 text-indigo-400 hover:text-indigo-800 transition-colors"
                        >
                            <X class="w-3 h-3" />
                        </button>
                    </span>
                    <span v-if="selectedItems.length === 0" class="text-slate-400 text-xs py-0.5">
                        {{ placeholder }}
                    </span>
                </template>

                <!-- SINGLE MODE: Single selection -->
                <template v-else>
                    <div v-if="selectedItems" class="flex items-center space-x-2 truncate">
                        <img v-if="selectedItems.avatar" :src="selectedItems.avatar" class="w-4 h-4 rounded-full object-cover" />
                        <span class="font-bold text-slate-800 truncate">{{ selectedItems.label }}</span>
                        <span v-if="selectedItems.subtext" class="text-[10px] text-slate-400 truncate">({{ selectedItems.subtext }})</span>
                        <span v-if="selectedItems.badge" class="px-1.5 py-0.2 rounded text-[9px] bg-slate-100 text-slate-600 font-medium">
                            {{ selectedItems.badge }}
                        </span>
                    </div>
                    <span v-else class="text-slate-400 text-xs">
                        {{ placeholder }}
                    </span>
                </template>
            </div>

            <!-- Right Controls: Clear and Chevron -->
            <div class="flex items-center space-x-1 shrink-0 text-slate-400">
                <button
                    v-if="allowClear && ((multiple && selectedItems.length > 0) || (!multiple && selectedItems))"
                    type="button"
                    @click="clearSelection"
                    class="p-0.5 rounded hover:text-slate-600 hover:bg-slate-200/60 transition-colors"
                    title="Clear selection"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
                <ChevronDown :class="['w-3.5 h-3.5 transition-transform duration-200', isOpen ? 'rotate-180 text-indigo-600' : '']" />
            </div>
        </div>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden p-1.5 space-y-1.5 animate-in fade-in-50 zoom-in-95 duration-100"
        >
            <!-- Search Bar inside dropdown -->
            <div class="relative">
                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Type to filter..."
                    class="w-full pl-8 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600"
                    @click.stop
                />
            </div>

            <!-- Options List -->
            <div class="max-h-52 overflow-y-auto space-y-0.5 pr-0.5">
                <div
                    v-for="opt in filteredOptions"
                    :key="opt.id"
                    @click="selectOption(opt)"
                    :class="[
                        'flex items-center justify-between px-2.5 py-1.5 rounded-xl cursor-pointer text-xs transition-colors',
                        isSelected(opt.id)
                            ? 'bg-indigo-50 text-indigo-900 font-semibold'
                            : 'hover:bg-slate-100/80 text-slate-700'
                    ]"
                >
                    <div class="flex items-center space-x-2 truncate">
                        <img v-if="opt.avatar" :src="opt.avatar" class="w-5 h-5 rounded-full object-cover shrink-0" />
                        <div class="truncate">
                            <div class="truncate font-medium">{{ opt.label }}</div>
                            <div v-if="opt.subtext" class="text-[10px] text-slate-400 truncate">{{ opt.subtext }}</div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-1.5 shrink-0 ml-2">
                        <span v-if="opt.badge" class="px-1.5 py-0.5 rounded text-[9px] bg-slate-100 text-slate-600 font-normal">
                            {{ opt.badge }}
                        </span>
                        <Check v-if="isSelected(opt.id)" class="w-3.5 h-3.5 text-indigo-600 stroke-[2.5]" />
                    </div>
                </div>

                <div v-if="filteredOptions.length === 0" class="py-4 text-center text-slate-400 text-xs">
                    {{ emptyMessage }}
                </div>
            </div>
        </div>
    </div>
</template>
