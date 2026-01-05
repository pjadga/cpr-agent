<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profile Setup') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="mb-4">Welcome, {{ $user->name }}. Please complete your profile setup.</p>

                    <form method="POST" action="{{ route('profile-setup.store') }}">
                        @csrf

                        <!-- Client ID -->
                        <div class="mt-4">
                            <x-input-label for="client_id" :value="__('Foundry Client ID')" />
                            <select id="client_id" name="client_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="" disabled {{ !$user->client_id ? 'selected' : '' }}>Select a Client ID</option>
                                @foreach($clientIds as $id => $name)
                                    <option value="{{ $id }}" {{ $user->client_id == $id ? 'selected' : '' }}>{{ $name }} ({{ $id }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                        </div>

                        <!-- Default Actor ID -->
                        <div class="mt-4">
                            <x-input-label for="default_actor_id" :value="__('Default Actor')" />
                            <select id="default_actor_id" name="default_actor_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="" disabled {{ !$user->default_actor_id ? 'selected' : '' }}>Select your Default Actor</option>
                                @foreach($actors as $id => $name)
                                    <option value="{{ $id }}" {{ $user->default_actor_id == $id ? 'selected' : '' }}>{{ $name }} ({{ $id }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('default_actor_id')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button class="ms-3">
                                {{ __('Save Profile') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
