@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-gray-800 border-gray-700 text-gray-100 focus:border-pink-500 focus:ring-pink-500 rounded-md shadow-sm placeholder-gray-500']) }}>
