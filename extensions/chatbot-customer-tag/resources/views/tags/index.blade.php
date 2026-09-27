@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $title)
@section('titlebar_subtitle', $description)
@section('titlebar_actions')
    <x-button
        x-data="{}"
        @click.prevent="Alpine.$data(document.querySelector('#customer-tags-page')).openCreateModal()"
    >
        <x-tabler-plus class="size-4" />
        {{ __('Add Tag') }}
    </x-button>
@endsection

@section('content')
    <div
        class="py-10"
        id="customer-tags-page"
        x-data="customerTagsPage"
    >
        <x-table>
            <x-slot:head>
                <tr>
                    <th>
                        {{ __('ID') }}
                    </th>
                    <th>
                        {{ __('chatbot-customer-tag::messages.tag') }}
                    </th>
                    <th>
                        {{ __('chatbot-customer-tag::messages.tag_color') }}
                    </th>
                    <th>
                        {{ __('Created At') }}
                    </th>
                    <th class="text-end">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </x-slot:head>

            <x-slot:body>
                {{-- SSR rows: visible before Alpine boots, self-remove once Alpine initializes --}}
                @forelse ($items as $entry)
                    <tr
                        x-data
                        x-init="$el.remove()"
                    >
                        <td>
                            {{ $entry->getKey() }}
                        </td>
                        <td>
                            {{ $entry->tag }}
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-5 rounded-full border"
                                    style="background-color: {{ $entry->tag_color ?? 'transparent' }}"
                                ></span>
                                <span class="text-xs uppercase tracking-wider opacity-70">{{ $entry->tag_color ?? '—' }}</span>
                            </div>
                        </td>
                        <td>
                            {{ $entry->created_at?->format('Y-m-d H:i') }}
                        </td>
                        <td class="text-end">
                            <x-button
                                class="size-9"
                                size="none"
                                variant="ghost-shadow"
                                hover-variant="primary"
                                title="{{ __('Edit') }}"
                            >
                                <x-tabler-pencil class="size-4" />
                            </x-button>
                            <x-button
                                class="size-9"
                                size="none"
                                variant="ghost-shadow"
                                hover-variant="danger"
                                title="{{ __('Delete') }}"
                            >
                                <x-tabler-x class="size-4" />
                            </x-button>
                        </td>
                    </tr>
                @empty
                    <tr
                        x-data
                        x-init="$el.remove()"
                    >
                        <td
                            class="text-muted py-6 text-center text-sm"
                            colspan="5"
                        >
                            {{ __('No records found') }}
                        </td>
                    </tr>
                @endforelse

                {{-- Alpine-managed rows --}}
                <template
                    x-for="entry in tags"
                    :key="entry.id"
                >
                    <tr>
                        <td x-text="entry.id"></td>
                        <td x-text="entry.tag"></td>
                        <td>
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-5 rounded-full border"
                                    :style="{ backgroundColor: entry.tag_color }"
                                ></span>
                                <span
                                    class="text-xs uppercase tracking-wider opacity-70"
                                    x-text="entry.tag_color ?? '—'"
                                ></span>
                            </div>
                        </td>
                        <td x-text="entry.created_at_formatted ?? '—'"></td>
                        <td class="text-end">
                            <x-button
                                class="size-9"
                                size="none"
                                variant="ghost-shadow"
                                hover-variant="primary"
                                title="{{ __('Edit') }}"
                                @click.prevent="openEditModal(entry.id)"
                            >
                                <x-tabler-pencil class="size-4" />
                            </x-button>
                            <x-button
                                class="size-9"
                                size="none"
                                variant="ghost-shadow"
                                hover-variant="danger"
                                title="{{ __('Delete') }}"
                                @click.prevent="deleteTag(entry.id)"
                            >
                                <x-tabler-x class="size-4" />
                            </x-button>
                        </td>
                    </tr>
                </template>

                {{-- Empty state for Alpine (after SSR rows are gone) --}}
                <template x-if="tags.length === 0">
                    <tr>
                        <td
                            class="text-muted py-6 text-center text-sm"
                            colspan="5"
                        >
                            {{ __('No records found') }}
                        </td>
                    </tr>
                </template>
            </x-slot:body>
        </x-table>

        <div class="mt-6">
            {{ $items->links() }}
        </div>

        <x-modal id="customer-tag-modal">
            <x-slot:title>
                <span x-text="isEditing ? '{{ __('chatbot-customer-tag::messages.edit_title') }}' : '{{ __('chatbot-customer-tag::messages.create_title') }}'"></span>
            </x-slot:title>

            <x-slot:modal>
                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="submitTag"
                >
                    <x-forms.input
                        id="tag"
                        size="lg"
                        name="tag"
                        label="{{ __('chatbot-customer-tag::messages.tag') }}"
                        placeholder="{{ __('chatbot-customer-tag::messages.tag') }}"
                        x-model="form.tag"
                        required
                        ::class="errors.tag && 'border-red-500'"
                    />
                    <template x-if="errors.tag">
                        <p
                            class="text-xs text-red-500"
                            x-text="errors.tag[0]"
                        ></p>
                    </template>

                    <x-forms.input
                        id="tag_color"
                        type="color"
                        size="lg"
                        name="tag_color"
                        label="{{ __('chatbot-customer-tag::messages.tag_color') }}"
                        x-model="form.tag_color"
                        x-init="$watch('modalOpen', value => $nextTick(() => { picker.setColor(form.tag_color) }))"
                    />

                    @if ($app_is_demo ?? false)
                        <x-button
                            class="w-full"
                            size="lg"
                            onclick="return toastr.info('This feature is disabled in Demo version.')"
                            type="button"
                        >
                            {{ __('Save') }}
                        </x-button>
                    @else
                        <x-button
                            class="w-full"
                            size="lg"
                            type="submit"
                            ::disabled="loading"
                        >
                            <span
                                class="hidden"
                                :class="{ '!inline-flex': loading }"
                            >
                                <x-tabler-loader-2 class="size-4 animate-spin" />
                            </span>
                            {{ __('Save') }}
                        </x-button>
                    @endif
                </form>
            </x-slot:modal>
        </x-modal>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('customerTagsPage', () => ({
                tags: @json(collect($items->items())->map(fn($item) => array_merge($item->toArray(), ['created_at_formatted' => $item->created_at?->format('Y-m-d H:i')]))),
                loading: false,
                isEditing: false,
                editId: null,
                form: {
                    tag: '',
                    tag_color: '#6366f1'
                },
                errors: {},

                modalData() {
                    return Alpine.$data(document.querySelector('#customer-tag-modal'));
                },

                resetForm() {
                    this.form = {
                        tag: '',
                        tag_color: '#6366f1'
                    };
                    this.errors = {};
                    this.editId = null;
                    this.isEditing = false;
                },

                openCreateModal() {
                    this.resetForm();
                    this.modalData().modalOpen = true;
                },

                async openEditModal(id) {
                    this.loading = true;
                    this.errors = {};

                    try {
                        const url = '{{ route('dashboard.chatbot-customer-tags.edit', ':id') }}'.replace(':id', id);
                        const response = await fetch(url, {
                            headers: {
                                Accept: 'application/json'
                            }
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            toastr.error(data.message || '{{ __('Something went wrong.') }}');
                            return;
                        }

                        this.isEditing = true;
                        this.editId = id;
                        this.form = {
                            tag: data.tag?.tag ?? '',
                            tag_color: data.tag?.tag_color ?? '#6366f1',
                        };
                        this.modalData().modalOpen = true;
                    } catch {
                        toastr.error('{{ __('Something went wrong.') }}');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitTag() {
                    this.loading = true;
                    this.errors = {};

                    try {
                        const url = this.isEditing ?
                            '{{ route('dashboard.chatbot-customer-tags.update', ':id') }}'.replace(':id', this.editId) :
                            '{{ route('dashboard.chatbot-customer-tags.store') }}';

                        const response = await fetch(url, {
                            method: this.isEditing ? 'PUT' : 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify(this.form),
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            if (data.errors) {
                                this.errors = data.errors;
                                return;
                            }
                            toastr.error(data.message || '{{ __('Something went wrong.') }}');
                            return;
                        }

                        if (this.isEditing) {
                            const index = this.tags.findIndex(t => t.id === this.editId);
                            if (index !== -1) {
                                this.tags[index] = data.tag;
                            }
                        } else {
                            this.tags.unshift(data.tag);
                        }

                        toastr.success(data.message);
                        this.modalData().modalOpen = false;
                        this.resetForm();
                    } catch {
                        toastr.error('{{ __('Something went wrong.') }}');
                    } finally {
                        this.loading = false;
                    }
                },

                async deleteTag(id) {
                    if (!confirm('{{ __('Are you sure? This is permanent.') }}')) {
                        return;
                    }

                    try {
                        const url = '{{ route('dashboard.chatbot-customer-tags.destroy', ':id') }}'.replace(':id', id);
                        const response = await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            toastr.error(data.message || '{{ __('Something went wrong.') }}');
                            return;
                        }

                        this.tags = this.tags.filter(t => t.id !== id);
                        toastr.success(data.message);
                    } catch {
                        toastr.error('{{ __('Something went wrong.') }}');
                    }
                },
            }));
        });
    </script>
@endpush
