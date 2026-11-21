import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    safelist: [
        'bg-blue-500/15', 'bg-emerald-500/15', 'bg-amber-500/20', 'bg-indigo-500/15',
        'bg-teal-500/15', 'bg-violet-500/15', 'bg-rose-500/15', 'bg-cyan-500/15', 'bg-slate-400/20',
        'text-blue-800', 'text-blue-900', 'text-emerald-800', 'text-emerald-900',
        'text-amber-800', 'text-amber-900', 'text-indigo-800', 'text-indigo-900',
        'text-teal-800', 'text-teal-900', 'text-violet-800', 'text-violet-900',
        'text-rose-800', 'text-rose-900', 'text-cyan-800', 'text-cyan-900',
        'text-slate-700', 'text-slate-900',
        // Detail action buttons
        'bg-indigo-600', 'hover:bg-indigo-700',
        'bg-teal-600', 'hover:bg-teal-700',
        'bg-sky-600', 'hover:bg-sky-700',
        'bg-amber-600', 'hover:bg-amber-700',
        'bg-orange-600', 'hover:bg-orange-700',
        'bg-violet-600', 'hover:bg-violet-700',
        'bg-blue-600', 'hover:bg-blue-700',
        'bg-emerald-600', 'hover:bg-emerald-700',
        'bg-red-600', 'hover:bg-red-700',
        'text-white',
    ],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './node_modules/flowbite/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, require('flowbite/plugin')],
};
